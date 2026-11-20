<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Bar } from 'vue-chartjs'
import type { CurrencyCode, Dashboard } from '~/types/api'

/**
 * Planned budget vs committed (actual, else estimate) per expense category, as horizontal
 * bars so long and Lao category names stay readable on a phone. The committed bar is
 * coloured by budget health; the list underneath repeats health as icon + text with the
 * exact amounts, so colour is never the only signal and every figure is readable as text.
 */
const props = defineProps<{
  categories: Dashboard['categories']
  currency: CurrencyCode
}>()

const { t } = useI18n()
const categoryName = useCategoryName()
const { format } = useCurrency()
const palette = useChartTheme()

/** Chart width (px) below which category labels are shortened. */
const NARROW_CHART = 480

// Categories with a plan or with something committed; untouched ones would only add empty rows.
const rows = computed(() => props.categories.filter(category => category.planned !== null || !isZero(category.committed)))

const data = computed<ChartData<'bar'>>(() => ({
  labels: rows.value.map(category => categoryName(category.name)),
  datasets: [
    {
      label: t('dashboard.budget.planned'),
      data: rows.value.map(category => (category.planned ? chartValue(category.planned.amount) : null)) as number[],
      backgroundColor: palette.value.planned,
      borderRadius: 4,
      borderSkipped: 'start'
    },
    {
      label: t('dashboard.budget.committed'),
      data: rows.value.map(category => chartValue(category.committed.amount)),
      backgroundColor: rows.value.map(category => (category.health ? palette.value.health[category.health] : palette.value.neutral)),
      borderRadius: 4,
      borderSkipped: 'start'
    }
  ]
}))

const options = computed<ChartOptions<'bar'>>(() => ({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  datasets: { bar: { barPercentage: 0.8, categoryPercentage: 0.7 } },
  scales: {
    x: {
      beginAtZero: true,
      grid: { color: palette.value.grid },
      border: { display: false },
      ticks: { color: palette.value.text, maxTicksLimit: 5, callback: value => compactMoney(Number(value), props.currency) }
    },
    y: {
      grid: { display: false },
      ticks: {
        color: palette.value.text,
        autoSkip: false,
        // On a phone-width chart long names would take half the width; the tooltip keeps them whole.
        callback(_value, index) {
          const name = rows.value[index]?.name ?? ''

          return this.chart.width < NARROW_CHART ? truncateLabel(name) : name
        }
      }
    }
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        title: (items: TooltipItem<'bar'>[]) => rows.value[items[0]!.dataIndex]!.name,
        // Exact amounts from the API strings, never the plotted numbers.
        label: (item: TooltipItem<'bar'>) => {
          const category = rows.value[item.dataIndex]!

          return item.datasetIndex === 0
            ? `${t('dashboard.budget.planned')}: ${category.planned ? format(category.planned) : t('dashboard.health.none')}`
            : `${t('dashboard.budget.committed')}: ${format(category.committed)}`
        },
        afterBody: (items: TooltipItem<'bar'>[]) => {
          const category = rows.value[items[0]!.dataIndex]!
          const health = t(`dashboard.health.${category.health ?? 'none'}`)

          return category.used_percent === null ? health : `${health} · ${t('dashboard.budget.used', { percent: category.used_percent })}`
        }
      }
    }
  }
}))

const height = computed(() => `${Math.max(160, rows.value.length * 44 + 40)}px`)
</script>

<template>
  <UCard>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold text-highlighted">
          {{ $t('dashboard.budget.title') }}
        </h2>
        <!-- Legend: the committed colour changes with health, so name the scheme. -->
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
          <span class="inline-flex items-center gap-1.5">
            <span
              class="size-3 rounded-sm"
              :style="{ background: palette.planned }"
            />
            {{ $t('dashboard.budget.planned') }}
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="flex">
              <span
                v-for="health in (['green', 'amber', 'red'] as const)"
                :key="health"
                class="size-3 first:rounded-s-sm last:rounded-e-sm"
                :style="{ background: palette.health[health] }"
              />
            </span>
            {{ $t('dashboard.budget.committedByHealth') }}
          </span>
        </div>
      </div>
    </template>

    <UEmpty
      v-if="!rows.length"
      icon="i-lucide-chart-bar"
      :title="$t('dashboard.budget.empty')"
      variant="naked"
    />

    <template v-else>
      <div
        class="relative"
        :style="{ height }"
      >
        <Bar
          :data="data"
          :options="options"
          :aria-label="$t('dashboard.budget.title')"
          role="img"
        />
      </div>

      <ul class="mt-4 divide-y divide-default text-sm">
        <li
          v-for="category in rows"
          :key="category.id"
          class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2"
        >
          <span class="font-medium text-highlighted">{{ categoryName(category.name) }}</span>
          <span class="flex flex-wrap items-center justify-end gap-2">
            <span class="text-muted tabular-nums">
              {{ format(category.committed) }} / {{ category.planned ? format(category.planned) : '—' }}
            </span>
            <span
              v-if="category.used_percent !== null"
              class="tabular-nums text-muted"
            >{{ $t('dashboard.budget.used', { percent: category.used_percent }) }}</span>
            <BudgetHealthBadge :health="category.health" />
          </span>
        </li>
      </ul>
    </template>
  </UCard>
</template>
