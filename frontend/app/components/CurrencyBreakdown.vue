<script setup lang="ts">
import type { Dashboard } from '~/types/api'

/**
 * Totals per original currency (LAK, USD, THB) with their snapshotted base equivalent, as the
 * API sends them: what was pledged and spent in each currency, not converted again.
 */
defineProps<{
  rows: Dashboard['per_currency']
}>()

const { label, symbol } = useCurrency()
</script>

<template>
  <UCard>
    <template #header>
      <h2 class="font-semibold text-highlighted">
        {{ $t('dashboard.perCurrency.title') }}
      </h2>
    </template>

    <div class="grid gap-4 md:grid-cols-3">
      <section
        v-for="row in rows"
        :key="row.currency"
        class="rounded-md border border-default p-3"
      >
        <h3 class="mb-2 text-sm font-medium text-highlighted">
          {{ symbol(row.currency) }} {{ row.currency }} · {{ label(row.currency) }}
        </h3>

        <dl class="space-y-2 text-sm">
          <div
            v-for="item in [
              { key: 'revenueReceived', pair: row.revenue.received },
              { key: 'revenueExpected', pair: row.revenue.expected },
              { key: 'expensesEstimated', pair: row.expenses.estimated },
              { key: 'expensesActual', pair: row.expenses.actual },
              { key: 'expensesPaid', pair: row.expenses.paid }
            ]"
            :key="item.key"
            class="flex items-start justify-between gap-3"
          >
            <dt class="text-muted">
              {{ $t(`dashboard.perCurrency.${item.key}`) }}
            </dt>
            <dd class="text-end">
              <MoneyDisplay
                :money="item.pair.amount"
                :base="item.pair.base"
                class="items-end"
              />
            </dd>
          </div>
        </dl>
      </section>
    </div>
  </UCard>
</template>
