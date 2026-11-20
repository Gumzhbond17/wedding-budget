<script setup lang="ts">
import type { Expense, ExpensePayment, ResourceResponse } from '~/types/api'

/**
 * One expense with its payments (in date order, as the API returns them). Status, paid and
 * outstanding are recalculated by the API after every payment change, so the page simply
 * reloads the expense.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  validate: route => /^\d+$/.test(String(route.params.weddingId)) && /^\d+$/.test(String(route.params.expenseId))
})

const { t, d } = useI18n()
const categoryName = useCategoryName()
const route = useRoute()
const api = useApi()
const toast = useToast()
const { formatAmount } = useCurrency()
const { weddingId } = useCurrentWedding()
const lookups = useWeddingLookups(weddingId)

const expenseId = computed(() => Number(route.params.expenseId))
const expense = ref<Expense | null>(null)
const payments = computed<ExpensePayment[]>(() => expense.value?.payments ?? [])
const backLink = computed(() => `/weddings/${weddingId.value}/expenses`)

useHead({ title: () => expense.value?.description ?? t('expenses.title') })

async function load(): Promise<void> {
  try {
    expense.value = (await api.get<ResourceResponse<Expense>>(`/weddings/${weddingId.value}/expenses/${expenseId.value}`)).data
  } catch {
    // 404 (deleted, or another wedding's) was toasted.
    await navigateTo(backLink.value)
  }
}

watch([weddingId, expenseId], load, { immediate: true })

onMounted(() => {
  Promise.all([lookups.loadCategories(), lookups.loadRates()]).catch(() => {})
})

function date(value: string | null): string {
  return value ? d(dateForDisplay(value), 'long') : '—'
}

// Expense dialogs
const editOpen = ref(false)
const deleteOpen = ref(false)
const deleteBusy = ref(false)

async function openEdit(): Promise<void> {
  editOpen.value = true
  await lookups.loadRates().catch(() => {})
}

async function onExpenseSaved(): Promise<void> {
  toast.add({ title: t('expenses.saved'), color: 'success', icon: 'i-lucide-check' })
  await load()
}

async function deleteExpense(): Promise<void> {
  deleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/expenses/${expenseId.value}`)
    deleteOpen.value = false
    toast.add({ title: t('expenses.deleted'), color: 'success', icon: 'i-lucide-check' })
    await navigateTo(backLink.value)
  } catch {
    // Toasted by useApi.
  } finally {
    deleteBusy.value = false
  }
}

// Payment dialogs
const paymentOpen = ref(false)
const editingPayment = ref<ExpensePayment | null>(null)
const paymentDeleteOpen = ref(false)
const deletingPayment = ref<ExpensePayment | null>(null)
const paymentDeleteBusy = ref(false)

async function openPayment(payment: ExpensePayment | null): Promise<void> {
  editingPayment.value = payment
  paymentOpen.value = true
  await lookups.loadRates().catch(() => {})
}

async function onPaymentSaved(): Promise<void> {
  toast.add({ title: t('payments.saved'), color: 'success', icon: 'i-lucide-check' })
  await load()
}

function askDeletePayment(payment: ExpensePayment): void {
  deletingPayment.value = payment
  paymentDeleteOpen.value = true
}

async function deletePayment(): Promise<void> {
  if (!deletingPayment.value) {
    return
  }

  paymentDeleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/expenses/${expenseId.value}/payments/${deletingPayment.value.id}`)
    paymentDeleteOpen.value = false
    toast.add({ title: t('payments.deleted'), color: 'success', icon: 'i-lucide-check' })
    await load()
  } catch {
    // Toasted by useApi.
  } finally {
    paymentDeleteBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <UButton
      :to="backLink"
      icon="i-lucide-arrow-left"
      :label="$t('expenses.back')"
      color="neutral"
      variant="link"
      class="-ms-2.5"
    />

    <USkeleton
      v-if="!expense"
      class="h-48"
    />

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <h1 class="text-2xl font-semibold break-words text-highlighted">
            {{ expense.description }}
          </h1>
          <p class="text-sm text-muted">
            {{ categoryName(expense.category?.name) }}<template v-if="expense.vendor_name">
              · {{ expense.vendor_name }}
            </template>
            · {{ $t('expenses.fields.dueDate') }}: {{ date(expense.due_date) }}
          </p>
          <div class="mt-2 flex flex-wrap gap-1">
            <StatusBadge :status="expense.status" />
            <UBadge
              v-if="expense.is_overpaid"
              :label="$t('expenses.overpaid')"
              color="warning"
              variant="solid"
              icon="i-lucide-triangle-alert"
            />
            <UBadge
              :label="expense.currency"
              color="neutral"
              variant="outline"
            />
          </div>
        </div>
        <div class="flex gap-1">
          <UButton
            icon="i-lucide-pencil"
            :label="$t('common.edit')"
            color="neutral"
            variant="outline"
            @click="openEdit"
          />
          <UButton
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            :aria-label="$t('common.deleteItem', { name: expense.description })"
            @click="deleteOpen = true"
          />
        </div>
      </div>

      <UCard :ui="{ body: 'grid grid-cols-2 gap-4 lg:grid-cols-4' }">
        <div>
          <p class="text-xs text-muted">
            {{ $t('expenses.fields.estimated') }}
          </p>
          <MoneyDisplay
            :money="expense.estimated_amount"
            :base="expense.base_estimated_amount"
          />
        </div>
        <div>
          <p class="text-xs text-muted">
            {{ $t('expenses.fields.actual') }}
          </p>
          <MoneyDisplay
            :money="expense.actual_amount"
            :base="expense.base_actual_amount"
            :empty="$t('expenses.noInvoice')"
          />
        </div>
        <div>
          <p class="text-xs text-muted">
            {{ $t('expenses.fields.paid') }}
          </p>
          <MoneyDisplay
            :money="expense.paid_amount"
            :base="expense.base_paid_amount"
          />
        </div>
        <div>
          <p class="text-xs text-muted">
            {{ $t('expenses.fields.outstanding') }}
          </p>
          <OutstandingAmount
            :outstanding="expense.outstanding_amount"
            class="items-start!"
          />
        </div>
      </UCard>

      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-highlighted">
            {{ $t('expenses.payments') }}
          </h2>
          <UButton
            icon="i-lucide-plus"
            :label="$t('payments.add')"
            @click="openPayment(null)"
          />
        </div>

        <UEmpty
          v-if="payments.length === 0"
          icon="i-lucide-wallet"
          :title="$t('payments.empty')"
          :description="$t('payments.emptyDescription')"
        />

        <UCard
          v-else
          :ui="{ body: 'p-0 sm:p-0' }"
        >
          <ul class="divide-y divide-default">
            <li
              v-for="payment in payments"
              :key="payment.id"
              class="grid gap-3 p-4 md:grid-cols-[10rem_1fr_auto_auto] md:items-center"
              data-testid="payment-row"
            >
              <div>
                <p class="font-medium text-highlighted">
                  {{ date(payment.paid_date) }}
                </p>
                <p
                  v-if="payment.payment_method"
                  class="text-xs text-muted"
                >
                  {{ $t(`paymentMethod.${payment.payment_method}`) }}
                </p>
              </div>

              <div>
                <MoneyDisplay
                  :money="payment.amount"
                  :base="payment.base_amount"
                />
                <p
                  v-if="payment.amount.currency !== payment.base_amount.currency"
                  class="text-xs text-muted"
                >
                  {{ $t('rates.pair', { from: payment.amount.currency, rate: formatRate(payment.exchange_rate, payment.base_amount.currency) }) }}
                </p>
                <p
                  v-if="payment.notes"
                  class="text-xs break-words text-muted"
                >
                  {{ payment.notes }}
                </p>
              </div>

              <ReceiptControl
                :wedding-id="weddingId"
                :expense-id="expense.id"
                :payment="payment"
                @changed="load"
              />

              <div class="flex justify-end gap-1">
                <UButton
                  icon="i-lucide-pencil"
                  color="neutral"
                  variant="ghost"
                  :aria-label="$t('payments.edit', { date: date(payment.paid_date) })"
                  @click="openPayment(payment)"
                />
                <UButton
                  icon="i-lucide-trash-2"
                  color="error"
                  variant="ghost"
                  :aria-label="$t('payments.delete', { date: date(payment.paid_date) })"
                  @click="askDeletePayment(payment)"
                />
              </div>
            </li>
          </ul>
        </UCard>
      </section>

      <ExpenseFormModal
        v-model:open="editOpen"
        :wedding-id="weddingId"
        :categories="lookups.categoriesOf('expense')"
        :rates="lookups.rates.value"
        :expense="expense"
        @saved="onExpenseSaved"
      />

      <PaymentFormModal
        v-model:open="paymentOpen"
        :wedding-id="weddingId"
        :expense="expense"
        :rates="lookups.rates.value"
        :payment="editingPayment"
        @saved="onPaymentSaved"
      />

      <ConfirmModal
        v-model:open="deleteOpen"
        :title="$t('expenses.deleteTitle', { description: expense.description })"
        :description="$t('records.deleteDescription')"
        :confirm-label="$t('common.delete')"
        :loading="deleteBusy"
        @confirm="deleteExpense"
      />

      <ConfirmModal
        v-model:open="paymentDeleteOpen"
        :title="$t('payments.deleteTitle')"
        :description="deletingPayment ? `${date(deletingPayment.paid_date)} · ${formatAmount(deletingPayment.amount.amount, deletingPayment.amount.currency)}` : undefined"
        :confirm-label="$t('common.delete')"
        :loading="paymentDeleteBusy"
        @confirm="deletePayment"
      />
    </template>
  </div>
</template>
