<script setup lang="ts">
import type { CurrencyCode, DecimalString, ExchangeRate, IsoDate, RateString } from '~/types/api'

/**
 * Which rate a save will use and the base amounts it will produce, before saving. The API
 * repeats the same lookup and rounding and stays the source of truth; this only lets the
 * user see the result (and a missing rate) while filling in the form.
 */
const props = defineProps<{
  weddingId: number
  currency: CurrencyCode
  base: CurrencyCode
  /** Transaction date that picks the rate (payment date, received date, today...). */
  date: IsoDate
  rates: ExchangeRate[]
  /** The record's stored rate, when the save keeps it (currency and date unchanged). */
  snapshot?: RateString | null
  /** Amounts to convert, e.g. [{ label: 'Estimated', amount: '6500' }]. */
  amounts: Array<{ label: string, amount: DecimalString }>
}>()

const { t, d } = useI18n()
const { formatAmount } = useCurrency()

const lookup = computed(() => (props.snapshot ? null : rateOn(props.rates, props.currency, props.base, props.date)))
const rate = computed<RateString | null>(() => {
  if (props.snapshot) {
    return props.snapshot
  }

  return lookup.value && lookup.value !== 'base' ? lookup.value.rate : null
})

const conversions = computed(() => props.amounts
  .filter(item => /^\d+(\.\d+)?$/.test(item.amount) && rate.value !== null)
  .map(item => ({ label: item.label, value: formatAmount(convertToBase(item.amount, rate.value!), props.base) })))

const source = computed(() => {
  if (props.snapshot) {
    return t('rates.preview.saved')
  }

  return lookup.value && lookup.value !== 'base'
    ? t('rates.preview.from', { date: d(dateForDisplay(lookup.value.effective_date), 'long') })
    : ''
})
</script>

<template>
  <div v-if="currency !== base">
    <UAlert
      v-if="rate === null"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('rates.preview.missingTitle', { currency, date: d(dateForDisplay(date), 'long') })"
      :description="$t('rates.preview.missingDescription', { currency, base })"
      :actions="[{ label: $t('rates.addFor', { currency }), to: `/weddings/${weddingId}/settings/exchange-rates`, color: 'warning', variant: 'outline' }]"
    />

    <div
      v-else
      class="rounded-md bg-elevated/50 px-3 py-2 text-sm"
      data-testid="rate-preview"
    >
      <p class="text-default">
        <span class="font-medium">{{ $t('rates.pair', { from: currency, rate: formatRate(rate, base) }) }}</span>
        <span class="text-muted"> · {{ source }}</span>
      </p>
      <p
        v-for="item in conversions"
        :key="item.label"
        class="text-muted tabular-nums"
      >
        {{ item.label }}: ≈ <span class="text-highlighted">{{ item.value }}</span>
      </p>
    </div>
  </div>
</template>
