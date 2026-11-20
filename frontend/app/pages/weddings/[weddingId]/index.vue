<script setup lang="ts">
/**
 * Dashboard of one wedding. Every amount comes from GET /weddings/{id}/dashboard in the base
 * currency, already summed by the API; this page only formats and draws (no money math in JS).
 * A wedding without any revenue or expense gets getting-started guidance instead.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  // Only numeric ids reach the page; anything else is a 404 route.
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t, d } = useI18n()
const { weddingId, wedding } = useCurrentWedding()
const { label } = useCurrency()
const { dashboard, loading, failed, load } = useDashboard(weddingId)
const { exporting, exportCsv } = useCsvExport(weddingId)
const lookups = useWeddingLookups(weddingId)

useHead({ title: () => wedding.value?.name ?? t('dashboard.title') })

const active = computed(() => !!dashboard.value && hasActivity(dashboard.value))

// Rates only matter for the getting-started checklist.
watch([dashboard, active], ([value, isActive]) => {
  if (value && !isActive) {
    lookups.loadRates().catch(() => {
      // Toasted by useApi; the step just stays unticked.
    })
  }
})

const summary = computed(() => dashboard.value?.summary)
const overBudget = computed(() => dashboard.value?.categories.filter(category => category.health === 'red') ?? [])
const asOf = computed(() => (dashboard.value ? d(dateForDisplay(dashboard.value.as_of), 'long') : ''))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div v-if="wedding">
        <h1 class="text-2xl font-semibold text-highlighted">
          {{ wedding.name }}
        </h1>
        <p class="text-sm text-muted">
          {{ $t('dashboard.totalsIn', { currency: label(wedding.base_currency) }) }}
        </p>
      </div>
      <USkeleton
        v-else
        class="h-14 w-64"
      />

      <div
        v-if="active"
        class="flex flex-wrap gap-2"
      >
        <UButton
          icon="i-lucide-download"
          :label="$t('exports.revenues')"
          color="neutral"
          variant="outline"
          :loading="exporting === 'revenues'"
          :disabled="exporting !== null"
          @click="exportCsv('revenues')"
        />
        <UButton
          icon="i-lucide-download"
          :label="$t('exports.expenses')"
          color="neutral"
          variant="outline"
          :loading="exporting === 'expenses'"
          :disabled="exporting !== null"
          @click="exportCsv('expenses')"
        />
      </div>
    </div>

    <div
      v-if="loading && !dashboard"
      class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
    >
      <USkeleton
        v-for="index in 8"
        :key="index"
        class="h-32"
      />
    </div>

    <UCard v-else-if="failed && !dashboard">
      <UEmpty
        icon="i-lucide-cloud-off"
        :title="$t('dashboard.failed')"
        :actions="[{ label: $t('common.retry'), icon: 'i-lucide-refresh-cw', onClick: () => load() }]"
        variant="naked"
      />
    </UCard>

    <DashboardGettingStarted
      v-else-if="dashboard && !active"
      :wedding-id="weddingId"
      :has-rates="lookups.rates.value.length > 0"
      :has-category-budgets="!isZero(dashboard.summary.planned_total)"
    />

    <template v-else-if="dashboard && summary">
      <section
        :aria-label="$t('dashboard.summaryLabel')"
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
      >
        <SummaryCard
          :title="$t('dashboard.cards.budgetTarget')"
          icon="i-lucide-target"
          :money="summary.budget_target"
          :rows="[{ label: $t('dashboard.cards.plannedInCategories'), money: summary.planned_total }]"
        />

        <SummaryCard
          :title="$t('dashboard.cards.revenue')"
          icon="i-lucide-hand-coins"
          :money="summary.revenue.total"
          :rows="[
            { label: $t('status.received'), money: summary.revenue.received },
            { label: $t('status.expected'), money: summary.revenue.expected }
          ]"
        />

        <SummaryCard
          :title="$t('dashboard.cards.expenses')"
          icon="i-lucide-receipt"
          :rows="[
            { label: $t('expenses.fields.estimated'), money: summary.expenses.estimated },
            { label: $t('expenses.fields.actual'), money: summary.expenses.actual },
            { label: $t('expenses.fields.paid'), money: summary.expenses.paid }
          ]"
          :description="$t('dashboard.cards.expensesHelp')"
        />

        <SummaryCard
          :title="$t('dashboard.cards.remainingBudget')"
          icon="i-lucide-piggy-bank"
          :money="summary.remaining_budget"
          :tone="signOf(summary.remaining_budget.amount) < 0 ? 'negative' : 'default'"
          :description="$t('dashboard.cards.remainingBudgetHelp')"
        >
          <template
            v-if="signOf(summary.remaining_budget.amount) < 0"
            #status
          >
            <UBadge
              :label="$t('dashboard.cards.overTarget')"
              icon="i-lucide-octagon-alert"
              color="error"
              variant="subtle"
              class="self-start"
            />
          </template>
        </SummaryCard>

        <SummaryCard
          :title="$t('dashboard.cards.netCash')"
          icon="i-lucide-wallet"
          :money="summary.net_cash_position"
          :tone="signOf(summary.net_cash_position.amount) < 0 ? 'negative' : 'default'"
          :description="$t('dashboard.cards.netCashHelp')"
        >
          <template
            v-if="signOf(summary.net_cash_position.amount) < 0"
            #status
          >
            <UBadge
              :label="$t('dashboard.cards.paidMoreThanReceived')"
              icon="i-lucide-triangle-alert"
              color="error"
              variant="subtle"
              class="self-start"
            />
          </template>
        </SummaryCard>

        <GapCard
          :title="$t('dashboard.cards.fundingGap')"
          :description="$t('dashboard.cards.fundingGapHelp')"
          :gap="summary.funding_gap"
        />

        <GapCard
          :title="$t('dashboard.cards.securedGap')"
          :description="$t('dashboard.cards.securedGapHelp')"
          :gap="summary.secured_gap"
        />

        <SummaryCard
          :title="$t('dashboard.cards.unallocated')"
          icon="i-lucide-layers"
          :money="summary.unallocated_budget"
          :tone="signOf(summary.unallocated_budget.amount) < 0 ? 'negative' : 'default'"
          :description="signOf(summary.unallocated_budget.amount) < 0 ? $t('dashboard.cards.overAllocated') : $t('dashboard.cards.unallocatedHelp')"
        >
          <ULink
            :to="`/weddings/${weddingId}/settings/categories`"
            class="inline-flex items-center gap-1 text-sm font-medium text-primary"
          >
            {{ $t('dashboard.cards.setCategoryBudgets') }}
            <UIcon
              name="i-lucide-arrow-right"
              class="size-4"
            />
          </ULink>
        </SummaryCard>
      </section>

      <div class="grid gap-4 lg:grid-cols-2">
        <section class="space-y-4">
          <UCard>
            <template #header>
              <div class="space-y-1">
                <h2 class="font-semibold text-highlighted">
                  {{ $t('dashboard.upcoming.title') }}
                </h2>
                <p class="text-xs text-muted">
                  {{ $t('dashboard.upcoming.window', { date: asOf }) }}
                </p>
              </div>
            </template>

            <p
              v-if="!dashboard.upcoming_payments.length"
              class="text-sm text-muted"
            >
              {{ $t('dashboard.upcoming.empty') }}
            </p>

            <ul
              v-else
              class="-my-2 divide-y divide-default"
            >
              <li
                v-for="payment in dashboard.upcoming_payments"
                :key="payment.expense_id"
              >
                <ULink
                  :to="`/weddings/${weddingId}/expenses/${payment.expense_id}`"
                  class="flex items-start justify-between gap-3 py-2 hover:bg-elevated/50"
                >
                  <span class="min-w-0">
                    <span class="block font-medium text-highlighted">{{ payment.description }}</span>
                    <span class="block text-xs text-muted">
                      {{ [payment.category.name, payment.vendor_name].filter(Boolean).join(' · ') }}
                    </span>
                    <span class="mt-0.5 inline-flex items-center gap-1 text-xs text-muted">
                      <UIcon
                        name="i-lucide-calendar"
                        class="size-3.5"
                      />
                      {{ $t('dashboard.upcoming.due', { date: d(dateForDisplay(payment.due_date), 'short') }) }}
                    </span>
                  </span>
                  <OutstandingAmount :outstanding="payment.outstanding_amount" />
                </ULink>
              </li>
            </ul>
          </UCard>

          <UCard>
            <template #header>
              <h2 class="font-semibold text-highlighted">
                {{ $t('dashboard.overBudget.title') }}
              </h2>
            </template>

            <p
              v-if="!overBudget.length"
              class="inline-flex items-center gap-2 text-sm text-muted"
            >
              <UIcon
                name="i-lucide-circle-check"
                class="size-4 text-success"
              />
              {{ $t('dashboard.overBudget.empty') }}
            </p>

            <ul
              v-else
              class="-my-2 divide-y divide-default"
            >
              <li
                v-for="category in overBudget"
                :key="category.id"
              >
                <ULink
                  :to="{ path: `/weddings/${weddingId}/expenses`, query: { category_id: String(category.id) } }"
                  class="flex flex-wrap items-center justify-between gap-2 py-2 hover:bg-elevated/50"
                >
                  <span class="font-medium text-highlighted">{{ category.name }}</span>
                  <span class="flex flex-wrap items-center justify-end gap-2 text-sm">
                    <span class="tabular-nums text-muted">
                      {{ $t('dashboard.overBudget.amounts', { committed: formatMoneyObject(category.committed), planned: category.planned ? formatMoneyObject(category.planned) : '—' }) }}
                    </span>
                    <BudgetHealthBadge :health="category.health" />
                    <span class="tabular-nums font-medium text-error">{{ $t('dashboard.budget.used', { percent: category.used_percent }) }}</span>
                  </span>
                </ULink>
              </li>
            </ul>
          </UCard>

          <!-- Spending without a plan has nothing to be over: listed apart, never flagged red. -->
          <UCard v-if="dashboard.unplanned_spending.length">
            <template #header>
              <div class="space-y-1">
                <h2 class="font-semibold text-highlighted">
                  {{ $t('dashboard.unplanned.title') }}
                </h2>
                <p class="text-xs text-muted">
                  {{ $t('dashboard.unplanned.description') }}
                </p>
              </div>
            </template>

            <ul class="-my-2 divide-y divide-default">
              <li
                v-for="category in dashboard.unplanned_spending"
                :key="category.category_id"
              >
                <ULink
                  :to="{ path: `/weddings/${weddingId}/expenses`, query: { category_id: String(category.category_id) } }"
                  class="flex flex-wrap items-center justify-between gap-2 py-2 hover:bg-elevated/50"
                >
                  <span class="font-medium text-highlighted">{{ category.name }}</span>
                  <span class="flex items-center gap-2 text-sm">
                    <BudgetHealthBadge :health="null" />
                    <span class="tabular-nums font-medium text-highlighted">{{ formatMoneyObject(category.committed) }}</span>
                  </span>
                </ULink>
              </li>
            </ul>

            <template #footer>
              <ULink
                :to="`/weddings/${weddingId}/settings/categories`"
                class="inline-flex items-center gap-1 text-sm font-medium text-primary"
              >
                {{ $t('dashboard.cards.setCategoryBudgets') }}
                <UIcon
                  name="i-lucide-arrow-right"
                  class="size-4"
                />
              </ULink>
            </template>
          </UCard>
        </section>

        <ExpenseBreakdownChart :slices="dashboard.expense_breakdown" />
      </div>

      <BudgetVsActualChart
        :categories="dashboard.categories"
        :currency="dashboard.base_currency"
      />

      <CashFlowChart
        :months="dashboard.cash_flow"
        :currency="dashboard.base_currency"
      />

      <CurrencyBreakdown :rows="dashboard.per_currency" />
    </template>
  </div>
</template>
