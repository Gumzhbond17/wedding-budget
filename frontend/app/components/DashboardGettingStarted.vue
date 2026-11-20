<script setup lang="ts">
/**
 * Shown instead of the dashboard while a wedding has no revenue or expense: the next steps,
 * ticked off as they are done, rather than a page of zeros and empty charts.
 */
const props = defineProps<{
  weddingId: number
  hasRates: boolean
  hasCategoryBudgets: boolean
}>()

const steps = computed(() => [
  {
    key: 'rates',
    done: props.hasRates,
    icon: 'i-lucide-arrow-left-right',
    to: `/weddings/${props.weddingId}/settings/exchange-rates`
  },
  {
    key: 'budgets',
    done: props.hasCategoryBudgets,
    icon: 'i-lucide-tags',
    to: `/weddings/${props.weddingId}/settings/categories`
  },
  {
    key: 'expense',
    done: false,
    icon: 'i-lucide-receipt',
    to: `/weddings/${props.weddingId}/expenses`
  },
  {
    key: 'revenue',
    done: false,
    icon: 'i-lucide-hand-coins',
    to: `/weddings/${props.weddingId}/revenues`
  }
])
</script>

<template>
  <UCard>
    <template #header>
      <div class="space-y-1">
        <h2 class="font-semibold text-highlighted">
          {{ $t('dashboard.start.title') }}
        </h2>
        <p class="text-sm text-muted">
          {{ $t('dashboard.start.description') }}
        </p>
      </div>
    </template>

    <ol class="space-y-3">
      <li
        v-for="(step, index) in steps"
        :key="step.key"
        class="flex items-start gap-3"
      >
        <span
          class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
          :class="step.done ? 'bg-success/10 text-success' : 'bg-elevated text-muted'"
        >
          <UIcon
            v-if="step.done"
            name="i-lucide-check"
            class="size-4"
          />
          <span v-else>{{ index + 1 }}</span>
        </span>

        <div class="min-w-0 flex-1 space-y-1">
          <p
            class="font-medium"
            :class="step.done ? 'text-muted line-through' : 'text-highlighted'"
          >
            {{ $t(`dashboard.start.${step.key}.title`) }}
            <span
              v-if="step.done"
              class="sr-only"
            >({{ $t('dashboard.start.done') }})</span>
          </p>
          <p class="text-sm text-muted">
            {{ $t(`dashboard.start.${step.key}.description`) }}
          </p>
        </div>

        <UButton
          v-if="!step.done"
          :to="step.to"
          :icon="step.icon"
          :label="$t(`dashboard.start.${step.key}.action`)"
          color="neutral"
          variant="outline"
          size="sm"
          class="shrink-0"
        />
      </li>
    </ol>
  </UCard>
</template>
