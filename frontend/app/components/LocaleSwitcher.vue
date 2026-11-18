<script setup lang="ts">
const { locale, locales, setLocale } = useI18n()

const items = computed(() => locales.value.map(item => ({ value: item.code, label: item.name ?? item.code })))
</script>

<template>
  <USelect
    :model-value="locale"
    :items="items"
    :aria-label="$t('nav.language')"
    icon="i-lucide-languages"
    variant="ghost"
    class="w-36"
    @update:model-value="setLocale($event)"
  />
</template>
