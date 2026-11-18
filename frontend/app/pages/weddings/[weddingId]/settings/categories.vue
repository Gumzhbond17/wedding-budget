<script setup lang="ts">
import type { TabsItem } from '@nuxt/ui'
import type { Category, CategoryType, CollectionResponse, Dashboard, ResourceResponse } from '~/types/api'

/**
 * Revenue and expense categories, with planned budgets for expenses and the part of the
 * budget target not yet planned. The totals come from the dashboard endpoint, so the
 * frontend never adds money up itself.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { format } = useCurrency()

useHead({ title: () => t('settings.categories') })

const weddingId = computed(() => Number(route.params.weddingId))
const categories = ref<Category[]>([])
const summary = ref<Dashboard['summary'] | null>(null)
const loading = ref(true)

// The tab lives in the URL (?type=revenue), so a reload or a link keeps it.
const type = computed<CategoryType>({
  get: () => (route.query.type === 'revenue' ? 'revenue' : 'expense'),
  set: value => router.replace({ query: { ...route.query, type: value } })
})

const tabs = computed<TabsItem[]>(() => [
  { label: t('categories.expense'), value: 'expense', icon: 'i-lucide-receipt' },
  { label: t('categories.revenue'), value: 'revenue', icon: 'i-lucide-hand-coins' }
])

const visible = computed(() => categories.value.filter(category => category.type === type.value))

async function load(): Promise<void> {
  loading.value = true

  try {
    const [list, dashboard] = await Promise.all([
      api.get<CollectionResponse<Category>>(`/weddings/${weddingId.value}/categories`),
      api.get<ResourceResponse<Dashboard>>(`/weddings/${weddingId.value}/dashboard`)
    ])
    categories.value = list.data
    summary.value = dashboard.data.summary
  } catch {
    // Toasted by useApi.
  } finally {
    loading.value = false
  }
}

watch(weddingId, load, { immediate: true })

const unallocated = computed(() => summary.value?.unallocated_budget ?? null)
const overAllocated = computed(() => unallocated.value?.amount.startsWith('-') ?? false)
// Shown without the sign: the label already says "over-allocated by".
const unallocatedText = computed(() => unallocated.value
  ? format({ ...unallocated.value, amount: unallocated.value.amount.replace(/^-/, '') })
  : '')

// Create / edit
const formOpen = ref(false)
const editing = ref<Category | null>(null)

function openCreate(): void {
  editing.value = null
  formOpen.value = true
}

function openEdit(category: Category): void {
  editing.value = category
  formOpen.value = true
}

async function onSaved(): Promise<void> {
  toast.add({ title: t('categories.saved'), color: 'success', icon: 'i-lucide-check' })
  await load()
}

// Delete
const deleting = ref<Category | null>(null)
const deleteOpen = ref(false)
const deleteError = ref<string | null>(null)
const deleteBusy = ref(false)

function askDelete(category: Category): void {
  deleting.value = category
  deleteError.value = null
  deleteOpen.value = true
}

async function confirmDelete(): Promise<void> {
  if (!deleting.value) {
    return
  }

  deleteBusy.value = true

  try {
    // 409: still used by records. Shown in the dialog instead of a toast.
    await api.delete(`/weddings/${weddingId.value}/categories/${deleting.value.id}`, { expectedStatuses: [409] })
    deleteOpen.value = false
    toast.add({ title: t('categories.deleted'), color: 'success', icon: 'i-lucide-check' })
    await load()
  } catch (error) {
    if (error instanceof ApiError && error.status === 409) {
      deleteError.value = t('categories.inUse', { name: deleting.value.name })
    }
  } finally {
    deleteBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <UTabs
      v-model="type"
      :items="tabs"
      :content="false"
      class="w-full sm:w-auto"
    />

    <!-- Budget target vs planned budgets: expense categories only. -->
    <UCard
      v-if="type === 'expense' && summary"
      :ui="{ body: 'grid gap-4 sm:grid-cols-3' }"
    >
      <div>
        <p class="text-sm text-muted">
          {{ $t('weddings.budgetTarget') }}
        </p>
        <p class="text-lg font-semibold text-highlighted tabular-nums">
          {{ format(summary.budget_target) }}
        </p>
      </div>
      <div>
        <p class="text-sm text-muted">
          {{ $t('categories.plannedTotal') }}
        </p>
        <p class="text-lg font-semibold text-highlighted tabular-nums">
          {{ format(summary.planned_total) }}
        </p>
      </div>
      <div>
        <p class="text-sm text-muted">
          {{ overAllocated ? $t('categories.overAllocated') : $t('categories.unallocated') }}
        </p>
        <p
          class="text-lg font-semibold tabular-nums"
          :class="overAllocated ? 'text-error' : 'text-success'"
        >
          {{ unallocatedText }}
        </p>
      </div>
    </UCard>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-muted">
        {{ type === 'expense' ? $t('categories.expenseHelp') : $t('categories.revenueHelp') }}
      </p>
      <UButton
        icon="i-lucide-plus"
        :label="$t('categories.add')"
        @click="openCreate"
      />
    </div>

    <USkeleton
      v-if="loading && categories.length === 0"
      class="h-40"
    />

    <UEmpty
      v-else-if="visible.length === 0"
      icon="i-lucide-tags"
      :title="$t('categories.empty')"
    />

    <UCard
      v-else
      :ui="{ body: 'p-0 sm:p-0' }"
    >
      <ul class="divide-y divide-default">
        <li
          v-for="category in visible"
          :key="category.id"
          class="flex items-center gap-3 px-4 py-3"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-highlighted">
              {{ category.name }}
            </p>
            <p
              v-if="type === 'expense'"
              class="text-sm text-muted tabular-nums"
            >
              {{ category.planned_amount ? format(category.planned_amount) : $t('categories.noPlan') }}
            </p>
          </div>

          <UButton
            icon="i-lucide-pencil"
            color="neutral"
            variant="ghost"
            :aria-label="$t('categories.edit', { name: category.name })"
            @click="openEdit(category)"
          />
          <UButton
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            :aria-label="$t('categories.delete', { name: category.name })"
            @click="askDelete(category)"
          />
        </li>
      </ul>
    </UCard>

    <CategoryFormModal
      v-model:open="formOpen"
      :wedding-id="weddingId"
      :type="type"
      :category="editing"
      @saved="onSaved"
    />

    <ConfirmModal
      v-model:open="deleteOpen"
      :title="$t('categories.deleteTitle', { name: deleting?.name ?? '' })"
      :description="$t('categories.deleteDescription')"
      :confirm-label="$t('common.delete')"
      :loading="deleteBusy"
      :error="deleteError"
      @confirm="confirmDelete"
    />
  </div>
</template>
