import type { CurrencyCode, PaginatedResponse, ResourceResponse, Wedding, WeddingStatus } from '~/types/api'

export interface WeddingInput {
  name: string
  wedding_date?: string | null
  status?: WeddingStatus
  base_currency?: CurrencyCode
  /** Decimal string, e.g. "150000000" or "4500.50". */
  budget_target?: string
  notes?: string | null
}

/**
 * The user's weddings and the one being viewed. The current wedding comes from the URL
 * (/weddings/:weddingId), so a link or reload always opens the right one.
 */
export const useWeddingsStore = defineStore('weddings', () => {
  const api = useApi()
  const weddings = ref<Wedding[]>([])
  const loaded = ref(false)
  const currentId = ref<number | null>(null)

  const current = computed(() => weddings.value.find(wedding => wedding.id === currentId.value) ?? null)
  const baseCurrency = computed<CurrencyCode>(() => current.value?.base_currency ?? DEFAULT_CURRENCY)

  // The layout and the list page both ask on first load; they share one request.
  let fetching: Promise<void> | null = null

  /** All pages: the selector needs every wedding, and a user has only a handful. */
  function fetchAll(): Promise<void> {
    fetching ??= (async () => {
      const all: Wedding[] = []
      let page = 1
      let lastPage: number

      do {
        const response = await api.get<PaginatedResponse<Wedding>>('/weddings', { query: { page } })
        all.push(...response.data)
        lastPage = response.meta.last_page
        page++
      } while (page <= lastPage)

      weddings.value = all
      loaded.value = true
    })().finally(() => {
      fetching = null
    })

    return fetching
  }

  async function ensureLoaded(): Promise<void> {
    if (!loaded.value) {
      await fetchAll()
    }
  }

  async function create(input: WeddingInput): Promise<Wedding> {
    const response = await api.post<ResourceResponse<Wedding>>('/weddings', input)
    // Re-read the list so it keeps the API's order (next wedding date first).
    await fetchAll()

    return response.data
  }

  function select(id: number | null): void {
    currentId.value = id
  }

  function $reset(): void {
    weddings.value = []
    loaded.value = false
    currentId.value = null
  }

  return { weddings, loaded, currentId, current, baseCurrency, fetchAll, ensureLoaded, create, select, $reset }
})
