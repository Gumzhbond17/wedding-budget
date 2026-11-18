<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'guest', middleware: 'guest' })

const { t } = useI18n()
const auth = useAuthStore()

useHead({ title: () => t('auth.register.title') })

// Same password rule as the API (Password::min(8)->letters()->numbers()), checked early
// for quick feedback; the API stays the authority.
const schema = computed(() => z.object({
  name: z.string().trim().min(1, t('validation.required')).max(255, t('validation.max', { max: 255 })),
  email: z.email(t('validation.email')).max(255, t('validation.max', { max: 255 })),
  password: z.string()
    .min(8, t('validation.passwordRules'))
    .regex(/\p{L}/u, t('validation.passwordRules'))
    .regex(/\p{N}/u, t('validation.passwordRules')),
  password_confirmation: z.string()
}).refine(data => data.password === data.password_confirmation, {
  message: t('validation.passwordMismatch'),
  path: ['password_confirmation']
}))

type Schema = z.output<typeof schema.value>

const state = reactive<Schema>({ name: '', email: '', password: '', password_confirmation: '' })
const form = useTemplateRef('form')
const submitting = ref(false)

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true

  try {
    await auth.register(event.data)
    await navigateTo('/weddings')
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
  <UCard>
    <template #header>
      <h1 class="text-xl font-semibold text-highlighted">
        {{ $t('auth.register.title') }}
      </h1>
      <p class="text-sm text-muted">
        {{ $t('app.tagline') }}
      </p>
    </template>

    <UForm
      ref="form"
      novalidate
      :schema="schema"
      :state="state"
      class="space-y-4"
      @submit="onSubmit"
    >
      <UFormField
        :label="$t('auth.fields.name')"
        name="name"
        required
      >
        <UInput
          v-model="state.name"
          autocomplete="name"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="$t('auth.fields.email')"
        name="email"
        required
      >
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="$t('auth.fields.password')"
        :hint="$t('auth.register.passwordHint')"
        name="password"
        required
      >
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UFormField
        :label="$t('auth.fields.passwordConfirmation')"
        name="password_confirmation"
        required
      >
        <UInput
          v-model="state.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>

      <UButton
        type="submit"
        :label="$t('auth.register.submit')"
        :loading="submitting"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-center text-sm text-muted">
        {{ $t('auth.register.haveAccount') }}
        <ULink
          to="/login"
          class="font-medium text-primary"
        >
          {{ $t('auth.register.login') }}
        </ULink>
      </p>
    </template>
  </UCard>
</template>
