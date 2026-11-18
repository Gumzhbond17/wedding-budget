<script setup lang="ts">
import type { CurrencyCode, ExchangeRate, PaginatedResponse } from '~/types/api'

/**
 * Manual exchange rates into the base currency. A record in a foreign currency takes the
 * rate effective on its date; without one the API refuses to save it, so this page says
 * plainly which currencies have no usable rate today.
 */
const { t, d } = useI18n()
const route = useRoute()
const api = useApi()
const toast = useToast()
const weddings = useWeddingsStore()

useHead({ title: () => t('settings.exchangeRates') })

const weddingId = computed(() => Number(route.params.weddingId))
const base = computed(() => weddings.baseCurrency)
const foreign = computed(() => CURRENCY_CODES.filter(code => code !== base.value))
const rates = ref<ExchangeRate[]>([])
const loading = ref(true)

async function load(): Promise<void> {
  loading.value = true

  try {
    const all: ExchangeRate[] = []
    let page = 1
    let lastPage: number

    do {
      const response = await api.get<PaginatedResponse<ExchangeRate>>(`/weddings/${weddingId.value}/exchange-rates`, { query: { page } })
      all.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage)

    rates.value = all
  } catch {
    // Toasted by useApi.
  } finally {
    loading.value = false
  }
}

watch(weddingId, load, { immediate: true })

/**
 * Currencies with no rate effective today (Vientiane), including those whose only rates
 * start in the future: a record dated today could not be saved in them.
 */
const missing = computed<CurrencyCode[]>(() => {
  const today = todayInAppTimezone()

  return foreign.value.filter(code => !rates.value.some(rate => rate.from_currency === code && rate.effective_date <= today))
})

/** The rate in force today per currency, to tag it in the list. */
const current = computed(() => {
  const today = todayInAppTimezone()
  const ids = new Set<number>()

  for (const code of foreign.value) {
    // The API lists newest effective date first.
    const rate = rates.value.find(item => item.from_currency === code && item.effective_date <= today)
    if (rate) {
      ids.add(rate.id)
    }
  }

  return ids
})

// Create / edit
const formOpen = ref(false)
const editing = ref<ExchangeRate | null>(null)
const presetCurrency = ref<CurrencyCode | null>(null)

function openCreate(currency: CurrencyCode | null = null): void {
  editing.value = null
  presetCurrency.value = currency
  formOpen.value = true
}

function openEdit(rate: ExchangeRate): void {
  editing.value = rate
  formOpen.value = true
}

async function onSaved(): Promise<void> {
  toast.add({ title: t('rates.saved'), color: 'success', icon: 'i-lucide-check' })
  await load()
}

// Delete
const deleting = ref<ExchangeRate | null>(null)
const deleteOpen = ref(false)
const deleteBusy = ref(false)

function askDelete(rate: ExchangeRate): void {
  deleting.value = rate
  deleteOpen.value = true
}

async function confirmDelete(): Promise<void> {
  if (!deleting.value) {
    return
  }

  deleteBusy.value = true

  try {
    await api.delete(`/weddings/${weddingId.value}/exchange-rates/${deleting.value.id}`)
    deleteOpen.value = false
    toast.add({ title: t('rates.deleted'), color: 'success', icon: 'i-lucide-check' })
    await load()
  } catch {
    // Toasted by useApi.
  } finally {
    deleteBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <UAlert
      v-for="code in loading ? [] : missing"
      :key="code"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('rates.missingTitle', { currency: code })"
      :description="$t('rates.missingDescription', { currency: code, base })"
      :actions="[{ label: $t('rates.addFor', { currency: code }), color: 'warning', variant: 'solid', onClick: () => openCreate(code) }]"
    />

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-muted">
        {{ $t('rates.help', { base }) }}
      </p>
      <UButton
        icon="i-lucide-plus"
        :label="$t('rates.add')"
        @click="openCreate()"
      />
    </div>

    <USkeleton
      v-if="loading && rates.length === 0"
      class="h-40"
    />

    <UEmpty
      v-else-if="rates.length === 0"
      icon="i-lucide-arrow-right-left"
      :title="$t('rates.empty')"
    />

    <UCard
      v-else
      :ui="{ body: 'p-0 sm:p-0' }"
    >
      <ul class="divide-y divide-default">
        <li
          v-for="rate in rates"
          :key="rate.id"
          class="flex items-center gap-3 px-4 py-3"
        >
          <div class="min-w-0 flex-1">
            <p class="font-medium text-highlighted tabular-nums">
              {{ $t('rates.pair', { from: rate.from_currency, rate: formatRate(rate.rate, rate.to_currency) }) }}
            </p>
            <p class="text-sm text-muted">
              {{ $t('rates.from', { date: d(dateForDisplay(rate.effective_date), 'long') }) }}
              <UBadge
                v-if="current.has(rate.id)"
                :label="$t('rates.inForce')"
                color="success"
                variant="subtle"
                size="sm"
                class="ms-1"
              />
            </p>
          </div>

          <UButton
            icon="i-lucide-pencil"
            color="neutral"
            variant="ghost"
            :aria-label="$t('rates.edit')"
            @click="openEdit(rate)"
          />
          <UButton
            icon="i-lucide-trash-2"
            color="error"
            variant="ghost"
            :aria-label="$t('rates.delete')"
            @click="askDelete(rate)"
          />
        </li>
      </ul>
    </UCard>

    <ExchangeRateFormModal
      v-model:open="formOpen"
      :wedding-id="weddingId"
      :base="base"
      :foreign="foreign"
      :rate="editing"
      :preset-currency="presetCurrency"
      @saved="onSaved"
    />

    <ConfirmModal
      v-model:open="deleteOpen"
      :title="$t('rates.deleteTitle')"
      :description="$t('rates.deleteDescription')"
      :confirm-label="$t('common.delete')"
      :loading="deleteBusy"
      @confirm="confirmDelete"
    />
  </div>
</template>
