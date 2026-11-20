<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Category, CurrencyCode, ExchangeRate, PaymentMethod, ResourceResponse, Revenue, RevenueStatus } from '~/types/api'

/**
 * Create or edit a revenue. The rate is picked by the transaction date (received date,
 * else expected date, else today) and previewed before saving; an edit that keeps currency
 * and date keeps the stored rate (docs/business-rules.md, "Rate snapshots").
 */
const props = defineProps<{
  weddingId: number
  categories: Category[]
  rates: ExchangeRate[]
  revenue: Revenue | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [revenue: Revenue] }>()

const { t } = useI18n()
const categoryName = useCategoryName()
const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)

const NONE = 'none'

interface RevenueFormState {
  category_id: number | undefined
  source: string
  amount: string
  currency: CurrencyCode
  status: RevenueStatus
  expected_date: string
  received_date: string
  payment_method: PaymentMethod | typeof NONE
  notes: string
}

const state = reactive<RevenueFormState>(blank())

function blank(): RevenueFormState {
  return {
    category_id: undefined,
    source: '',
    amount: '',
    currency: base.value,
    status: 'expected',
    expected_date: '',
    received_date: '',
    payment_method: NONE,
    notes: ''
  }
}

const schema = computed(() => z.object({
  category_id: z.number({ error: t('validation.required') }),
  source: z.string().trim().min(1, t('validation.required')).max(150, t('validation.max', { max: 150 })),
  amount: z.string()
    .regex(amountPattern(state.currency), t('validation.money'))
    .refine(value => /[1-9]/.test(value), t('validation.positive')),
  currency: z.enum(['LAK', 'USD', 'THB']),
  status: z.enum(['expected', 'received']),
  expected_date: z.string(),
  received_date: z.string(),
  payment_method: z.string(),
  notes: z.string().max(5000, t('validation.max', { max: 5000 }))
}).refine(data => data.status !== 'received' || data.received_date !== '', {
  // Same text as the API (ManageRevenue::messages()).
  message: t('revenues.form.receivedDateRequired'),
  path: ['received_date']
}))

type Schema = z.output<typeof schema.value>

const form = useTemplateRef('form')
const submitting = ref(false)

const categoryItems = computed(() => props.categories.map(category => ({ value: category.id, label: categoryName(category.name) })))
const statusItems = computed(() => (['expected', 'received'] as const).map(value => ({ value, label: t(`status.${value}`) })))
const methodItems = computed(() => [
  { value: NONE, label: t('paymentMethod.none') },
  ...(['cash', 'bank_transfer', 'other'] as const).map(value => ({ value, label: t(`paymentMethod.${value}`) }))
])

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  const revenue = props.revenue
  Object.assign(state, revenue
    ? {
        category_id: revenue.category_id,
        source: revenue.source,
        amount: revenue.amount.amount,
        currency: revenue.amount.currency,
        status: revenue.status,
        expected_date: revenue.expected_date ?? '',
        received_date: revenue.received_date ?? '',
        payment_method: revenue.payment_method ?? NONE,
        notes: revenue.notes ?? ''
      }
    : blank())
})

// Choosing "received" suggests today as the received date.
watch(() => state.status, (status) => {
  if (status === 'received' && state.received_date === '') {
    state.received_date = todayInAppTimezone()
  }
})

/** Date that picks the rate, as the API does: received, else expected, else the save day. */
function transactionDate(status: RevenueStatus, received: string, expected: string, fallback: string): string {
  return (status === 'received' && received) || expected || fallback
}

const rateDate = computed(() => transactionDate(state.status, state.received_date, state.expected_date, todayInAppTimezone()))

// The stored rate is kept when neither the currency nor the transaction date changes.
const snapshot = computed(() => {
  const revenue = props.revenue
  if (!revenue || revenue.amount.currency !== state.currency) {
    return null
  }

  const savedDate = revenue.received_date ?? revenue.expected_date ?? revenue.created_at?.slice(0, 10) ?? ''
  const newDate = transactionDate(state.status, state.received_date, state.expected_date, revenue.created_at?.slice(0, 10) ?? '')

  return newDate === savedDate ? revenue.exchange_rate : null
})

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true
  const data = event.data
  const payload = {
    category_id: data.category_id,
    source: data.source,
    amount: data.amount,
    currency: data.currency,
    status: data.status,
    expected_date: data.expected_date || null,
    received_date: data.status === 'received' ? data.received_date : (data.received_date || null),
    payment_method: data.payment_method === NONE ? null : data.payment_method,
    notes: data.notes || null
  }

  try {
    const { data: saved } = props.revenue
      ? await api.patch<ResourceResponse<Revenue>>(`/weddings/${props.weddingId}/revenues/${props.revenue.id}`, payload)
      : await api.post<ResourceResponse<Revenue>>(`/weddings/${props.weddingId}/revenues`, payload)

    open.value = false
    emit('saved', saved)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      // The currency picker is part of the amount field (e.g. "no USD rate" lands there).
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
    :title="revenue ? $t('revenues.form.editTitle') : $t('revenues.form.createTitle')"
  >
    <template #body>
      <UForm
        id="revenue-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('fields.category')"
          name="category_id"
          required
        >
          <USelect
            v-model="state.category_id"
            :items="categoryItems"
            :placeholder="$t('fields.chooseCategory')"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('revenues.fields.source')"
          :hint="$t('revenues.form.sourceHint')"
          name="source"
          required
        >
          <UInput
            v-model="state.source"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('fields.amount')"
          name="amount"
          required
        >
          <MoneyInput
            v-model="state.amount"
            v-model:currency="state.currency"
          />
        </UFormField>

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField
            :label="$t('fields.status')"
            name="status"
          >
            <USelect
              v-model="state.status"
              :items="statusItems"
              class="w-full"
            />
          </UFormField>

          <UFormField
            v-if="state.status === 'received'"
            :label="$t('revenues.fields.receivedDate')"
            name="received_date"
            required
          >
            <DateInput
              v-model="state.received_date"
              clearable
            />
          </UFormField>

          <UFormField
            v-else
            :label="$t('revenues.fields.expectedDate')"
            name="expected_date"
          >
            <DateInput
              v-model="state.expected_date"
              clearable
            />
          </UFormField>
        </div>

        <RatePreview
          :wedding-id="weddingId"
          :currency="state.currency"
          :base="base"
          :date="rateDate"
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
          form="revenue-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
