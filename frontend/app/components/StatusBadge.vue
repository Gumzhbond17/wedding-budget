<script setup lang="ts">
import type { ExpenseStatus, RevenueStatus } from '~/types/api'

/**
 * Status of an expense or revenue as a colored badge. Colors carry meaning across the app:
 * green = done, amber = in progress, red = cancelled.
 */
const props = defineProps<{
  status: ExpenseStatus | RevenueStatus
}>()

const COLORS = {
  planned: 'neutral',
  booked: 'info',
  partially_paid: 'warning',
  paid: 'success',
  cancelled: 'error',
  expected: 'neutral',
  received: 'success'
} as const

const ICONS = {
  planned: 'i-lucide-circle-dashed',
  booked: 'i-lucide-calendar-check',
  partially_paid: 'i-lucide-circle-dot',
  paid: 'i-lucide-circle-check',
  cancelled: 'i-lucide-circle-x',
  expected: 'i-lucide-hourglass',
  received: 'i-lucide-circle-check'
} as const

const color = computed(() => COLORS[props.status])
const icon = computed(() => ICONS[props.status])
</script>

<template>
  <UBadge
    :label="$t(`status.${status}`)"
    :color="color"
    :icon="icon"
    variant="subtle"
  />
</template>
