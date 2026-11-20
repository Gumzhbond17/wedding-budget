<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { GiftEnvelope, GiftEnvelopeSummary, GuestGroup, PaginatedResponse } from '~/types/api'

/**
 * Invitation card money, one row per guest. Amounts are in the base currency and count as
 * received revenue on the dashboard. Filters and page live in the URL like the other lists;
 * the totals per group cover the whole wedding, whatever the filters.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t, d } = useI18n()
const api = useApi()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { format } = useCurrency()
const { weddingId, wedding } = useCurrentWedding()
const { exporting, exportCsv } = useCsvExport(weddingId)

useHead({ title: () => t('giftEnvelopes.title') })

const DATE = /^\d{4}-\d{2}-\d{2}$/
const SORTS = ['-created_at', 'full_name', '-amount', '-received_date'] as const
const ALL = 'all'

function read(key: string): string | undefined {
  const value = route.query[key]

  return typeof value === 'string' && value !== '' ? value : undefined
}

// URL values are checked against the API's contract, so a hand-edited URL cannot make the
// list request fail with a 422 the page cannot show.
const filters = computed(() => {
  const group = read('guest_group')
  const from = read('date_from')
  const to = read('date_to')
  const sort = read('sort')

  return {
    search: read('search')?.slice(0, 150),
    guest_group: group && (GUEST_GROUPS as string[]).includes(group) ? group as GuestGroup : undefined,
    date_from: from && DATE.test(from) ? from : undefined,
    date_to: to && DATE.test(to) && (!from || !DATE.test(from) || to >= from) ? to : undefined,
    sort: sort && (SORTS as readonly string[]).includes(sort) ? sort as typeof SORTS[number] : SORTS[0]
  }
})

const page = computed(() => {
  const value = Number(read('page'))

  return Number.isInteger(value) && value > 1 ? value : 1
})

const hasFilters = computed(() => Boolean(filters.value.search || filters.value.guest_group || filters.value.date_from || filters.value.date_to))

const apiQuery = computed(() => {
  const query: Record<string, string | number> = { page: page.value, sort: filters.value.sort }

  for (const key of ['search', 'guest_group', 'date_from', 'date_to'] as const) {
    const value = filters.value[key]
    if (value !== undefined) {
      query[key] = value
    }
  }

  return query
})

/** Any filter change starts again at page 1. */
function setFilter(key: string, value: string | undefined): void {
  router.replace({ query: { ...route.query, [key]: value || undefined, page: undefined } })
}

function setPage(value: number): void {
  router.replace({ query: { ...route.query, page: value > 1 ? String(value) : undefined } })
}

// Search box: typed text updates the URL after a short pause, not on every key.
const searchText = ref(filters.value.search ?? '')
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(searchText, (text) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => setFilter('search', text.trim() || undefined), 300)
})

watch(() => filters.value.search, (search) => {
  if ((search ?? '') !== searchText.value.trim()) {
    searchText.value = search ?? ''
  }
})

onBeforeUnmount(() => clearTimeout(searchTimer))

const groupFilterItems = computed(() => [
  { value: ALL, label: t('giftEnvelopes.allGroups') },
  ...GUEST_GROUPS.map(value => ({ value, label: t(`guestGroup.${value}`) }))
])
const sortItems = computed(() => SORTS.map(value => ({ value, label: t(`giftEnvelopes.sort.${value.replace(/^-/, '')}`) })))

const envelopes = ref<GiftEnvelope[]>([])
const summary = ref<GiftEnvelopeSummary | null>(null)
const total = ref(0)
const perPage = ref(25)
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true

  try {
    const response = await api.get<PaginatedResponse<GiftEnvelope> & { meta: { summary: GiftEnvelopeSummary } }>(
      `/weddings/${weddingId.value}/gift-envelopes`,
      { query: apiQuery.value }
    )
    envelopes.value = response.data
    summary.value = response.meta.summary
    total.value = response.meta.total
    perPage.value = response.meta.per_page
  } catch {
    // Toasted by useApi.
  } finally {
    loading.value = false
  }
}

watch([weddingId, apiQuery], load, { immediate: true, deep: true })

const usedGroups = computed(() => summary.value?.groups.filter(group => group.count > 0) ?? [])

function receivedOn(envelope: GiftEnvelope): string {
  return d(dateForDisplay(envelope.received_date), 'short')
}

const columns = computed<TableColumn<GiftEnvelope>[]>(() => [
  { accessorKey: 'full_name', header: t('giftEnvelopes.fields.fullName') },
  { id: 'group', header: t('giftEnvelopes.fields.group') },
  { id: 'date', header: t('revenues.fields.receivedDate') },
  { accessorKey: 'notes', header: t('giftEnvelopes.fields.description') },
  { id: 'amount', header: () => h('span', { class: 'block text-end' }, t('fields.amount')) },
  { id: 'actions', header: () => h('span', { class: 'sr-only' }, t('common.actions')) }
])

// Dialogs
const formOpen = ref(false)
const editing = ref<GiftEnvelope | null>(null)
const deleteOpen = ref(false)
const deleting = ref<GiftEnvelope | null>(null)
const deleteBusy = ref(false)
/** The date of the last envelope saved, so a batch typed later keeps its day. */
const lastDate = ref<string | null>(null)
const defaultDate = computed(() => lastDate.value ?? wedding.value?.wedding_date ?? todayInAppTimezone())

function openForm(envelope: GiftEnvelope | null): void {
  editing.value = envelope
  formOpen.value = true
}

function askDelete(envelope: GiftEnvelope): void {
  deleting.value = envelope
  deleteOpen.value = true
}

async function onSaved(envelope: GiftEnvelope): Promise<void> {
  lastDate.value = envelope.received_date
  toast.add({ title: t('giftEnvelopes.saved', { name: envelope.full_name }), color: 'success', icon: 'i-lucide-check' })
  await load()
}

async function confirmDelete(): Promise<void> {
  if (!deleting.value) {
    return
  }

  deleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/gift-envelopes/${deleting.value.id}`)
    deleteOpen.value = false
    toast.add({ title: t('giftEnvelopes.deleted'), color: 'success', icon: 'i-lucide-check' })
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
      <div>
        <h1 class="text-2xl font-semibold text-highlighted">
          {{ $t('giftEnvelopes.title') }}
        </h1>
        <p class="text-sm text-muted">
          {{ $t('giftEnvelopes.help', { currency: wedding?.base_currency ?? '' }) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton
          icon="i-lucide-download"
          :label="$t('exports.giftEnvelopes')"
          color="neutral"
          variant="outline"
          :loading="exporting === 'gift-envelopes'"
          :disabled="exporting !== null || summary?.count === 0"
          @click="exportCsv('gift-envelopes')"
        />
        <UButton
          icon="i-lucide-plus"
          :label="$t('giftEnvelopes.add')"
          @click="openForm(null)"
        />
      </div>
    </div>

    <!-- Totals for the whole wedding: overall, then each group that has envelopes. -->
    <UCard
      v-if="summary && summary.count > 0"
      :ui="{ body: 'p-4 sm:p-4' }"
    >
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <p class="text-sm text-muted">
          {{ $t('giftEnvelopes.totalCount', { count: summary.count }, summary.count) }}
        </p>
        <p class="text-2xl font-semibold text-highlighted tabular-nums">
          {{ format(summary.total) }}
        </p>
      </div>
      <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <li
          v-for="group in usedGroups"
          :key="group.guest_group"
          class="flex items-baseline justify-between gap-3"
        >
          <span class="text-muted">
            {{ $t(`guestGroup.${group.guest_group}`) }} · {{ group.count }}
          </span>
          <span class="tabular-nums text-highlighted">{{ format(group.total) }}</span>
        </li>
      </ul>
    </UCard>

    <!-- Five equal columns in one row from desktop width, two per row on phones (search on
         its own). Date ranges use the placeholder instead of a label above, so every control
         has the same height. The clear button sits outside the grid so the columns stay equal. -->
    <div class="flex items-start gap-2">
      <div class="grid min-w-0 flex-1 grid-cols-2 gap-2 lg:grid-cols-5">
        <UInput
          v-model="searchText"
          icon="i-lucide-search"
          :placeholder="$t('giftEnvelopes.search')"
          :aria-label="$t('giftEnvelopes.search')"
          class="col-span-2 w-full lg:col-span-1"
        />
        <USelect
          :model-value="filters.guest_group ?? ALL"
          :items="groupFilterItems"
          :aria-label="$t('giftEnvelopes.fields.group')"
          class="w-full"
          @update:model-value="setFilter('guest_group', $event === ALL ? undefined : $event)"
        />
        <USelect
          :model-value="filters.sort"
          :items="sortItems"
          :aria-label="$t('giftEnvelopes.sortLabel')"
          class="w-full"
          @update:model-value="setFilter('sort', $event === SORTS[0] ? undefined : $event)"
        />
        <DateInput
          :model-value="filters.date_from ?? ''"
          :max="filters.date_to"
          :placeholder="$t('filters.from', { field: $t('revenues.fields.receivedDate') })"
          :aria-label="$t('filters.from', { field: $t('revenues.fields.receivedDate') })"
          clearable
          @update:model-value="setFilter('date_from', $event || undefined)"
        />
        <DateInput
          :model-value="filters.date_to ?? ''"
          :min="filters.date_from"
          :placeholder="$t('filters.to', { field: $t('revenues.fields.receivedDate') })"
          :aria-label="$t('filters.to', { field: $t('revenues.fields.receivedDate') })"
          clearable
          @update:model-value="setFilter('date_to', $event || undefined)"
        />
      </div>
      <UButton
        v-if="hasFilters"
        icon="i-lucide-x"
        color="neutral"
        variant="ghost"
        :aria-label="$t('filters.clear')"
        :title="$t('filters.clear')"
        class="shrink-0"
        @click="router.replace({ query: {} })"
      />
    </div>

    <USkeleton
      v-if="loading && envelopes.length === 0"
      class="h-48"
    />

    <UEmpty
      v-else-if="envelopes.length === 0"
      icon="i-lucide-mail"
      :title="hasFilters ? $t('filters.noMatch') : $t('giftEnvelopes.empty')"
      :description="hasFilters ? undefined : $t('giftEnvelopes.emptyHelp')"
    />

    <template v-else>
      <!-- Tablet and desktop: table. -->
      <UTable
        :data="envelopes"
        :columns="columns"
        :loading="loading"
        class="hidden rounded-lg border border-default md:block"
      >
        <template #full_name-cell="{ row }">
          <span class="font-medium text-highlighted">{{ row.original.full_name }}</span>
        </template>
        <template #group-cell="{ row }">
          <UBadge
            :label="$t(`guestGroup.${row.original.guest_group}`)"
            color="neutral"
            variant="subtle"
          />
        </template>
        <template #date-cell="{ row }">
          {{ receivedOn(row.original) }}
        </template>
        <template #notes-cell="{ row }">
          <span class="line-clamp-2 max-w-64 whitespace-normal text-muted">{{ row.original.notes }}</span>
        </template>
        <template #amount-cell="{ row }">
          <div class="text-end font-medium tabular-nums text-highlighted">
            {{ format(row.original.amount) }}
          </div>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1">
            <UButton
              icon="i-lucide-pencil"
              color="neutral"
              variant="ghost"
              :aria-label="$t('common.editItem', { name: row.original.full_name })"
              @click="openForm(row.original)"
            />
            <UButton
              icon="i-lucide-trash-2"
              color="error"
              variant="ghost"
              :aria-label="$t('common.deleteItem', { name: row.original.full_name })"
              @click="askDelete(row.original)"
            />
          </div>
        </template>
      </UTable>

      <!-- Phones: cards. -->
      <ul class="space-y-3 md:hidden">
        <li
          v-for="envelope in envelopes"
          :key="envelope.id"
        >
          <UCard :ui="{ body: 'space-y-2 p-4 sm:p-4' }">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate font-medium text-highlighted">
                  {{ envelope.full_name }}
                </p>
                <p class="text-sm text-muted">
                  {{ $t(`guestGroup.${envelope.guest_group}`) }} · {{ receivedOn(envelope) }}
                </p>
              </div>
              <p class="shrink-0 font-medium tabular-nums text-highlighted">
                {{ format(envelope.amount) }}
              </p>
            </div>
            <p
              v-if="envelope.notes"
              class="text-sm text-muted"
            >
              {{ envelope.notes }}
            </p>
            <div class="flex justify-end gap-1">
              <UButton
                icon="i-lucide-pencil"
                color="neutral"
                variant="ghost"
                :aria-label="$t('common.editItem', { name: envelope.full_name })"
                @click="openForm(envelope)"
              />
              <UButton
                icon="i-lucide-trash-2"
                color="error"
                variant="ghost"
                :aria-label="$t('common.deleteItem', { name: envelope.full_name })"
                @click="askDelete(envelope)"
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
          :page="page"
          :total="total"
          :items-per-page="perPage"
          @update:page="setPage"
        />
      </div>
    </template>

    <GiftEnvelopeFormModal
      v-model:open="formOpen"
      :wedding-id="weddingId"
      :envelope="editing"
      :default-date="defaultDate"
      @saved="onSaved"
    />

    <ConfirmModal
      v-model:open="deleteOpen"
      :title="$t('giftEnvelopes.deleteTitle', { name: deleting?.full_name ?? '' })"
      :description="$t('giftEnvelopes.deleteDescription')"
      :confirm-label="$t('common.delete')"
      :loading="deleteBusy"
      @confirm="confirmDelete"
    />
  </div>
</template>
