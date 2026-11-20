<script setup lang="ts">
import type { ExpensePayment, ResourceResponse } from '~/types/api'

/**
 * Receipt of one payment: attach (with progress), open, replace, remove.
 *
 * Files are checked here first (type, 5 MB) so a wrong file fails at once instead of after
 * the upload; the API checks again by content. The file is fetched through a short-lived
 * signed URL: images open in a new tab, PDFs download (the API sends them as attachments,
 * never rendered on its origin).
 */
const props = defineProps<{
  weddingId: number
  expenseId: number
  payment: ExpensePayment
}>()

const emit = defineEmits<{ changed: [] }>()

/** Mirrors StoreReceiptRequest on the API. */
const MAX_BYTES = 5 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']

const { t } = useI18n()
const api = useApi()
const toast = useToast()

const input = useTemplateRef('input')
const progress = ref<number | null>(null)
const error = ref<string | null>(null)
const removeOpen = ref(false)
const removing = ref(false)

const url = computed(() => `/weddings/${props.weddingId}/expenses/${props.expenseId}/payments/${props.payment.id}/receipt`)
const isPdf = computed(() => props.payment.receipt?.mime_type === 'application/pdf')

function formatSize(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
}

function choose(): void {
  error.value = null
  input.value?.click()
}

async function onFile(event: Event): Promise<void> {
  const element = event.target as HTMLInputElement
  const file = element.files?.[0]
  // Reset, so choosing the same file again still fires "change".
  element.value = ''

  if (!file) {
    return
  }

  if (!TYPES.includes(file.type)) {
    error.value = t('receipts.wrongType')

    return
  }

  if (file.size > MAX_BYTES) {
    error.value = t('receipts.tooBig', { size: formatSize(file.size) })

    return
  }

  const body = new FormData()
  body.append('receipt', file)
  progress.value = 0

  try {
    await api.upload<ResourceResponse<ExpensePayment>>(url.value, body, (fraction) => {
      progress.value = Math.round(fraction * 100)
    })
    toast.add({ title: t('receipts.uploaded'), color: 'success', icon: 'i-lucide-check' })
    emit('changed')
  } catch (caught) {
    if (caught instanceof ApiValidationError) {
      error.value = caught.errors.receipt?.[0] ?? caught.message
    }
  } finally {
    progress.value = null
  }
}

async function openReceipt(): Promise<void> {
  // Opened before the request, while the click still counts as a user gesture; a window
  // opened after an await would be blocked as a pop-up.
  const tab = isPdf.value ? null : window.open('', '_blank')

  try {
    const { data } = await api.get<ResourceResponse<{ url: string, expires_at: string }>>(url.value)

    if (tab) {
      tab.opener = null
      tab.location.href = data.url
    } else {
      // Attachment response: the browser downloads it and stays on this page.
      const link = document.createElement('a')
      link.href = data.url
      link.rel = 'noopener'
      link.click()
    }
  } catch {
    tab?.close()
  }
}

async function remove(): Promise<void> {
  removing.value = true

  try {
    await api.delete(url.value)
    removeOpen.value = false
    toast.add({ title: t('receipts.removed'), color: 'success', icon: 'i-lucide-check' })
    emit('changed')
  } catch {
    // Toasted by useApi.
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <div class="space-y-1">
    <input
      ref="input"
      type="file"
      :accept="TYPES.join(',')"
      class="hidden"
      :aria-label="$t('receipts.choose')"
      data-testid="receipt-input"
      @change="onFile"
    >

    <div
      v-if="progress !== null"
      class="space-y-1"
    >
      <UProgress
        :model-value="progress"
        size="sm"
      />
      <p class="text-xs text-muted tabular-nums">
        {{ $t('receipts.uploading', { percent: progress }) }}
      </p>
    </div>

    <div
      v-else-if="payment.receipt"
      class="flex flex-wrap items-center gap-1"
    >
      <UButton
        :icon="isPdf ? 'i-lucide-download' : 'i-lucide-external-link'"
        :label="isPdf ? $t('receipts.download') : $t('receipts.open')"
        color="neutral"
        variant="soft"
        size="xs"
        :title="`${payment.receipt.original_name} · ${formatSize(payment.receipt.size)}`"
        @click="openReceipt"
      />
      <UButton
        icon="i-lucide-replace"
        color="neutral"
        variant="ghost"
        size="xs"
        :aria-label="$t('receipts.replace')"
        @click="choose"
      />
      <UButton
        icon="i-lucide-trash-2"
        color="error"
        variant="ghost"
        size="xs"
        :aria-label="$t('receipts.remove')"
        @click="removeOpen = true"
      />
    </div>

    <div v-else>
      <UButton
        icon="i-lucide-paperclip"
        :label="$t('receipts.attach')"
        color="neutral"
        variant="ghost"
        size="xs"
        @click="choose"
      />
      <p class="text-xs text-muted">
        {{ $t('receipts.limits') }}
      </p>
    </div>

    <p
      v-if="error"
      class="text-xs text-error"
      role="alert"
    >
      {{ error }}
    </p>

    <ConfirmModal
      v-model:open="removeOpen"
      :title="$t('receipts.removeTitle')"
      :description="payment.receipt?.original_name"
      :confirm-label="$t('receipts.remove')"
      :loading="removing"
      @confirm="remove"
    />
  </div>
</template>
