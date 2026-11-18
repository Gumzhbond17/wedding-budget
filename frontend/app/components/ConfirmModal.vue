<script setup lang="ts">
/**
 * Confirmation for destructive actions. The parent runs the action on "confirm" and passes
 * an error to show inside the dialog (e.g. "category in use"), so the user reads why it
 * failed right where they clicked, without the dialog closing on them.
 */
withDefaults(defineProps<{
  title: string
  description?: string
  confirmLabel: string
  loading?: boolean
  error?: string | null
}>(), {
  description: undefined,
  loading: false,
  error: null
})

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()
</script>

<template>
  <UModal
    v-model:open="open"
    :title="title"
    :description="description"
  >
    <template
      v-if="error"
      #body
    >
      <UAlert
        color="error"
        variant="subtle"
        icon="i-lucide-circle-alert"
        :description="error"
      />
    </template>

    <template #footer>
      <div class="flex w-full justify-end gap-2">
        <UButton
          :label="$t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          :label="confirmLabel"
          color="error"
          :loading="loading"
          :disabled="!!error"
          @click="emit('confirm')"
        />
      </div>
    </template>
  </UModal>
</template>
