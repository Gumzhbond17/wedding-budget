<script setup lang="ts">
import type { Category } from '~/types/api'
import type { ListFilters } from '~/composables/useListQuery'

/**
 * Category, status, currency and date-range filters of a record list. Values live in the
 * URL (useListQuery); this component only shows and changes them.
 */
const props = defineProps<{
  filters: ListFilters
  categories: Category[]
  statuses: readonly string[]
  /** Label of the date range, e.g. "Due date". */
  dateLabel: string
  hasFilters: boolean
}>()

const emit = defineEmits<{
  change: [key: keyof ListFilters, value: string | undefined]
  clear: []
}>()

const { t } = useI18n()

// Reka UI selects cannot hold an empty value, so "all" stands for "no filter".
const ALL = 'all'

const categoryItems = computed(() => [
  { value: ALL, label: t('filters.allCategories') },
  ...props.categories.map(category => ({ value: String(category.id), label: category.name }))
])
const statusItems = computed(() => [
  { value: ALL, label: t('filters.allStatuses') },
  ...props.statuses.map(status => ({ value: status, label: t(`status.${status}`) }))
])
const currencyItems = computed(() => [
  { value: ALL, label: t('filters.allCurrencies') },
  ...CURRENCY_CODES.map(code => ({ value: code, label: code }))
])

function change(key: keyof ListFilters, value: string | undefined): void {
  emit('change', key, value === ALL ? undefined : value)
}
</script>

<template>
  <div class="grid grid-cols-2 gap-2 md:grid-cols-[repeat(3,minmax(0,1fr))_auto_auto_auto] md:items-end">
    <USelect
      :model-value="filters.category_id ?? ALL"
      :items="categoryItems"
      :aria-label="$t('filters.category')"
      class="col-span-2 md:col-span-1"
      @update:model-value="change('category_id', $event)"
    />
    <USelect
      :model-value="filters.status ?? ALL"
      :items="statusItems"
      :aria-label="$t('filters.status')"
      @update:model-value="change('status', $event)"
    />
    <USelect
      :model-value="filters.currency ?? ALL"
      :items="currencyItems"
      :aria-label="$t('filters.currency')"
      @update:model-value="change('currency', $event)"
    />
    <UFormField
      :label="$t('filters.from', { field: dateLabel })"
      :ui="{ label: 'text-xs text-muted' }"
    >
      <DateInput
        :model-value="filters.date_from ?? ''"
        :max="filters.date_to"
        clearable
        class="md:w-44"
        @update:model-value="change('date_from', $event || undefined)"
      />
    </UFormField>
    <UFormField
      :label="$t('filters.to', { field: dateLabel })"
      :ui="{ label: 'text-xs text-muted' }"
    >
      <DateInput
        :model-value="filters.date_to ?? ''"
        :min="filters.date_from"
        clearable
        class="md:w-44"
        @update:model-value="change('date_to', $event || undefined)"
      />
    </UFormField>
    <UButton
      v-if="hasFilters"
      :label="$t('filters.clear')"
      icon="i-lucide-x"
      color="neutral"
      variant="ghost"
      class="col-span-2 justify-center md:col-span-1"
      @click="emit('clear')"
    />
  </div>
</template>
