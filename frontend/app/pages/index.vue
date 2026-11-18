<script setup lang="ts">
// No landing page: the app starts at the wedding list (or login, via the auth middleware).
definePageMeta({
  middleware: ['auth', () => navigateTo('/weddings', { replace: true })]
})
</script>

<template>
  <div />
</template>
