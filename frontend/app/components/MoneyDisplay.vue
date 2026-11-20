<script setup lang="ts">
import type { Money } from '~/types/api'

/**
 * An amount in its own currency, with the base-currency equivalent underneath when the
 * currencies differ ("$6,500.00" / "≈ ₭142.025.000").
 */
const props = defineProps<{
  money: Money | null
  base?: Money | null
  /** Shown when money is null, e.g. no actual amount yet. */
  empty?: string
}>()

const { format } = useCurrency()
const showBase = computed(() => !!props.money && !!props.base && props.base.currency !== props.money.currency)
</script>

<template>
  <span class="inline-flex flex-col tabular-nums">
    <span
      v-if="money"
      class="text-highlighted"
    >{{ format(money) }}</span>
    <span
      v-else
      class="text-muted"
    >{{ empty ?? '—' }}</span>
    <span
      v-if="showBase && base"
      class="text-xs text-muted"
    >≈ {{ format(base) }}</span>
  </span>
</template>
