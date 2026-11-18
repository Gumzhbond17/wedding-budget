<script setup lang="ts">
import type { ResourceResponse, Wedding } from '~/types/api'

/**
 * Dashboard shell for one wedding. Summary cards and charts arrive in deliverable 9;
 * this page already resolves the wedding from the URL and owns the wedding selector state.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  // Only numeric ids reach the page; anything else is a 404 route.
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t } = useI18n()
const route = useRoute()
const api = useApi()
const weddings = useWeddingsStore()
const { format, label } = useCurrency()

const weddingId = computed(() => Number(route.params.weddingId))
const wedding = ref<Wedding | null>(null)

useHead({ title: () => wedding.value?.name ?? t('dashboard.title') })

watch(weddingId, async (id) => {
  weddings.select(id)
  wedding.value = weddings.weddings.find(item => item.id === id) ?? null

  try {
    // Always re-read: the list may be stale, and a foreign or deleted id must 404 here.
    wedding.value = (await api.get<ResourceResponse<Wedding>>(`/weddings/${id}`)).data
  } catch {
    // 404 (not yours or deleted) was toasted; go back to the list.
    weddings.select(null)
    await navigateTo('/weddings')
  }
}, { immediate: true })
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
