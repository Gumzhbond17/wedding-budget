<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { CurrencyCode, ExchangeRate, ResourceResponse } from '~/types/api'

/**
 * Create or edit a manual rate: how much of the base currency one unit of a foreign
 * currency is worth from a given date. Records already saved keep their own snapshot.
 */
const props = defineProps<{
  weddingId: number
  base: CurrencyCode
  /** Currencies that need a rate into base. */
  foreign: CurrencyCode[]
  rate: ExchangeRate | null
  /** Preselected currency for a new rate (e.g. from the "missing rate" alert). */
  presetCurrency?: CurrencyCode | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [rate: ExchangeRate] }>()

const { t } = useI18n()
const api = useApi()
const { options } = useCurrency()

// Mirrors the API: DECIMAL(20,8), more than zero.
const RATE = /^\d{1,12}(\.\d{1,8})?$/

const schema = computed(() => z.object({
  from_currency: z.enum(['LAK', 'USD', 'THB']),
  rate: z.string().trim()
    .regex(RATE, t('rates.form.rateInvalid'))
    .refine(value => /[1-9]/.test(value), t('rates.form.rateInvalid')),
  effective_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, t('validation.required'))
}))

type Schema = z.output<typeof schema.value>

const state = reactive<Schema>({ from_currency: 'USD', rate: '', effective_date: '' })
const form = useTemplateRef('form')
const submitting = ref(false)

const currencyItems = computed(() => options.value.filter(option => props.foreign.includes(option.value)))

// "1 USD = ₭21.850" while typing, once the value is a valid rate.
const preview = computed(() => {
  const value = state.rate.trim()

  return RATE.test(value) && /[1-9]/.test(value)
    ? t('rates.pair', { from: state.from_currency, rate: formatRate(value, props.base) })
    : null
})

watch(open, (isOpen) => {
  if (isOpen) {
    state.from_currency = props.rate?.from_currency ?? props.presetCurrency ?? props.foreign[0] ?? 'USD'
    // Trailing zeros of the stored DECIMAL(20,8) are noise when editing:
    // "21850.00000000" → "21850", "628.40000000" → "628.4"; never touches integer zeros.
    state.rate = props.rate?.rate.replace(/\.0+$/, '').replace(/(\.\d*?[1-9])0+$/, '$1') ?? ''
    state.effective_date = props.rate?.effective_date ?? todayInAppTimezone()
  }
})

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true

  try {
    const { data } = props.rate
      ? await api.patch<ResourceResponse<ExchangeRate>>(`/weddings/${props.weddingId}/exchange-rates/${props.rate.id}`, event.data)
      : await api.post<ResourceResponse<ExchangeRate>>(`/weddings/${props.weddingId}/exchange-rates`, event.data)

    open.value = false
    emit('saved', data)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      form.value?.setErrors(toFormErrors(error))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="rate ? $t('rates.form.editTitle') : $t('rates.form.createTitle')"
    :description="$t('rates.form.description', { base })"
  >
    <template #body>
      <UForm
        id="rate-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('rates.form.currency')"
          name="from_currency"
          required
        >
          <USelect
            v-model="state.from_currency"
            :items="currencyItems"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('rates.form.rate', { from: state.from_currency, base })"
          :help="preview ?? $t('rates.form.rateHelp')"
          name="rate"
          required
        >
          <UInput
            v-model="state.rate"
            inputmode="decimal"
            autocomplete="off"
            :placeholder="base === 'LAK' ? '21850' : undefined"
            class="w-full"
            :ui="{ base: 'tabular-nums' }"
          >
            <template #trailing>
              <span class="text-sm text-muted">{{ base }}</span>
            </template>
          </UInput>
        </UFormField>

        <UFormField
          :label="$t('rates.form.effectiveDate')"
          :help="$t('rates.form.effectiveDateHelp')"
          name="effective_date"
          required
        >
          <DateInput v-model="state.effective_date" />
        </UFormField>
      </UForm>
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
          type="submit"
          form="rate-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
