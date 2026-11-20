import type { LocationQueryValue } from 'vue-router'

export interface ListQueryOptions {
  /** API sort key used when the URL has none, e.g. "-created_at". */
  defaultSort: string
  /** Accepted sort keys (without "-"). */
  sortable: readonly string[]
  /** Accepted status values. */
  statuses: readonly string[]
}

export interface ListFilters {
  category_id?: string
  status?: string
  currency?: string
  date_from?: string
  date_to?: string
  sort: string
}

const DATE = /^\d{4}-\d{2}-\d{2}$/
const FILTER_KEYS = ['category_id', 'status', 'currency', 'date_from', 'date_to', 'sort'] as const

/**
 * Filters, sort and page of a list, kept in the URL (?status=received&page=2) so a reload,
 * the back button or a shared link shows the same list.
 *
 * Values are checked against the API's contract before use: a hand-edited URL with an
 * unknown status would otherwise make the list request fail with a 422 the page cannot show.
 */
export function useListQuery(options: ListQueryOptions) {
  const route = useRoute()
  const router = useRouter()

  function read(key: string): string | undefined {
    const value: LocationQueryValue | LocationQueryValue[] | undefined = route.query[key]

    return typeof value === 'string' && value !== '' ? value : undefined
  }

  const filters = computed<ListFilters>(() => {
    const sort = read('sort')
    const status = read('status')
    const currency = read('currency')
    const categoryId = read('category_id')
    const from = read('date_from')
    const to = read('date_to')

    return {
      category_id: categoryId && /^\d+$/.test(categoryId) ? categoryId : undefined,
      status: status && options.statuses.includes(status) ? status : undefined,
      currency: currency && (CURRENCY_CODES as string[]).includes(currency) ? currency : undefined,
      date_from: from && DATE.test(from) ? from : undefined,
      // The API requires date_to >= date_from.
      date_to: to && DATE.test(to) && (!from || !DATE.test(from) || to >= from) ? to : undefined,
      sort: sort && options.sortable.includes(sort.replace(/^-/, '')) ? sort : options.defaultSort
    }
  })

  const page = computed(() => {
    const value = Number(read('page'))

    return Number.isInteger(value) && value > 1 ? value : 1
  })

  /** Query for the API: only set filters, plus the page. */
  const apiQuery = computed(() => {
    const query: Record<string, string | number> = { page: page.value }

    for (const key of FILTER_KEYS) {
      const value = filters.value[key]
      if (value !== undefined) {
        query[key] = value
      }
    }

    return query
  })

  const hasFilters = computed(() => FILTER_KEYS.some(key => key !== 'sort' && filters.value[key] !== undefined))

  /** Any filter change starts again at page 1. */
  function setFilter(key: keyof ListFilters, value: string | undefined): void {
    router.replace({ query: { ...route.query, [key]: value || undefined, page: undefined } })
  }

  function setPage(value: number): void {
    router.replace({ query: { ...route.query, page: value > 1 ? String(value) : undefined } })
  }

  function clearFilters(): void {
    router.replace({ query: {} })
  }

  return { filters, page, apiQuery, hasFilters, setFilter, setPage, clearFilters }
}
