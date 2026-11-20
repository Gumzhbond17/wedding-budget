import type { Category, CategoryType, CollectionResponse, ExchangeRate, PaginatedResponse } from '~/types/api'

/**
 * Categories and exchange rates of a wedding, for record forms and filters: category
 * pickers, and the rate preview that shows which rate a save will use.
 */
export function useWeddingLookups(weddingId: Ref<number>) {
  const api = useApi()
  const categories = ref<Category[]>([])
  const rates = ref<ExchangeRate[]>([])

  async function loadCategories(): Promise<void> {
    categories.value = (await api.get<CollectionResponse<Category>>(`/weddings/${weddingId.value}/categories`)).data
  }

  async function loadRates(): Promise<void> {
    const all: ExchangeRate[] = []
    let page = 1
    let lastPage: number

    do {
      const response = await api.get<PaginatedResponse<ExchangeRate>>(`/weddings/${weddingId.value}/exchange-rates`, { query: { page } })
      all.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage)

    rates.value = all
  }

  function categoriesOf(type: CategoryType): Category[] {
    return categories.value.filter(category => category.type === type)
  }

  return { categories, rates, categoriesOf, loadCategories, loadRates }
}
