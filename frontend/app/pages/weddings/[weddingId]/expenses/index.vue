<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Expense, PaginatedResponse } from '~/types/api'

/**
 * Expense list: filters and page in the URL, a table from tablet width, cards on phones.
 * Payments are managed on each expense's page.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t, d } = useI18n()
const api = useApi()
const toast = useToast()
const { weddingId } = useCurrentWedding()
const lookups = useWeddingLookups(weddingId)

useHead({ title: () => t('expenses.title') })

const STATUSES = ['planned', 'booked', 'partially_paid', 'paid', 'cancelled'] as const
const list = useListQuery({ defaultSort: '-created_at', sortable: ['base_estimated_amount', 'base_actual_amount', 'due_date', 'created_at'], statuses: STATUSES })

const expenses = ref<Expense[]>([])
const total = ref(0)
const perPage = ref(25)
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true

  try {
    const response = await api.get<PaginatedResponse<Expense>>(`/weddings/${weddingId.value}/expenses`, { query: list.apiQuery.value })
    expenses.value = response.data
    total.value = response.meta.total
    perPage.value = response.meta.per_page
  } catch {
    // Toasted by useApi.
  } finally {
    loading.value = false
  }
}

watch([weddingId, list.apiQuery], load, { immediate: true, deep: true })

onMounted(() => {
  Promise.all([lookups.loadCategories(), lookups.loadRates()]).catch(() => {
    // Toasted by useApi.
  })
})

const expenseCategories = computed(() => lookups.categoriesOf('expense'))

function dueDate(expense: Expense): string {
  return expense.due_date ? d(dateForDisplay(expense.due_date), 'short') : '—'
}

function detailLink(expense: Expense): string {
  return `/weddings/${weddingId.value}/expenses/${expense.id}`
}

const end = (label: string) => () => h('span', { class: 'block text-end' }, label)

const columns = computed<TableColumn<Expense>[]>(() => [
  { accessorKey: 'description', header: t('expenses.fields.description') },
  { id: 'category', header: t('fields.category') },
  { accessorKey: 'status', header: t('fields.status') },
  { id: 'due', header: t('expenses.fields.dueDate') },
  { id: 'estimated', header: end(t('expenses.fields.estimated')) },
  { id: 'actual', header: end(t('expenses.fields.actual')) },
  { id: 'paid', header: end(t('expenses.fields.paid')) },
  { id: 'outstanding', header: end(t('expenses.fields.outstanding')) },
  { id: 'actions', header: () => h('span', { class: 'sr-only' }, t('common.actions')) }
])

// Dialogs
const formOpen = ref(false)
const editing = ref<Expense | null>(null)
const deleteOpen = ref(false)
const deleting = ref<Expense | null>(null)
const deleteBusy = ref(false)

async function openForm(expense: Expense | null): Promise<void> {
  editing.value = expense
  formOpen.value = true
  await lookups.loadRates().catch(() => {})
}

function askDelete(expense: Expense): void {
  deleting.value = expense
  deleteOpen.value = true
}

async function onSaved(expense: Expense): Promise<void> {
  toast.add({ title: t('expenses.saved'), color: 'success', icon: 'i-lucide-check' })

  if (editing.value) {
    await load()
  } else {
    // A new expense usually gets its payments next.
    await navigateTo(detailLink(expense))
  }
}

async function confirmDelete(): Promise<void> {
  if (!deleting.value) {
    return
  }

  deleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/expenses/${deleting.value.id}`)
    deleteOpen.value = false
    toast.add({ title: t('expenses.deleted'), color: 'success', icon: 'i-lucide-check' })
    await load()
  } catch {
    // Toasted by useApi.
  } finally {
    deleteBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-highlighted">
        {{ $t('expenses.title') }}
      </h1>
      <UButton
        icon="i-lucide-plus"
        :label="$t('expenses.add')"
        @click="openForm(null)"
      />
    </div>

    <RecordFilters
      :filters="list.filters.value"
      :categories="expenseCategories"
      :statuses="STATUSES"
      :date-label="$t('expenses.fields.dueDate')"
      :has-filters="list.hasFilters.value"
      @change="list.setFilter"
      @clear="list.clearFilters"
    />

    <USkeleton
      v-if="loading && expenses.length === 0"
      class="h-48"
    />

    <UEmpty
      v-else-if="expenses.length === 0"
      icon="i-lucide-receipt"
      :title="list.hasFilters.value ? $t('filters.noMatch') : $t('expenses.empty')"
    />

    <template v-else>
      <UTable
        :data="expenses"
        :columns="columns"
        :loading="loading"
        class="hidden rounded-lg border border-default md:block"
      >
        <template #description-cell="{ row }">
          <NuxtLink
            :to="detailLink(row.original)"
            class="font-medium text-highlighted hover:underline"
          >
            {{ row.original.description }}
          </NuxtLink>
          <p
            v-if="row.original.vendor_name"
            class="text-xs text-muted"
          >
            {{ row.original.vendor_name }}
          </p>
        </template>
        <template #category-cell="{ row }">
          {{ row.original.category?.name }}
        </template>
        <template #status-cell="{ row }">
          <div class="flex flex-wrap gap-1">
            <StatusBadge :status="row.original.status" />
            <UBadge
              v-if="row.original.is_overpaid"
              :label="$t('expenses.overpaid')"
              color="warning"
              variant="solid"
              icon="i-lucide-triangle-alert"
            />
          </div>
        </template>
        <template #due-cell="{ row }">
          {{ dueDate(row.original) }}
        </template>
        <template #estimated-cell="{ row }">
          <div class="text-end">
            <MoneyDisplay
              :money="row.original.estimated_amount"
              :base="row.original.base_estimated_amount"
            />
          </div>
        </template>
        <template #actual-cell="{ row }">
          <div class="text-end">
            <MoneyDisplay
              :money="row.original.actual_amount"
              :base="row.original.base_actual_amount"
            />
          </div>
        </template>
        <template #paid-cell="{ row }">
          <div class="text-end">
            <MoneyDisplay
              :money="row.original.paid_amount"
              :base="row.original.base_paid_amount"
            />
          </div>
        </template>
        <template #outstanding-cell="{ row }">
          <div class="text-end">
            <OutstandingAmount :outstanding="row.original.outstanding_amount" />
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1">
            <UButton
              icon="i-lucide-pencil"
              color="neutral"
              variant="ghost"
              :aria-label="$t('common.editItem', { name: row.original.description })"
              @click="openForm(row.original)"
            />
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              :aria-label="$t('common.deleteItem', { name: row.original.description })"
              @click="askDelete(row.original)"
            />
          </div>
        </template>
      </UTable>

      <ul class="space-y-3 md:hidden">
        <li
          v-for="expense in expenses"
          :key="expense.id"
        >
          <UCard :ui="{ body: 'space-y-3 p-4 sm:p-4' }">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <NuxtLink
                  :to="detailLink(expense)"
                  class="block truncate font-medium text-highlighted"
                >
                  {{ expense.description }}
                </NuxtLink>
                <p class="text-sm text-muted">
                  {{ expense.category?.name }}<template v-if="expense.vendor_name">
                    · {{ expense.vendor_name }}
                  </template>
                </p>
              </div>
              <div class="flex shrink-0 flex-col items-end gap-1">
                <StatusBadge :status="expense.status" />
                <UBadge
                  v-if="expense.is_overpaid"
                  :label="$t('expenses.overpaid')"
                  color="warning"
                  variant="solid"
                  icon="i-lucide-triangle-alert"
                />
              </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
              <!-- Same rule as the outstanding label: the invoice once known, else the estimate. -->
              <div v-if="expense.actual_amount && expense.base_actual_amount">
                <dt class="text-xs text-muted">
                  {{ $t('expenses.fields.actual') }}
                </dt>
                <dd>
                  <MoneyDisplay
                    :money="expense.actual_amount"
                    :base="expense.base_actual_amount"
                  />
                </dd>
              </div>
              <div v-else>
                <dt class="text-xs text-muted">
                  {{ $t('expenses.fields.estimated') }}
                </dt>
                <dd>
                  <MoneyDisplay
                    :money="expense.estimated_amount"
                    :base="expense.base_estimated_amount"
                  />
                </dd>
              </div>
              <div class="text-end">
                <dt class="text-xs text-muted">
                  {{ $t('expenses.fields.outstanding') }}
                </dt>
                <dd>
                  <OutstandingAmount :outstanding="expense.outstanding_amount" />
                </dd>
              </div>
              <div>
                <dt class="text-xs text-muted">
                  {{ $t('expenses.fields.paid') }}
                </dt>
                <dd>
                  <MoneyDisplay :money="expense.paid_amount" />
                </dd>
              </div>
              <div class="text-end">
                <dt class="text-xs text-muted">
                  {{ $t('expenses.fields.dueDate') }}
                </dt>
                <dd>{{ dueDate(expense) }}</dd>
              </div>
            </dl>

            <div class="flex justify-end gap-1">
              <UButton
                :to="detailLink(expense)"
                :label="$t('expenses.payments')"
                icon="i-lucide-wallet"
                color="neutral"
                variant="soft"
                size="sm"
              />
              <UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="ghost"
                :aria-label="$t('common.editItem', { name: expense.description })"
                @click="openForm(expense)"
              />
              <UButton
                icon="i-lucide-trash-2"
                color="error"
                variant="ghost"
                :aria-label="$t('common.deleteItem', { name: expense.description })"
                @click="askDelete(expense)"
              />
            </div>
          </UCard>
        </li>
      </ul>

      <div
        v-if="total > perPage"
        class="flex justify-center"
      >
        <UPagination
          :page="list.page.value"
          :total="total"
          :items-per-page="perPage"
          @update:page="list.setPage"
        />
      </div>
    </template>

    <ExpenseFormModal
      v-model:open="formOpen"
      :wedding-id="weddingId"
      :categories="expenseCategories"
      :rates="lookups.rates.value"
      :expense="editing"
      @saved="onSaved"
    />

    <ConfirmModal
      v-model:open="deleteOpen"
      :title="$t('expenses.deleteTitle', { description: deleting?.description ?? '' })"
      :description="$t('records.deleteDescription')"
      :confirm-label="$t('common.delete')"
      :loading="deleteBusy"
      @confirm="confirmDelete"
    />
  </div>
</template>
