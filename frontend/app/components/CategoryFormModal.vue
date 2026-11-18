<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { Category, CategoryType, ResourceResponse } from '~/types/api'

/**
 * Create or edit a category. Planned budgets exist for expense categories only and are in
 * the wedding's base currency.
 */
const props = defineProps<{
  weddingId: number
  type: CategoryType
  /** The category to edit; null to create one. */
  category: Category | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [category: Category] }>()

const { t } = useI18n()
const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)
const isExpense = computed(() => (props.category?.type ?? props.type) === 'expense')

const schema = computed(() => z.object({
  name: z.string().trim().min(1, t('validation.required')).max(100, t('validation.max', { max: 100 })),
  planned_amount: z.string().regex(amountPattern(base.value), t('validation.money')).or(z.literal(''))
}))

type Schema = z.output<typeof schema.value>

const state = reactive<Schema>({ name: '', planned_amount: '' })
const form = useTemplateRef('form')
const submitting = ref(false)

watch(open, (isOpen) => {
  if (isOpen) {
    state.name = props.category?.name ?? ''
    state.planned_amount = props.category?.planned_amount?.amount ?? ''
  }
})

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true
  // An empty plan clears it (null); revenue categories never send one.
  const plan = isExpense.value ? { planned_amount: event.data.planned_amount || null } : {}

  try {
    const { data } = props.category
      ? await api.patch<ResourceResponse<Category>>(`/weddings/${props.weddingId}/categories/${props.category.id}`, { name: event.data.name, ...plan })
      : await api.post<ResourceResponse<Category>>(`/weddings/${props.weddingId}/categories`, { type: props.type, name: event.data.name, ...plan })

    open.value = false
    emit('saved', data)
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
    :title="category ? $t('categories.form.editTitle') : $t(`categories.form.createTitle.${type}`)"
  >
    <template #body>
      <UForm
        id="category-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('categories.form.name')"
          name="name"
          required
        >
          <UInput
            v-model="state.name"
            class="w-full"
          />
        </UFormField>

        <UFormField
          v-if="isExpense"
          :label="$t('categories.form.planned')"
          :help="$t('categories.form.plannedHelp')"
          name="planned_amount"
        >
          <MoneyInput
            v-model="state.planned_amount"
            :currency="base"
            :currency-editable="false"
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
          form="category-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
