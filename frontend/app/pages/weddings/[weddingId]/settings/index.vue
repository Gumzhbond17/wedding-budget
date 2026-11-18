<script setup lang="ts">
// The settings section opens on categories.
definePageMeta({
  middleware: [to => navigateTo(`/weddings/${to.params.weddingId}/settings/categories`, { replace: true })]
})
</script>

<template>
  <div />
</template>
