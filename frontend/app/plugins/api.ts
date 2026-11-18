import type { ApiError } from '~/utils/apiClient'

/**
 * One API client for the whole app (so concurrent 419s share one CSRF refresh), exposed as
 * useNuxtApp().$api and through useApi().
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const toast = useToast()
  const router = useRouter()

  function errorKey(error: ApiError): string {
    switch (error.status) {
      case null: return 'errors.network'
      case 403: return 'errors.forbidden'
      case 404: return 'errors.notFound'
      case 419: return 'errors.sessionExpired'
      case 429: return 'errors.tooManyRequests'
      default: return error.status >= 500 ? 'errors.server' : 'errors.generic'
    }
  }

  const api = createApiClient({
    baseURL: config.public.apiBase,
    // apiClient types the body as unknown (any JSON value or FormData); $fetch serializes it.
    fetch: (url, options) => $fetch(url, options as Parameters<typeof $fetch>[1]),
    readCookie: name => readCookie(document.cookie, name),
    // Read per request, so a language switch applies to the very next call.
    locale: () => nuxtApp.$i18n.locale.value,

    async onUnauthenticated() {
      useAuthStore().clear()
      const route = router.currentRoute.value

      // Already on login/register: nothing to redirect to.
      if (route.meta.layout !== 'guest') {
        await navigateTo({ path: '/login', query: { redirect: route.fullPath } })
      }
    },

    onError(error) {
      const t = nuxtApp.$i18n.t
      const key = errorKey(error)

      toast.add({
        title: t(key),
        // Domain errors (e.g. 409 "category in use") carry a useful server message.
        description: key === 'errors.generic' ? error.message : undefined,
        color: 'error',
        icon: 'i-lucide-circle-alert'
      })
    }
  })

  return { provide: { api } }
})
