import type { ResourceResponse, User } from '~/types/api'

export interface LoginInput {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterInput {
  name: string
  email: string
  password: string
  password_confirmation: string
}

/**
 * The logged-in user. The session itself lives in Laravel's HttpOnly cookie, which this app
 * cannot read; the store only mirrors what GET /auth/user says about it.
 */
export const useAuthStore = defineStore('auth', () => {
  const api = useApi()
  const user = ref<User | null>(null)
  const loaded = ref(false)
  let loading: Promise<void> | null = null

  const isLoggedIn = computed(() => user.value !== null)

  async function fetchUser(): Promise<void> {
    try {
      const response = await api.get<ResourceResponse<User>>('/auth/user', { allowUnauthenticated: true })
      user.value = response.data
    } catch (error) {
      user.value = null

      // 401 simply means "not logged in". Anything else (API down) was already toasted.
      if (!(error instanceof ApiError) || error.status !== 401) {
        throw error
      }
    } finally {
      loaded.value = true
    }
  }

  /** Asks the API once per page load; later calls reuse the answer. */
  function ensureLoaded(): Promise<void> {
    if (loaded.value) {
      return Promise.resolve()
    }

    loading ??= fetchUser().finally(() => {
      loading = null
    })

    return loading
  }

  async function login(input: LoginInput): Promise<void> {
    const response = await api.post<ResourceResponse<User>>('/auth/login', input)
    user.value = response.data
    loaded.value = true
  }

  async function register(input: RegisterInput): Promise<void> {
    const response = await api.post<ResourceResponse<User>>('/auth/register', input)
    user.value = response.data
    loaded.value = true
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout', undefined, { allowUnauthenticated: true })
    } finally {
      clear()
    }
  }

  /** Forget everything tied to the previous session. */
  function clear(): void {
    user.value = null
    loaded.value = true
    useWeddingsStore().$reset()
  }

  return { user, loaded, isLoggedIn, ensureLoaded, login, register, logout, clear }
})
