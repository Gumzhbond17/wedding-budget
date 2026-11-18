<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'

/**
 * Settings of one wedding: categories and exchange rates as child pages. The wedding is
 * resolved here once for both.
 */
definePageMeta({
  layout: 'app',
  middleware: 'auth',
  validate: route => /^\d+$/.test(String(route.params.weddingId))
})

const { t } = useI18n()
const { weddingId, wedding } = useCurrentWedding()

useHead({ title: () => t('settings.title') })

const tabs = computed<NavigationMenuItem[]>(() => [
  { label: t('settings.categories'), icon: 'i-lucide-tags', to: `/weddings/${weddingId.value}/settings/categories` },
  { label: t('settings.exchangeRates'), icon: 'i-lucide-arrow-right-left', to: `/weddings/${weddingId.value}/settings/exchange-rates` }
])
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-highlighted">
        {{ $t('settings.title') }}
      </h1>
      <p
        v-if="wedding"
        class="text-sm text-muted"
      >
        {{ wedding.name }}
      </p>
    </div>

    <UNavigationMenu
      :items="tabs"
      variant="pill"
      class="-mx-2.5"
    />

    <!-- Always rendered: a conditional <NuxtPage> can miss the child route on a direct load.
         Children read the base currency from the store and update when the wedding arrives. -->
    <NuxtPage />
  </div>
</template>
