/**
 * The wedding named in the URL (/weddings/:weddingId/...). Selects it in the store (header
 * selector, base currency for MoneyInput) and re-reads it from the API; a wedding that is
 * not the user's, or no longer exists, is a 404 there and sends the user back to the list.
 *
 * Call once per page tree: in the top page of a section (the dashboard, the settings parent).
 */
export function useCurrentWedding() {
  const route = useRoute()
  const weddings = useWeddingsStore()

  const weddingId = computed(() => Number(route.params.weddingId))
  const wedding = computed(() => weddings.current)

  watch(weddingId, async (id, _previous, onCleanup) => {
    let stale = false
    onCleanup(() => {
      stale = true
    })

    if (!Number.isInteger(id)) {
      return
    }

    weddings.select(id)

    try {
      await weddings.fetchOne(id)
    } catch {
      // 404 was toasted by useApi.
      if (!stale) {
        weddings.select(null)
        await navigateTo('/weddings')
      }
    }
  }, { immediate: true })

  return { weddingId, wedding }
}
