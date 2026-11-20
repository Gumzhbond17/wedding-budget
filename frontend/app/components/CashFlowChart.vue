<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js'
import { Bar } from 'vue-chartjs'
import type { CurrencyCode, Dashboard } from '~/types/api'

/**
 * Money in (received revenue) vs money out (payments) per month, as grouped bars on one axis.
 * The tooltip adds the month's net and the running cash position, as sent by the API.
 */
const props = defineProps<{
  months: Dashboard['cash_flow']
  currency: CurrencyCode
}>()

const { t, d } = useI18n()
const { format } = useCurrency()
const palette = useChartTheme()

const labels = computed(() => props.months.map(month => d(dateForDisplay(`${month.month}-01`), { month: 'short', year: 'numeric' })))

const data = computed<ChartData<'bar'>>(() => ({
  labels: labels.value,
  datasets: [
    {
      label: t('dashboard.cashFlow.inflow'),
      data: props.months.map(month => chartValue(month.inflow.amount)),
      backgroundColor: palette.value.series[0],
      borderRadius: 4,
      borderSkipped: 'start'
    },
    {
      label: t('dashboard.cashFlow.outflow'),
      data: props.months.map(month => chartValue(month.outflow.amount)),
      backgroundColor: palette.value.series[1],
      borderRadius: 4,
      borderSkipped: 'start'
    }
  ]
}))

const options = computed<ChartOptions<'bar'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  interaction: { mode: 'index', intersect: false },
  datasets: { bar: { barPercentage: 0.85, categoryPercentage: 0.7 } },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: palette.value.text, maxRotation: 0, autoSkip: true }
    },
    y: {
      beginAtZero: true,
      grid: { color: palette.value.grid },
      border: { display: false },
      ticks: { color: palette.value.text, maxTicksLimit: 5, callback: value => compactMoney(Number(value), props.currency) }
    }
  },
  plugins: {
    legend: {
      position: 'top',
      align: 'end',
      labels: { color: palette.value.text, boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 2 }
    },
    tooltip: {
      callbacks: {
        label: (item: TooltipItem<'bar'>) => {
          const month = props.months[item.dataIndex]!

          return `${item.dataset.label}: ${format(item.datasetIndex === 0 ? month.inflow : month.outflow)}`
        },
        footer: (items: TooltipItem<'bar'>[]) => {
          const month = props.months[items[0]!.dataIndex]!

          return [
            `${t('dashboard.cashFlow.net')}: ${format(month.net)}`,
            `${t('dashboard.cashFlow.cumulative')}: ${format(month.cumulative)}`
          ]
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
        {{ $t('dashboard.cashFlow.title') }}
      </h2>
    </template>

    <UEmpty
      v-if="!months.length"
      icon="i-lucide-chart-column"
      :title="$t('dashboard.cashFlow.empty')"
      variant="naked"
    />

    <div
      v-else
      class="relative h-64"
    >
      <Bar
        :data="data"
        :options="options"
        :aria-label="$t('dashboard.cashFlow.title')"
        role="img"
      />
    </div>
  </UCard>
</template>
