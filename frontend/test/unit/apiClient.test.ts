import { describe, expect, it, vi } from 'vitest'
import {
  ApiError,
  ApiValidationError,
  createApiClient,
  readCookie,
  toFormErrors,
  type FetchOptions
} from '~/utils/apiClient'

/** An error shaped like ofetch's FetchError. */
function httpError(status: number, data?: unknown): Error {
  return Object.assign(new Error(`HTTP ${status}`), { response: { status }, data })
}

type Handler = (url: string, options: FetchOptions) => unknown

/**
 * A client over a fake $fetch. `handle` answers API calls; the CSRF endpoint sets the cookie
 * like Laravel does. Every call is recorded.
 */
function setup(handle: Handler, { cookie = 'token-1' as string | null, locale = 'en' } = {}) {
  let xsrf = cookie
  let currentLocale = locale
  let csrfRefreshes = 0
  const calls: Array<{ url: string, options: FetchOptions }> = []

  const fetch = vi.fn(async (url: string, options: FetchOptions) => {
    calls.push({ url, options })

    if (url === '/sanctum/csrf-cookie') {
      csrfRefreshes++
      xsrf = `token-${csrfRefreshes + 1}`

      return undefined
    }

    return handle(url, options)
  })

  const onUnauthenticated = vi.fn()
  const onError = vi.fn()

  const api = createApiClient({
    baseURL: 'http://localhost:8000',
    fetch: fetch as never,
    readCookie: () => xsrf,
    locale: () => currentLocale,
    onUnauthenticated,
    onError
  })

  return {
    api,
    setLocale: (value: string) => {
      currentLocale = value
    },
    calls,
    apiCalls: () => calls.filter(call => call.url !== '/sanctum/csrf-cookie'),
    csrfRefreshes: () => csrfRefreshes,
    onUnauthenticated,
    onError
  }
}

describe('requests', () => {
  it('sends credentials, JSON accept and the XSRF header to the versioned API', async () => {
    const { api, calls } = setup(() => ({ data: [] }))

    await expect(api.get('/weddings', { query: { page: 2 } })).resolves.toEqual({ data: [] })

    expect(calls).toEqual([{
      url: '/weddings',
      options: {
        method: 'GET',
        baseURL: 'http://localhost:8000/api/v1',
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'Accept-Language': 'en', 'X-XSRF-TOKEN': 'token-1' },
        body: undefined,
        query: { page: 2 }
      }
    }])
  })

  it('fetches the CSRF cookie before the first write and sends the new token', async () => {
    const { api, calls } = setup(() => ({ data: { id: 1 } }), { cookie: null })

    await api.post('/auth/login', { email: 'a@b.test', password: 'secret' })

    expect(calls.map(call => call.url)).toEqual(['/sanctum/csrf-cookie', '/auth/login'])
    expect(calls[0]!.options).toMatchObject({ baseURL: 'http://localhost:8000', credentials: 'include' })
    expect(calls[1]!.options.headers['X-XSRF-TOKEN']).toBe('token-2')
  })

  it('sends the current UI language, read again on every request', async () => {
    const { api, apiCalls, setLocale } = setup(() => ({ data: null }), { locale: 'lo' })

    await api.get('/weddings')
    setLocale('en')
    await api.get('/weddings')

    expect(apiCalls().map(call => call.options.headers['Accept-Language'])).toEqual(['lo', 'en'])
  })

  it('does not fetch a CSRF cookie for reads', async () => {
    const { api, csrfRefreshes } = setup(() => ({ data: null }), { cookie: null })

    await api.get('/auth/user')

    expect(csrfRefreshes()).toBe(0)
  })
})

describe('419 CSRF token mismatch', () => {
  it('refreshes the token and retries once with the new token', async () => {
    let attempts = 0
    const { api, apiCalls, csrfRefreshes, onError } = setup(() => {
      attempts++
      if (attempts === 1) {
        throw httpError(419, { message: 'CSRF token mismatch.' })
      }

      return { data: 'saved' }
    })

    await expect(api.post('/weddings', { name: 'Noy & Somchai' })).resolves.toEqual({ data: 'saved' })

    expect(csrfRefreshes()).toBe(1)
    expect(apiCalls().map(call => call.options.headers['X-XSRF-TOKEN'])).toEqual(['token-1', 'token-2'])
    expect(onError).not.toHaveBeenCalled()
  })

  it('retries only once, then reports the error', async () => {
    const { api, apiCalls, onError } = setup(() => {
      throw httpError(419, { message: 'CSRF token mismatch.' })
    })

    const error = await api.post('/weddings', {}).catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(419)
    expect(apiCalls()).toHaveLength(2)
    expect(onError).toHaveBeenCalledTimes(1)
  })

  it('shares one token refresh between concurrent requests', async () => {
    const seen = new Set<string>()
    const { api, csrfRefreshes } = setup((url) => {
      if (!seen.has(url)) {
        seen.add(url)
        throw httpError(419)
      }

      return { data: url }
    })

    await Promise.all([api.post('/a'), api.post('/b'), api.post('/c')])

    expect(csrfRefreshes()).toBe(1)
  })
})

describe('401 unauthenticated', () => {
  it('asks the app to send the user to login, without a toast', async () => {
    const { api, onUnauthenticated, onError } = setup(() => {
      throw httpError(401, { message: 'Unauthenticated.' })
    })

    const error = await api.get('/weddings').catch((caught: unknown) => caught)

    expect((error as ApiError).status).toBe(401)
    expect(onUnauthenticated).toHaveBeenCalledTimes(1)
    expect(onError).not.toHaveBeenCalled()
  })

  it('stays put when a 401 is an expected answer', async () => {
    const { api, onUnauthenticated } = setup(() => {
      throw httpError(401)
    })

    await expect(api.get('/auth/user', { allowUnauthenticated: true })).rejects.toBeInstanceOf(ApiError)

    expect(onUnauthenticated).not.toHaveBeenCalled()
  })
})

describe('422 validation errors', () => {
  const body = {
    message: 'The email field must be a valid email address. (and 1 more error)',
    errors: {
      email: ['The email field must be a valid email address.', 'The email has already been taken.'],
      password: ['The password field must be at least 8 characters.']
    }
  }

  it('throws the field errors for the form, without a toast', async () => {
    const { api, onError, onUnauthenticated } = setup(() => {
      throw httpError(422, body)
    })

    const error = await api.post('/auth/register', {}).catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiValidationError)
    expect((error as ApiValidationError).errors).toEqual(body.errors)
    expect(onError).not.toHaveBeenCalled()
    expect(onUnauthenticated).not.toHaveBeenCalled()
  })

  it('maps to UForm errors with the first message per field', () => {
    expect(toFormErrors(new ApiValidationError(body))).toEqual([
      { name: 'email', message: 'The email field must be a valid email address.' },
      { name: 'password', message: 'The password field must be at least 8 characters.' }
    ])
  })
})

describe('other failures', () => {
  it.each([403, 404, 409, 429, 500, 503])('reports a %i once and rethrows it', async (status) => {
    const { api, onError, onUnauthenticated } = setup(() => {
      throw httpError(status, { message: 'Server says no.' })
    })

    const error = await api.delete('/weddings/1').catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).not.toBeInstanceOf(ApiValidationError)
    expect(error).toMatchObject({ status, message: 'Server says no.' })
    expect(onError).toHaveBeenCalledExactlyOnceWith(error)
    expect(onUnauthenticated).not.toHaveBeenCalled()
  })

  it('reports a network failure with no status', async () => {
    const { api, onError } = setup(() => {
      throw new TypeError('Failed to fetch')
    })

    const error = await api.get('/weddings').catch((caught: unknown) => caught)

    expect(error).toMatchObject({ status: null, message: 'Request failed' })
    expect(onError).toHaveBeenCalledTimes(1)
  })
})

describe('readCookie', () => {
  it('decodes the URL-encoded XSRF token Laravel sets', () => {
    expect(readCookie('i18n_locale=en; XSRF-TOKEN=eyJpdiI6Ik%3D%3D; other=1', 'XSRF-TOKEN')).toBe('eyJpdiI6Ik==')
  })

  it('returns null for a missing cookie, not a prefix match', () => {
    expect(readCookie('XSRF-TOKEN-OLD=1', 'XSRF-TOKEN')).toBeNull()
    expect(readCookie('', 'XSRF-TOKEN')).toBeNull()
  })
})
