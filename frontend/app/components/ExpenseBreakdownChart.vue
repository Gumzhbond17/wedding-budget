<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import type { Dashboard } from '~/types/api'

/**
 * Committed expenses per category (doughnut), largest first as the API sends them. The eight
 * largest take the categorical colours in fixed order and the rest share one neutral grey:
 * grouping them into one "Other" slice would need adding amounts here, which the dashboard
 * never does. The list beside the chart names every slice with its amount and share, so
 * identity never rests on colour.
 */
const props = defineProps<{
  slices: Dashboard['expense_breakdown']
}>()

const { t } = useI18n()
const { format } = useCurrency()
const palette = useChartTheme()

const colors = computed(() => props.slices.map((_slice, index) => palette.value.series[index] ?? palette.value.neutral))

const data = computed<ChartData<'doughnut'>>(() => ({
  labels: props.slices.map(slice => slice.name),
  datasets: [{
    data: props.slices.map(slice => chartValue(slice.committed.amount)),
    backgroundColor: colors.value,
    // A 2px surface-coloured gap between slices.
    borderColor: palette.value.surface,
    borderWidth: 2
  }]
}))

const options = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  cutout: '62%',
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (item: TooltipItem<'doughnut'>) => {
          const slice = props.slices[item.dataIndex]!

          return `${format(slice.committed)} · ${t('dashboard.breakdown.share', { percent: slice.share_percent })}`
        }
      }
    }
  }
}))
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ $t('dashboard.breakdown.title') }}
      </h2>
    </template>

    <UEmpty
      v-if="!slices.length"
      icon="i-lucide-chart-pie"
      :title="$t('dashboard.breakdown.empty')"
      variant="naked"
    />

    <div
      v-else
      class="grid items-center gap-4 sm:grid-cols-2"
    >
      <div class="relative mx-auto h-56 w-full max-w-64">
        <Doughnut
          :data="data"
          :options="options"
          :aria-label="$t('dashboard.breakdown.title')"
          role="img"
        />
      </div>

      <ul class="space-y-1.5 text-sm">
        <li
          v-for="(slice, index) in slices"
          :key="slice.category_id"
          class="flex items-center gap-2"
        >
          <span
            class="size-3 shrink-0 rounded-sm"
            :style="{ background: colors[index] }"
          />
          <span class="min-w-0 flex-1 truncate text-highlighted">{{ slice.name }}</span>
          <span class="tabular-nums text-muted">{{ $t('dashboard.breakdown.share', { percent: slice.share_percent }) }}</span>
          <span class="tabular-nums font-medium text-highlighted">{{ format(slice.committed) }}</span>
        </li>
      </ul>
    </div>
  </UCard>
</template>
