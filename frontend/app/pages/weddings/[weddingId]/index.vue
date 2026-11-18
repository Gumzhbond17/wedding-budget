<script setup lang="ts">
/**
 * Dashboard shell for one wedding. Summary cards and charts arrive in deliverable 9c.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  // Only numeric ids reach the page; anything else is a 404 route.
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t } = useI18n()
const { wedding } = useCurrentWedding()
const { format, label } = useCurrency()

useHead({ title: () => wedding.value?.name ?? t('dashboard.title') })
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="wedding"
      class="flex flex-wrap items-end justify-between gap-3"
    >
      <div>
        <h1 class="text-2xl font-semibold text-highlighted">
          {{ wedding.name }}
        </h1>
        <p class="text-sm text-muted">
          {{ $t('dashboard.totalsIn', { currency: label(wedding.base_currency) }) }}
        </p>
      </div>

      <div class="text-end">
        <p class="text-xs text-muted">
          {{ $t('weddings.budgetTarget') }}
        </p>
        <p class="text-lg font-semibold text-highlighted tabular-nums">
          {{ format(wedding.budget_target) }}
        </p>
      </div>
    </div>

    <USkeleton
      v-else
      class="h-14 w-64"
    />

    <UCard>
      <UEmpty
        icon="i-lucide-chart-no-axes-combined"
        :title="$t('dashboard.empty.title')"
        :description="$t('dashboard.empty.description')"
        variant="naked"
      />
    </UCard>
  </div>
</template>
