<script setup lang="ts">
import type { OutstandingMoney } from '~/types/api'

/**
 * What is still to pay on an expense, in its currency. Until the invoice (actual amount)
 * is in, it is measured against the estimate and says so.
 */
defineProps<{
  outstanding: OutstandingMoney
}>()

const { format } = useCurrency()
</script>

<template>
  <span class="inline-flex flex-col items-end tabular-nums">
    <span class="text-highlighted">{{ format(outstanding) }}</span>
    <span
      v-if="outstanding.basis === 'estimated'"
      class="text-xs text-muted"
    >{{ $t('expenses.outstandingEstimated') }}</span>
  </span>
</template>
