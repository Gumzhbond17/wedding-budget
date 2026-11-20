/**
 * CSV exports of a wedding's revenues or expenses. The file is fetched with the session
 * cookie (a plain link would lose the error toasts) and saved under the name the API gives:
 * "<wedding name>-revenues-<date>.csv", with Lao text kept.
 */
export function useCsvExport(weddingId: Ref<number>) {
  const api = useApi()
  const toast = useToast()
  const { t } = useI18n()
  const exporting = ref<'revenues' | 'expenses' | null>(null)

  async function exportCsv(type: 'revenues' | 'expenses'): Promise<void> {
    exporting.value = type

    try {
      const { blob, fileName } = await api.download(`/weddings/${weddingId.value}/exports/${type}`)
      saveBlob(blob, fileName ?? `wedding-${weddingId.value}-${type}-${todayInAppTimezone()}.csv`)
      toast.add({ title: t('exports.done'), color: 'success', icon: 'i-lucide-file-check' })
    } catch {
      // Toasted by useApi (e.g. 429 when exporting too often).
    } finally {
      exporting.value = null
    }
  }

  return { exporting, exportCsv }
}
