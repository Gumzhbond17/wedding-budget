/**
 * Login and register: a logged-in user has nothing to do there.
 */
export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()

  try {
    await auth.ensureLoaded()
  } catch {
    // API unreachable: already toasted; show the form anyway.
  }

  if (auth.isLoggedIn) {
    return navigateTo('/weddings')
  }
})
