<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Category, CurrencyCode, Expense, ExchangeRate, ResourceResponse } from '~/types/api'

/**
 * Create or edit an expense. Only planned, booked and cancelled can be chosen: partially
 * paid and paid follow from payments. The rate is the one of the save day; an edit keeps
 * the stored rate unless the currency changes, and the currency is locked once payments
 * exist (docs/business-rules.md).
 */
const props = defineProps<{
  weddingId: number
  categories: Category[]
  rates: ExchangeRate[]
  expense: Expense | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [expense: Expense] }>()

const { t } = useI18n()
const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)

const USER_STATUSES = ['planned', 'booked', 'cancelled'] as const
type UserStatus = typeof USER_STATUSES[number]

interface ExpenseFormState {
  category_id: number | undefined
  description: string
  vendor_name: string
  currency: CurrencyCode
  estimated_amount: string
  actual_amount: string
  /** undefined: leave the status as it is (e.g. derived from payments). */
  status: UserStatus | undefined
  due_date: string
  notes: string
}

function blank(): ExpenseFormState {
  return {
    category_id: undefined,
    description: '',
    vendor_name: '',
    currency: base.value,
    estimated_amount: '',
    actual_amount: '',
    status: 'planned',
    due_date: '',
    notes: ''
  }
}

const state = reactive<ExpenseFormState>(blank())

const schema = computed(() => z.object({
  category_id: z.number({ error: t('validation.required') }),
  description: z.string().trim().min(1, t('validation.required')).max(255, t('validation.max', { max: 255 })),
  vendor_name: z.string().max(150, t('validation.max', { max: 150 })),
  currency: z.enum(['LAK', 'USD', 'THB']),
  estimated_amount: z.string().regex(amountPattern(state.currency), t('validation.money')),
  actual_amount: z.string().regex(amountPattern(state.currency), t('validation.money')).or(z.literal('')),
  status: z.enum(USER_STATUSES).optional(),
  due_date: z.string(),
  notes: z.string().max(5000, t('validation.max', { max: 5000 }))
}))

type Schema = z.output<typeof schema.value>

const form = useTemplateRef('form')
const submitting = ref(false)

const hasPayments = computed(() => !!props.expense && /[1-9]/.test(props.expense.paid_amount.amount))
const derivedStatus = computed(() => (props.expense && !(USER_STATUSES as readonly string[]).includes(props.expense.status) ? props.expense.status : null))

const categoryItems = computed(() => props.categories.map(category => ({ value: category.id, label: category.name })))
const statusItems = computed(() => USER_STATUSES.map(value => ({ value, label: t(`status.${value}`) })))

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  const expense = props.expense
  Object.assign(state, expense
    ? {
        category_id: expense.category_id,
        description: expense.description,
        vendor_name: expense.vendor_name ?? '',
        currency: expense.currency,
        estimated_amount: expense.estimated_amount.amount,
        actual_amount: expense.actual_amount?.amount ?? '',
        // A status derived from payments is not offered; it stays unless the user picks one.
        status: (USER_STATUSES as readonly string[]).includes(expense.status) ? expense.status as UserStatus : undefined,
        due_date: expense.due_date ?? '',
        notes: expense.notes ?? ''
      }
    : blank())
})

// Kept when editing without changing the currency; a new snapshot (today's rate) otherwise.
const snapshot = computed(() => (props.expense && props.expense.currency === state.currency ? props.expense.exchange_rate : null))

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true
  const data = event.data
  const payload: Record<string, unknown> = {
    category_id: data.category_id,
    description: data.description,
    vendor_name: data.vendor_name || null,
    estimated_amount: data.estimated_amount,
    actual_amount: data.actual_amount || null,
    due_date: data.due_date || null,
    notes: data.notes || null
  }

  // Currency only when it can change; status only when chosen and different.
  if (!hasPayments.value) {
    payload.currency = data.currency
  }
  if (data.status && data.status !== props.expense?.status) {
    payload.status = data.status
  }

  try {
    const { data: saved } = props.expense
      ? await api.patch<ResourceResponse<Expense>>(`/weddings/${props.weddingId}/expenses/${props.expense.id}`, payload)
      : await api.post<ResourceResponse<Expense>>(`/weddings/${props.weddingId}/expenses`, payload)

    open.value = false
    emit('saved', saved)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      // The currency picker is part of the estimated amount field.
      form.value?.setErrors(toFormErrors(error, { currency: 'estimated_amount' }))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="expense ? $t('expenses.form.editTitle') : $t('expenses.form.createTitle')"
  >
    <template #body>
      <UForm
        id="expense-form"
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
          :label="$t('expenses.fields.description')"
          name="description"
          required
        >
          <UInput
            v-model="state.description"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('expenses.fields.vendor')"
          name="vendor_name"
        >
          <UInput
            v-model="state.vendor_name"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('expenses.fields.estimated')"
          :help="hasPayments ? $t('expenses.form.currencyLocked') : undefined"
          name="estimated_amount"
          required
        >
          <MoneyInput
            v-model="state.estimated_amount"
            v-model:currency="state.currency"
            :currency-editable="!hasPayments"
          />
        </UFormField>

        <UFormField
          :label="$t('expenses.fields.actual')"
          :help="$t('expenses.form.actualHelp')"
          name="actual_amount"
        >
          <MoneyInput
            v-model="state.actual_amount"
            :currency="state.currency"
            :currency-editable="false"
          />
        </UFormField>

        <RatePreview
          :wedding-id="weddingId"
          :currency="state.currency"
          :base="base"
          :date="todayInAppTimezone()"
          :rates="rates"
          :snapshot="snapshot"
          :amounts="[
            { label: $t('expenses.fields.estimated'), amount: state.estimated_amount },
            { label: $t('expenses.fields.actual'), amount: state.actual_amount }
          ]"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField
            :label="$t('fields.status')"
            :help="derivedStatus ? $t('expenses.form.statusDerived', { status: $t(`status.${derivedStatus}`) }) : $t('expenses.form.statusHelp')"
            name="status"
          >
            <USelect
              v-model="state.status"
              :items="statusItems"
              :placeholder="derivedStatus ? $t(`status.${derivedStatus}`) : undefined"
              class="w-full"
            />
          </UFormField>

          <UFormField
            :label="$t('expenses.fields.dueDate')"
            name="due_date"
          >
            <DateInput
              v-model="state.due_date"
              clearable
            />
          </UFormField>
        </div>

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
          form="expense-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
