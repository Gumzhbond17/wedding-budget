<script setup lang="ts">
import type { Money } from '~/types/api'

/**
 * Funding or secured gap (committed expenses − revenue). The sign becomes words: positive is
 * "Still needed" (red), negative "Surplus" (green), each with an icon; the amount is shown
 * without its sign because the label already says which way it goes.
 */
const props = defineProps<{
  title: string
  description: string
  gap: Money
}>()

const state = computed(() => gapState(props.gap))

const META = {
  needed: { tone: 'negative', icon: 'i-lucide-trending-down', color: 'error' },
  surplus: { tone: 'positive', icon: 'i-lucide-trending-up', color: 'success' },
  balanced: { tone: 'default', icon: 'i-lucide-equal', color: 'neutral' }
} as const
</script>

<template>
  <SummaryCard
    :title="title"
    icon="i-lucide-scale"
    :money="absolute(gap)"
    :tone="META[state].tone"
    :description="description"
  >
    <template #status>
      <UBadge
        :label="$t(`dashboard.gap.${state}`)"
        :icon="META[state].icon"
        :color="META[state].color"
        variant="subtle"
        class="self-start"
      />
    </template>
  </SummaryCard>
</template>
