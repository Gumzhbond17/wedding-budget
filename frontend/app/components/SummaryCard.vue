<script setup lang="ts">
import type { Money } from '~/types/api'

/**
 * One dashboard figure: a title, the main amount (base currency) and optional detail rows.
 * tone colours the amount and is always accompanied by a status text (the `status` slot),
 * so the meaning does not depend on colour.
 */
withDefaults(defineProps<{
  title: string
  icon: string
  money?: Money | null
  description?: string
  tone?: 'default' | 'positive' | 'negative'
  rows?: Array<{ label: string, money: Money }>
}>(), {
  money: null,
  description: undefined,
  tone: 'default',
  rows: () => []
})

const { format } = useCurrency()

const TONES = {
  default: 'text-highlighted',
  positive: 'text-success',
  negative: 'text-error'
} as const
</script>

<template>
  <UCard :ui="{ body: 'p-4 sm:p-5 h-full flex flex-col gap-2' }">
    <div class="flex items-center gap-2 text-sm text-muted">
      <UIcon
        :name="icon"
        class="size-4 shrink-0"
      />
      <span>{{ title }}</span>
    </div>

    <p
      v-if="money"
      class="text-2xl font-semibold tabular-nums break-all"
      :class="TONES[tone]"
    >
      {{ format(money) }}
    </p>

    <slot name="status" />

    <dl
      v-if="rows.length"
      class="space-y-1 text-sm"
    >
      <div
        v-for="row in rows"
        :key="row.label"
        class="flex items-baseline justify-between gap-3"
      >
        <dt class="text-muted">
          {{ row.label }}
        </dt>
        <dd class="font-medium text-highlighted tabular-nums">
          {{ format(row.money) }}
        </dd>
      </div>
    </dl>

    <p
      v-if="description"
      class="mt-auto text-xs text-muted"
    >
      {{ description }}
    </p>

    <slot />
  </UCard>
</template>
