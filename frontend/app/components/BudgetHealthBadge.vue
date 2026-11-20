<script setup lang="ts">
import type { BudgetHealth } from '~/types/api'

/**
 * Budget health of an expense category as icon + text, so it never relies on colour alone.
 * green < 90% of the planned budget, amber 90–100%, red > 100%; null = no budget set.
 */
const props = defineProps<{
  health: BudgetHealth | null
}>()

const META = {
  green: { color: 'success', icon: 'i-lucide-circle-check' },
  amber: { color: 'warning', icon: 'i-lucide-triangle-alert' },
  red: { color: 'error', icon: 'i-lucide-octagon-alert' }
} as const

const meta = computed(() => (props.health ? META[props.health] : { color: 'neutral', icon: 'i-lucide-circle-dashed' } as const))
</script>

<template>
  <UBadge
    :label="$t(`dashboard.health.${health ?? 'none'}`)"
    :color="meta.color"
    :icon="meta.icon"
    variant="subtle"
    size="sm"
  />
</template>
