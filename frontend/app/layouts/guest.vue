<script setup lang="ts">
/**
 * Login and register: a centered card, no navigation.
 */
</script>

<template>
  <div class="flex min-h-dvh flex-col">
    <header class="flex items-center justify-between px-4 py-3">
      <span class="font-semibold text-highlighted">{{ $t('app.name') }}</span>

      <div class="flex items-center gap-1">
        <LocaleSwitcher />
        <UColorModeButton />
      </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-8">
      <div class="w-full max-w-sm">
        <slot />
      </div>
    </main>
  </div>
</template>
