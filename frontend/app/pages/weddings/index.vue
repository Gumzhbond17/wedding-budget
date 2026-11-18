<script setup lang="ts">
import type { Wedding } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })

const { t, d } = useI18n()
const weddings = useWeddingsStore()
const { format } = useCurrency()
const toast = useToast()

useHead({ title: () => t('weddings.title') })

const creating = ref(false)
const loading = ref(true)

onMounted(async () => {
  weddings.select(null)

  try {
    await weddings.fetchAll()
  } catch {
    // Already toasted by useApi.
  } finally {
    loading.value = false
  }
})

async function onCreated(wedding: Wedding): Promise<void> {
  toast.add({ title: t('weddings.created'), color: 'success', icon: 'i-lucide-check' })
  await navigateTo(`/weddings/${wedding.id}`)
}

function weddingDate(wedding: Wedding): string {
  // Date-only value: parse at noon so no time zone shifts it to the previous day.
  return wedding.wedding_date ? d(new Date(`${wedding.wedding_date}T12:00:00`), 'long') : t('weddings.noDate')
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-highlighted">
        {{ $t('weddings.title') }}
      </h1>
      <UButton
        icon="i-lucide-plus"
        :label="$t('weddings.create')"
        @click="creating = true"
      />
    </div>

    <div
      v-if="loading && weddings.weddings.length === 0"
      class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
    >
      <USkeleton
        v-for="n in 3"
        :key="n"
        class="h-32"
      />
    </div>

    <UEmpty
      v-else-if="weddings.weddings.length === 0"
      icon="i-lucide-heart"
      :title="$t('weddings.empty.title')"
      :description="$t('weddings.empty.description')"
      :actions="[{ icon: 'i-lucide-plus', label: $t('weddings.create'), onClick: () => { creating = true } }]"
    />

    <div
      v-else
      class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
    >
      <UPageCard
        v-for="wedding in weddings.weddings"
        :key="wedding.id"
        :to="`/weddings/${wedding.id}`"
        :title="wedding.name"
        :description="weddingDate(wedding)"
        icon="i-lucide-heart"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <p class="text-xs text-muted">
              {{ $t('weddings.budgetTarget') }}
            </p>
            <p class="font-medium text-highlighted tabular-nums">
              {{ format(wedding.budget_target) }}
            </p>
          </div>
          <div class="flex gap-1">
            <UBadge
              :label="wedding.base_currency"
              color="neutral"
              variant="subtle"
            />
            <UBadge
              :label="$t(`weddings.status.${wedding.status}`)"
              :color="wedding.status === 'completed' ? 'success' : 'primary'"
              variant="subtle"
            />
          </div>
        </div>
      </UPageCard>
    </div>

    <WeddingCreateModal
      v-model:open="creating"
      @created="onCreated"
    />
  </div>
</template>
