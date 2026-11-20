<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { CurrencyCode, Wedding } from '~/types/api'

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ created: [wedding: Wedding] }>()

const { t } = useI18n()
const weddings = useWeddingsStore()
const { options: currencyOptions } = useCurrency()

interface WeddingFormState {
  name: string
  wedding_date: string
  base_currency: CurrencyCode
  budget_target: string
  notes: string
}

function initialState(): WeddingFormState {
  return { name: '', wedding_date: '', base_currency: DEFAULT_CURRENCY, budget_target: '', notes: '' }
}

// Declared before the schema, which reads the chosen currency.
const state = reactive<WeddingFormState>(initialState())

// The budget target is in the base currency, so its decimals follow it (whole kip for LAK).
const schema = computed(() => z.object({
  name: z.string().trim().min(1, t('validation.required')).max(150, t('validation.max', { max: 150 })),
  wedding_date: z.string(),
  base_currency: z.enum(['LAK', 'USD', 'THB']),
  budget_target: z.string().regex(amountPattern(state.base_currency), t('validation.money')).or(z.literal('')),
  notes: z.string().max(5000, t('validation.max', { max: 5000 }))
}))

type Schema = z.output<typeof schema.value>
const form = useTemplateRef('form')
const submitting = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    Object.assign(state, initialState())
  }
})

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true

  try {
    const wedding = await weddings.create({
      name: event.data.name,
      wedding_date: event.data.wedding_date || null,
      base_currency: event.data.base_currency as CurrencyCode,
      // Omitted when empty: the API defaults the target to 0.
      ...(event.data.budget_target ? { budget_target: event.data.budget_target } : {}),
      notes: event.data.notes || null
    })
    open.value = false
    emit('created', wedding)
  } catch (error) {
    if (error instanceof ApiValidationError) {
      form.value?.setErrors(toFormErrors(error))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="$t('weddings.form.title')"
  >
    <template #body>
      <UForm
        id="wedding-create-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('weddings.form.name')"
          name="name"
          required
        >
          <UInput
            v-model="state.name"
            :placeholder="$t('weddings.form.namePlaceholder')"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('weddings.form.weddingDate')"
          name="wedding_date"
        >
          <DateInput
            v-model="state.wedding_date"
            clearable
          />
        </UFormField>

        <UFormField
          :label="$t('weddings.form.baseCurrency')"
          :help="$t('weddings.form.baseCurrencyHelp')"
          name="base_currency"
          required
        >
          <USelect
            v-model="state.base_currency"
            :items="currencyOptions"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('weddings.form.budgetTarget')"
          name="budget_target"
        >
          <MoneyInput
            v-model="state.budget_target"
            :currency="state.base_currency"
            :currency-editable="false"
          />
        </UFormField>

        <UFormField
          :label="$t('weddings.form.notes')"
          name="notes"
        >
          <UTextarea
            v-model="state.notes"
            :rows="3"
            class="w-full"
          />
        </UFormField>
      </UForm>
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
          type="submit"
          form="wedding-create-form"
          :label="$t('weddings.form.submit')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
