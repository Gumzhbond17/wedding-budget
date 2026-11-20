<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Expense, ExpensePayment, ExchangeRate, PaymentMethod, ResourceResponse } from '~/types/api'

/**
 * Record or correct a payment. Payments are always in the expense currency (shown, not
 * editable). The rate is the one effective on the payment date; correcting only the
 * amount keeps the stored rate.
 */
const props = defineProps<{
  weddingId: number
  expense: Expense
  rates: ExchangeRate[]
  payment: ExpensePayment | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [payment: ExpensePayment] }>()

const { t } = useI18n()
const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)

const NONE = 'none'

interface PaymentFormState {
  amount: string
  paid_date: string
  payment_method: PaymentMethod | typeof NONE
  notes: string
}

const state = reactive<PaymentFormState>({ amount: '', paid_date: '', payment_method: NONE, notes: '' })
const today = computed(() => todayInAppTimezone())

const schema = computed(() => z.object({
  amount: z.string()
    .regex(amountPattern(props.expense.currency), t('validation.money'))
    .refine(value => /[1-9]/.test(value), t('validation.positive')),
  paid_date: z.string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, t('validation.required'))
    .refine(value => value <= today.value, t('payments.form.notInFuture')),
  payment_method: z.string(),
  notes: z.string().max(5000, t('validation.max', { max: 5000 }))
}))

type Schema = z.output<typeof schema.value>

const form = useTemplateRef('form')
const submitting = ref(false)

const methodItems = computed(() => [
  { value: NONE, label: t('paymentMethod.none') },
  ...(['cash', 'bank_transfer', 'other'] as const).map(value => ({ value, label: t(`paymentMethod.${value}`) }))
])

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  const payment = props.payment
  Object.assign(state, payment
    ? { amount: payment.amount.amount, paid_date: payment.paid_date, payment_method: payment.payment_method ?? NONE, notes: payment.notes ?? '' }
    : { amount: '', paid_date: today.value, payment_method: NONE, notes: '' })
})

const snapshot = computed(() => (props.payment && props.payment.paid_date === state.paid_date ? props.payment.exchange_rate : null))

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true
  const payload = {
    amount: event.data.amount,
    paid_date: event.data.paid_date,
    payment_method: event.data.payment_method === NONE ? null : event.data.payment_method,
    notes: event.data.notes || null
  }
  const url = `/weddings/${props.weddingId}/expenses/${props.expense.id}/payments`

  try {
    const { data } = props.payment
      ? await api.patch<ResourceResponse<ExpensePayment>>(`${url}/${props.payment.id}`, payload)
      : await api.post<ResourceResponse<ExpensePayment>>(url, payload)

    open.value = false
    emit('saved', data)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      // No currency field (it is the expense's): currency errors, e.g. no rate for the
      // payment date, are shown on the amount.
      form.value?.setErrors(toFormErrors(error, { currency: 'amount' }))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="payment ? $t('payments.form.editTitle') : $t('payments.form.createTitle')"
    :description="expense.description"
  >
    <template #body>
      <UForm
        id="payment-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('fields.amount')"
          :help="$t('payments.form.currencyFixed', { currency: expense.currency })"
          name="amount"
          required
        >
          <MoneyInput
            v-model="state.amount"
            :currency="expense.currency"
            :currency-editable="false"
          />
        </UFormField>

        <UFormField
          :label="$t('payments.fields.paidDate')"
          name="paid_date"
          required
        >
          <UInput
            v-model="state.paid_date"
            type="date"
            :max="today"
            class="w-full"
          />
        </UFormField>

        <RatePreview
          :wedding-id="weddingId"
          :currency="expense.currency"
          :base="base"
          :date="state.paid_date || today"
          :rates="rates"
          :snapshot="snapshot"
          :amounts="[{ label: $t('rates.preview.baseAmount'), amount: state.amount }]"
        />

        <UFormField
          :label="$t('fields.paymentMethod')"
          name="payment_method"
        >
          <USelect
            v-model="state.payment_method"
            :items="methodItems"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('fields.notes')"
          name="notes"
        >
          <UTextarea
            v-model="state.notes"
            :rows="2"
            class="w-full"
          />
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
          form="payment-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
