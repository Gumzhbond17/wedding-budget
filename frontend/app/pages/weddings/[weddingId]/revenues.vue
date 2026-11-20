<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { PaginatedResponse, Revenue } from '~/types/api'

/**
 * Revenue list: filters and page in the URL, a table from tablet width, cards on phones.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t, d } = useI18n()
const api = useApi()
const toast = useToast()
const { format } = useCurrency()
const { weddingId } = useCurrentWedding()
const lookups = useWeddingLookups(weddingId)

useHead({ title: () => t('revenues.title') })

const STATUSES = ['expected', 'received'] as const
const list = useListQuery({ defaultSort: '-created_at', sortable: ['base_amount', 'expected_date', 'received_date', 'created_at'], statuses: STATUSES })

const revenues = ref<Revenue[]>([])
const total = ref(0)
const perPage = ref(25)
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true

  try {
    const response = await api.get<PaginatedResponse<Revenue>>(`/weddings/${weddingId.value}/revenues`, { query: list.apiQuery.value })
    revenues.value = response.data
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

const revenueCategories = computed(() => lookups.categoriesOf('revenue'))

function transactionDate(revenue: Revenue): string {
  const date = revenue.received_date ?? revenue.expected_date

  return date ? d(dateForDisplay(date), 'short') : '—'
}

const columns = computed<TableColumn<Revenue>[]>(() => [
  { accessorKey: 'source', header: t('revenues.fields.source') },
  { id: 'category', header: t('fields.category') },
  { accessorKey: 'status', header: t('fields.status') },
  { id: 'date', header: t('fields.date') },
  { id: 'amount', header: () => h('span', { class: 'block text-end' }, t('fields.amount')) },
  { id: 'actions', header: () => h('span', { class: 'sr-only' }, t('common.actions')) }
])

// Dialogs
const formOpen = ref(false)
const editing = ref<Revenue | null>(null)
const receiveOpen = ref(false)
const receiving = ref<Revenue | null>(null)
const deleteOpen = ref(false)
const deleting = ref<Revenue | null>(null)
const deleteBusy = ref(false)

async function openForm(revenue: Revenue | null): Promise<void> {
  editing.value = revenue
  formOpen.value = true
  // Rates may have changed in Settings since the page opened.
  await lookups.loadRates().catch(() => {})
}

async function openReceive(revenue: Revenue): Promise<void> {
  receiving.value = revenue
  receiveOpen.value = true
  await lookups.loadRates().catch(() => {})
}

function askDelete(revenue: Revenue): void {
  deleting.value = revenue
  deleteOpen.value = true
}

async function onSaved(): Promise<void> {
  toast.add({ title: t('revenues.saved'), color: 'success', icon: 'i-lucide-check' })
  await load()
}

async function onReceived(revenue: Revenue): Promise<void> {
  // The rate was snapshotted again for the received date: say what was stored.
  toast.add({
    title: t('revenues.markReceived.done'),
    description: revenue.amount.currency === revenue.base_amount.currency
      ? format(revenue.amount)
      : t('revenues.markReceived.doneBase', {
          amount: format(revenue.base_amount),
          rate: t('rates.pair', { from: revenue.amount.currency, rate: formatRate(revenue.exchange_rate, revenue.base_amount.currency) })
        }),
    color: 'success',
    icon: 'i-lucide-check'
  })
  await load()
}

async function confirmDelete(): Promise<void> {
  if (!deleting.value) {
    return
  }

  deleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/revenues/${deleting.value.id}`)
    deleteOpen.value = false
    toast.add({ title: t('revenues.deleted'), color: 'success', icon: 'i-lucide-check' })
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
        {{ $t('revenues.title') }}
      </h1>
      <UButton
        icon="i-lucide-plus"
        :label="$t('revenues.add')"
        @click="openForm(null)"
      />
    </div>

    <RecordFilters
      :filters="list.filters.value"
      :categories="revenueCategories"
      :statuses="STATUSES"
      :date-label="$t('fields.date')"
      :has-filters="list.hasFilters.value"
      @change="list.setFilter"
      @clear="list.clearFilters"
    />

    <USkeleton
      v-if="loading && revenues.length === 0"
      class="h-48"
    />

    <UEmpty
      v-else-if="revenues.length === 0"
      icon="i-lucide-hand-coins"
      :title="list.hasFilters.value ? $t('filters.noMatch') : $t('revenues.empty')"
    />

    <template v-else>
      <!-- Tablet and desktop: table. -->
      <UTable
        :data="revenues"
        :columns="columns"
        :loading="loading"
        class="hidden rounded-lg border border-default md:block"
      >
        <template #source-cell="{ row }">
          <span class="font-medium text-highlighted">{{ row.original.source }}</span>
        </template>
        <template #category-cell="{ row }">
          {{ row.original.category?.name }}
        </template>
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #date-cell="{ row }">
          {{ transactionDate(row.original) }}
        </template>
        <template #amount-cell="{ row }">
          <div class="text-end">
            <MoneyDisplay
              :money="row.original.amount"
              :base="row.original.base_amount"
            />
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1">
            <UButton
              v-if="row.original.status === 'expected'"
              icon="i-lucide-check"
              color="success"
              variant="ghost"
              :aria-label="$t('revenues.markReceived.action', { source: row.original.source })"
              @click="openReceive(row.original)"
            />
            <UButton
              icon="i-lucide-pencil"
              color="neutral"
              variant="ghost"
              :aria-label="$t('common.editItem', { name: row.original.source })"
              @click="openForm(row.original)"
            />
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              :aria-label="$t('common.deleteItem', { name: row.original.source })"
              @click="askDelete(row.original)"
            />
          </div>
        </template>
      </UTable>

      <!-- Phones: cards. -->
      <ul class="space-y-3 md:hidden">
        <li
          v-for="revenue in revenues"
          :key="revenue.id"
        >
          <UCard :ui="{ body: 'space-y-3 p-4 sm:p-4' }">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-medium text-highlighted">
                  {{ revenue.source }}
                </p>
                <p class="text-sm text-muted">
                  {{ revenue.category?.name }} · {{ transactionDate(revenue) }}
                </p>
              </div>
              <StatusBadge :status="revenue.status" />
            </div>
            <div class="flex items-end justify-between gap-3">
              <MoneyDisplay
                :money="revenue.amount"
                :base="revenue.base_amount"
              />
              <div class="flex gap-1">
                <UButton
                  v-if="revenue.status === 'expected'"
                  icon="i-lucide-check"
                  color="success"
                  variant="soft"
                  :aria-label="$t('revenues.markReceived.action', { source: revenue.source })"
                  @click="openReceive(revenue)"
                />
                <UButton
                  icon="i-lucide-pencil"
                  color="neutral"
                  variant="ghost"
                  :aria-label="$t('common.editItem', { name: revenue.source })"
                  @click="openForm(revenue)"
                />
                <UButton
                  icon="i-lucide-trash-2"
                  color="error"
                  variant="ghost"
                  :aria-label="$t('common.deleteItem', { name: revenue.source })"
                  @click="askDelete(revenue)"
                />
              </div>
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

    <RevenueFormModal
      v-model:open="formOpen"
      :wedding-id="weddingId"
      :categories="revenueCategories"
      :rates="lookups.rates.value"
      :revenue="editing"
      @saved="onSaved"
    />

    <MarkReceivedModal
      v-model:open="receiveOpen"
      :wedding-id="weddingId"
      :revenue="receiving"
      :rates="lookups.rates.value"
      @saved="onReceived"
    />

    <ConfirmModal
      v-model:open="deleteOpen"
      :title="$t('revenues.deleteTitle', { source: deleting?.source ?? '' })"
      :description="$t('records.deleteDescription')"
      :confirm-label="$t('common.delete')"
      :loading="deleteBusy"
      @confirm="confirmDelete"
    />
  </div>
</template>
