import type { ApiClient } from '~/utils/apiClient'

/**
 * The shared API client: Sanctum CSRF cookie, credentials, 401 redirect, 419 retry,
 * 422 field errors (ApiValidationError) and error toasts. See utils/apiClient.ts.
 */
export function useApi(): ApiClient {
  return useNuxtApp().$api
}
