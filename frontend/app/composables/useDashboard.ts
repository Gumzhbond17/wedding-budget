import type { Dashboard, ResourceResponse } from '~/types/api'

/**
 * The dashboard summary of one wedding (GET /weddings/{id}/dashboard). Every figure comes
 * from the API in the base currency; the page only formats and draws them.
 */
export function useDashboard(weddingId: Ref<number>) {
  const api = useApi()
  const dashboard = ref<Dashboard | null>(null)
  const loading = ref(true)
  const failed = ref(false)

  async function load(): Promise<void> {
    loading.value = true
    failed.value = false

    try {
      dashboard.value = (await api.get<ResourceResponse<Dashboard>>(`/weddings/${weddingId.value}/dashboard`)).data
    } catch {
      // Toasted by useApi; the page offers a retry.
      failed.value = true
    } finally {
      loading.value = false
    }
  }

  watch(weddingId, (id) => {
    if (Number.isInteger(id)) {
      dashboard.value = null
      load()
    }
  }, { immediate: true })

  return { dashboard, loading, failed, load }
}
