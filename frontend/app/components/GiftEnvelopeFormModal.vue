<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'
import type { GiftEnvelope, GuestGroup, ResourceResponse } from '~/types/api'

/**
 * Create or edit a gift envelope. The amount is always in the wedding's base currency, so
 * there is no currency picker and no rate preview.
 *
 * Envelopes are entered in batches after the wedding: "Save and add another" keeps the group
 * and date, clears the rest and stays open, so a stack of cards is typed without reopening.
 */
const props = defineProps<{
  weddingId: number
  envelope: GiftEnvelope | null
  /** Date suggested for a new envelope (the wedding day, else today). */
  defaultDate: string
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ saved: [envelope: GiftEnvelope] }>()

const { t } = useI18n()
const api = useApi()
const weddings = useWeddingsStore()
const base = computed(() => weddings.baseCurrency)

interface EnvelopeFormState {
  full_name: string
  amount: string
  guest_group: GuestGroup
  received_date: string
  notes: string
}

const state = reactive<EnvelopeFormState>(blank())

function blank(): EnvelopeFormState {
  return { full_name: '', amount: '', guest_group: 'friends', received_date: props.defaultDate, notes: '' }
}

const schema = computed(() => z.object({
  full_name: z.string().trim().min(1, t('validation.required')).max(150, t('validation.max', { max: 150 })),
  amount: z.string()
    .regex(amountPattern(base.value), t('validation.money'))
    .refine(value => /[1-9]/.test(value), t('validation.positive')),
  guest_group: z.enum(GUEST_GROUPS as [GuestGroup, ...GuestGroup[]]),
  received_date: z.string().min(1, t('validation.required')),
  notes: z.string().max(5000, t('validation.max', { max: 5000 }))
}))

type Schema = z.output<typeof schema.value>

const form = useTemplateRef('form')
const nameField = useTemplateRef('nameField')
const submitting = ref(false)
/** Set by the "Save and add another" button just before it submits the form. */
let addAnother = false

const groupItems = computed(() => GUEST_GROUPS.map(value => ({ value, label: t(`guestGroup.${value}`) })))

watch(open, (isOpen) => {
  if (!isOpen) {
    return
  }

  const envelope = props.envelope
  Object.assign(state, envelope
    ? {
        full_name: envelope.full_name,
        amount: envelope.amount.amount,
        guest_group: envelope.guest_group,
        received_date: envelope.received_date,
        notes: envelope.notes ?? ''
      }
    : blank())
})

function submitAndAddAnother(): void {
  addAnother = true
  form.value?.submit()
}

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  const keepOpen = addAnother && !props.envelope
  addAnother = false
  submitting.value = true
  const data = event.data
  const payload = {
    full_name: data.full_name,
    amount: data.amount,
    guest_group: data.guest_group,
    received_date: data.received_date,
    notes: data.notes || null
  }

  try {
    const { data: saved } = props.envelope
      ? await api.patch<ResourceResponse<GiftEnvelope>>(`/weddings/${props.weddingId}/gift-envelopes/${props.envelope.id}`, payload)
      : await api.post<ResourceResponse<GiftEnvelope>>(`/weddings/${props.weddingId}/gift-envelopes`, payload)

    emit('saved', saved)

    if (keepOpen) {
      Object.assign(state, { full_name: '', amount: '', notes: '' })
      form.value?.clear()
      await nextTick()
      nameField.value?.inputRef?.focus()
    } else {
      open.value = false
    }
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
    :title="envelope ? $t('giftEnvelopes.form.editTitle') : $t('giftEnvelopes.form.createTitle')"
  >
    <template #body>
      <UForm
        id="gift-envelope-form"
        ref="form"
        novalidate
        :schema="schema"
        :state="state"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField
          :label="$t('giftEnvelopes.fields.fullName')"
          name="full_name"
          required
        >
          <UInput
            ref="nameField"
            v-model="state.full_name"
            autocomplete="off"
            class="w-full"
          />
        </UFormField>

        <UFormField
          :label="$t('fields.amount')"
          :hint="$t('giftEnvelopes.form.amountHint', { currency: base })"
          name="amount"
          required
        >
          <MoneyInput
            v-model="state.amount"
            :currency-editable="false"
          />
        </UFormField>

        <div class="grid gap-4 sm:grid-cols-2">
          <UFormField
            :label="$t('giftEnvelopes.fields.group')"
            name="guest_group"
            required
          >
            <USelect
              v-model="state.guest_group"
              :items="groupItems"
              class="w-full"
            />
          </UFormField>

          <UFormField
            :label="$t('revenues.fields.receivedDate')"
            name="received_date"
            required
          >
            <DateInput v-model="state.received_date" />
          </UFormField>
        </div>

        <UFormField
          :label="$t('giftEnvelopes.fields.description')"
          name="notes"
        >
          <UTextarea
            v-model="state.notes"
            :rows="2"
            class="w-full"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <div class="flex w-full flex-wrap justify-end gap-2">
        <UButton
          :label="$t('common.cancel')"
          color="neutral"
          variant="ghost"
          @click="open = false"
        />
        <UButton
          v-if="!envelope"
          :label="$t('giftEnvelopes.form.saveAndAdd')"
          color="neutral"
          variant="outline"
          :loading="submitting"
          @click="submitAndAddAnother"
        />
        <UButton
          type="submit"
          form="gift-envelope-form"
          :label="$t('common.save')"
          :loading="submitting"
        />
      </div>
    </template>
  </UModal>
</template>
