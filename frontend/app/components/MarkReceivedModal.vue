<script setup lang="ts">
import type { ExchangeRate, ResourceResponse, Revenue } from '~/types/api'

/**
 * Marks an expected revenue as received on a date. The received date becomes the
 * transaction date, so the API takes a new rate snapshot; the preview shows it before
 * saving and the saved result is reported after (it can differ from the expected amount's
 * old base value).
 */
const props = defineProps<{
  weddingId: number
  revenue: Revenue | null
  rates: ExchangeRate[]
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [revenue: Revenue] }>()

const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)
const { format } = useCurrency()

const receivedDate = ref('')
const error = ref<string | null>(null)
const saving = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    receivedDate.value = todayInAppTimezone()
    error.value = null
  }
})

async function confirm(): Promise<void> {
  if (!props.revenue || receivedDate.value === '') {
    return
  }

  saving.value = true
  error.value = null

  try {
    const { data } = await api.patch<ResourceResponse<Revenue>>(
      `/weddings/${props.weddingId}/revenues/${props.revenue.id}`,
      { status: 'received', received_date: receivedDate.value }
    )
    open.value = false
    emit('saved', data)
  } catch (caught) {
    // A missing rate for that date comes back as a 422 on "currency": show it here.
    if (caught instanceof ApiValidationError) {
      error.value = Object.values(caught.errors).flat()[0] ?? caught.message
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="$t('revenues.markReceived.title')"
    :description="revenue ? `${revenue.source} · ${format(revenue.amount)}` : undefined"
  >
    <template #body>
      <div
        v-if="revenue"
        class="space-y-4"
      >
        <UFormField
          :label="$t('revenues.fields.receivedDate')"
          required
        >
          <DateInput v-model="receivedDate" />
        </UFormField>

        <RatePreview
          :wedding-id="weddingId"
          :currency="revenue.amount.currency"
          :base="base"
          :date="receivedDate || todayInAppTimezone()"
          :rates="rates"
          :amounts="[{ label: $t('rates.preview.baseAmount'), amount: revenue.amount.amount }]"
        />

        <UAlert
          v-if="error"
          color="error"
          variant="subtle"
          icon="i-lucide-circle-alert"
          :description="error"
        />
      </div>
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="$t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          :label="$t('revenues.markReceived.confirm')"
          icon="i-lucide-check"
          :loading="saving"
          :disabled="receivedDate === ''"
          @click="confirm"
        />
      </div>
    </template>
  </UModal>
</template>
