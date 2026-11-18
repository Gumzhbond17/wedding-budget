<script setup lang="ts">
import type { DropdownMenuItem, NavigationMenuItem } from '@nuxt/ui'

/**
 * Logged-in pages: header with the wedding selector, language, color mode and account menu.
 */
const auth = useAuthStore()
const weddings = useWeddingsStore()
const { t, locale, locales, setLocale } = useI18n()

onMounted(() => {
  weddings.ensureLoaded().catch(() => {
    // Already toasted by useApi.
  })
})

// Sections of the selected wedding.
const sectionItems = computed<NavigationMenuItem[]>(() => {
  const id = weddings.currentId

  return id === null
    ? []
    : [
        { label: t('nav.dashboard'), icon: 'i-lucide-layout-dashboard', to: `/weddings/${id}`, exact: true },
        { label: t('nav.settings'), icon: 'i-lucide-settings', to: `/weddings/${id}/settings` }
      ]
})

const weddingItems = computed(() => weddings.weddings.map(wedding => ({ value: wedding.id, label: wedding.name })))

async function selectWedding(id: number | undefined): Promise<void> {
  if (id !== undefined) {
    await navigateTo(`/weddings/${id}`)
  }
}

const accountItems = computed<DropdownMenuItem[][]>(() => [
  [{ label: auth.user?.email ?? '', type: 'label' }],
  [
    { label: t('nav.allWeddings'), icon: 'i-lucide-list', to: '/weddings' },
    // The header language select is hidden at phone width; this keeps it reachable.
    {
      label: t('nav.language'),
      icon: 'i-lucide-languages',
      children: locales.value.map(item => ({
        label: item.name ?? item.code,
        type: 'checkbox' as const,
        checked: locale.value === item.code,
        onSelect: () => setLocale(item.code)
      }))
    }
  ],
  [{ label: t('nav.logout'), icon: 'i-lucide-log-out', onSelect: logout }]
])

async function logout(): Promise<void> {
  try {
    await auth.logout()
  } finally {
    await navigateTo('/login')
  }
}
</script>

<template>
  <div class="flex min-h-dvh flex-col">
    <UHeader :toggle="false">
      <template #left>
        <NuxtLink
          to="/weddings"
          :aria-label="$t('app.name')"
          class="flex shrink-0 items-center gap-2 font-semibold text-highlighted"
        >
          <UIcon
            name="i-lucide-heart-handshake"
            class="size-6 text-primary"
          />
          <!-- Name hidden on phones: the wedding selector needs the room. -->
          <span class="hidden sm:inline">{{ $t('app.name') }}</span>
        </NuxtLink>
      </template>

      <template #right>
        <USelectMenu
          v-if="weddings.weddings.length > 0"
          :model-value="weddings.currentId ?? undefined"
          :items="weddingItems"
          value-key="value"
          :placeholder="$t('nav.selectWedding')"
          :aria-label="$t('nav.selectWedding')"
          :search-input="false"
          icon="i-lucide-heart"
          class="w-40 sm:w-56"
          @update:model-value="selectWedding"
        />

        <LocaleSwitcher class="hidden sm:flex" />
        <UColorModeButton />

        <UDropdownMenu :items="accountItems">
          <UButton
            icon="i-lucide-circle-user"
            color="neutral"
            variant="ghost"
            :aria-label="$t('nav.account')"
          />
        </UDropdownMenu>
      </template>
    </UHeader>

    <UMain>
      <UContainer class="py-6">
        <UNavigationMenu
          v-if="sectionItems.length > 0"
          :items="sectionItems"
          highlight
          class="-mx-2.5 mb-6 border-b border-default"
        />
        <slot />
      </UContainer>
    </UMain>
  </div>
</template>
