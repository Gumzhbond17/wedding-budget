import type { ValidationErrorBody } from '~/types/api'

/**
 * HTTP client for the Laravel API, free of Nuxt imports so its error handling can be unit
 * tested with a fake fetch. useApi() wires it to $fetch, cookies, the router and toasts.
 *
 * Sanctum SPA authentication (cookie based):
 * - every request is sent with credentials, so the browser attaches the session cookie;
 * - before the first state-changing request, GET /sanctum/csrf-cookie makes Laravel set the
 *   XSRF-TOKEN cookie; its value goes back in the X-XSRF-TOKEN header on every request
 *   (the frontend runs on another port, so the header is set by hand, not by the browser);
 * - 419 (CSRF token expired, e.g. after the session timed out): fetch a fresh token and
 *   retry the request once;
 * - 401: the session is gone, so the app is told to send the user to the login page;
 * - 422: thrown as ApiValidationError, whose field errors go straight into UForm;
 * - anything else (403, 404, 429, 5xx, network): reported once through onError (a toast),
 *   then thrown so the caller can stop its own flow.
 */

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export interface FetchOptions {
  method: HttpMethod
  baseURL: string
  credentials: RequestCredentials
  headers: Record<string, string>
  body?: unknown
  query?: Record<string, unknown>
}

/** The subset of ofetch's $fetch this client needs; errors carry response.status and data. */
export type Fetcher = <T>(url: string, options: FetchOptions) => Promise<T>

export interface RequestOptions {
  body?: unknown
  query?: Record<string, unknown>
  /**
   * A 401 is an expected answer (e.g. "who is logged in?"): throw it without redirecting.
   */
  allowUnauthenticated?: boolean
}

export interface ApiClientOptions {
  /** Laravel origin, e.g. http://localhost:8000 (no /api). */
  baseURL: string
  fetch: Fetcher
  readCookie: (name: string) => string | null
  onUnauthenticated: () => void | Promise<void>
  onError: (error: ApiError) => void
}

export const API_PREFIX = '/api/v1'
export const CSRF_COOKIE_PATH = '/sanctum/csrf-cookie'
export const XSRF_COOKIE = 'XSRF-TOKEN'
export const XSRF_HEADER = 'X-XSRF-TOKEN'

const SAFE_METHODS = new Set<HttpMethod>(['GET'])

export class ApiError extends Error {
  /** HTTP status, or null when no response arrived (network error, CORS failure). */
  readonly status: number | null
  readonly data: unknown

  constructor(message: string, status: number | null, data: unknown, options?: ErrorOptions) {
    super(message, options)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }
}

export class ApiValidationError extends ApiError {
  readonly errors: Record<string, string[]>

  constructor(body: ValidationErrorBody, options?: ErrorOptions) {
    super(body.message, 422, body, options)
    this.name = 'ApiValidationError'
    this.errors = body.errors
  }
}

/** UForm's FormError shape: one message per field, the first one Laravel reported. */
export function toFormErrors(error: ApiValidationError): Array<{ name: string, message: string }> {
  return Object.entries(error.errors)
    .filter(([, messages]) => messages.length > 0)
    .map(([name, messages]) => ({ name, message: messages[0]! }))
}

/** Value of one cookie from a document.cookie string; Laravel URL-encodes XSRF-TOKEN. */
export function readCookie(cookieHeader: string, name: string): string | null {
  for (const part of cookieHeader.split(';')) {
    const [key, ...value] = part.trim().split('=')

    if (key === name) {
      return decodeURIComponent(value.join('='))
    }
  }

  return null
}

function statusOf(cause: unknown): number | null {
  const response = (cause as { response?: { status?: unknown } } | null)?.response

  return typeof response?.status === 'number' ? response.status : null
}

function dataOf(cause: unknown): unknown {
  return (cause as { data?: unknown } | null)?.data
}

function isValidationBody(data: unknown): data is ValidationErrorBody {
  return typeof data === 'object' && data !== null
    && typeof (data as ValidationErrorBody).errors === 'object' && (data as ValidationErrorBody).errors !== null
}

function messageOf(data: unknown, fallback: string): string {
  const message = (data as { message?: unknown } | null)?.message

  return typeof message === 'string' && message !== '' ? message : fallback
}

export function createApiClient(options: ApiClientOptions) {
  // Shared by concurrent requests, so a burst of 419s triggers one token refresh, not many.
  let csrfRefresh: Promise<void> | null = null

  function refreshCsrfToken(): Promise<void> {
    csrfRefresh ??= options.fetch<unknown>(CSRF_COOKIE_PATH, {
      method: 'GET',
      baseURL: options.baseURL,
      credentials: 'include',
      headers: { Accept: 'application/json' }
    })
      .then(() => undefined)
      .finally(() => {
        csrfRefresh = null
      })

    return csrfRefresh
  }

  async function send<T>(method: HttpMethod, url: string, request: RequestOptions): Promise<T> {
    if (!SAFE_METHODS.has(method) && options.readCookie(XSRF_COOKIE) === null) {
      await refreshCsrfToken()
    }

    const headers: Record<string, string> = { Accept: 'application/json' }
    const token = options.readCookie(XSRF_COOKIE)

    if (token !== null) {
      headers[XSRF_HEADER] = token
    }

    return options.fetch<T>(url, {
      method,
      baseURL: options.baseURL + API_PREFIX,
      credentials: 'include',
      headers,
      body: request.body,
      query: request.query
    })
  }

  async function handleFailure(cause: unknown, request: RequestOptions): Promise<never> {
    const status = statusOf(cause)
    const data = dataOf(cause)

    if (status === 422 && isValidationBody(data)) {
      throw new ApiValidationError(data, { cause })
    }

    const error = new ApiError(messageOf(data, 'Request failed'), status, data, { cause })

    if (status === 401) {
      if (!request.allowUnauthenticated) {
        await options.onUnauthenticated()
      }

      throw error
    }

    options.onError(error)

    throw error
  }

  async function request<T>(method: HttpMethod, url: string, request: RequestOptions = {}): Promise<T> {
    try {
      return await send<T>(method, url, request)
    } catch (cause) {
      if (statusOf(cause) !== 419) {
        return handleFailure(cause, request)
      }

      // Stale CSRF token: refresh it and retry exactly once. A second 419 is reported.
      try {
        await refreshCsrfToken()

        return await send<T>(method, url, request)
      } catch (retryCause) {
        return handleFailure(retryCause, request)
      }
    }
  }

  return {
    request,
    get: <T>(url: string, opts?: Omit<RequestOptions, 'body'>) => request<T>('GET', url, opts),
    post: <T>(url: string, body?: unknown, opts?: RequestOptions) => request<T>('POST', url, { ...opts, body }),
    patch: <T>(url: string, body?: unknown, opts?: RequestOptions) => request<T>('PATCH', url, { ...opts, body }),
    put: <T>(url: string, body?: unknown, opts?: RequestOptions) => request<T>('PUT', url, { ...opts, body }),
    delete: <T>(url: string, opts?: RequestOptions) => request<T>('DELETE', url, opts),
    refreshCsrfToken
  }
}

export type ApiClient = ReturnType<typeof createApiClient>
