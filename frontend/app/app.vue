<script setup lang="ts">
import { en, lo } from '@nuxt/ui/locale'

const { locale, t } = useI18n()

// Nuxt UI's own strings (close buttons, empty states, color mode) follow the app language.
const uiLocale = computed(() => (locale.value === 'lo' ? lo : en))

useHead({
  htmlAttrs: {
    lang: () => uiLocale.value.code,
    dir: () => uiLocale.value.dir
  },
  titleTemplate: title => (title ? `${title} · ${t('app.name')}` : t('app.name')),
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1' }
  ],
  link: [
    { rel: 'icon', href: '/favicon.ico' }
  ]
})
</script>

<template>
  <!-- At most 3 toasts: on a phone a longer stack covers the content being worked on. -->
  <UApp
    :locale="uiLocale"
    :toaster="{ max: 3 }"
  >
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
