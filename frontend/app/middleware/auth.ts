/**
 * Pages for logged-in users. The API is the source of truth: the first navigation asks
 * GET /auth/user, later ones reuse the answer until a 401 clears it.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()

  try {
    await auth.ensureLoaded()
  } catch {
    // API unreachable: already toasted; treat as logged out.
  }

  if (!auth.isLoggedIn) {
    return navigateTo({ path: '/login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } })
  }
})
