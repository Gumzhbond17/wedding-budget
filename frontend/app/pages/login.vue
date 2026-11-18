<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'guest', middleware: 'guest' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

useHead({ title: () => t('auth.login.title') })

// Built in a computed so the messages follow a language switch.
const schema = computed(() => z.object({
  email: z.email(t('validation.email')),
  password: z.string().min(1, t('validation.required')),
  remember: z.boolean()
}))

type Schema = z.output<typeof schema.value>

const state = reactive<Schema>({ email: '', password: '', remember: false })
const form = useTemplateRef('form')
const submitting = ref(false)

async function onSubmit(event: FormSubmitEvent<Schema>): Promise<void> {
  submitting.value = true

  try {
    await auth.login(event.data)
    await navigateTo(safeRedirect(route.query.redirect))
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
        {{ $t('auth.login.title') }}
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
        name="password"
        required
      >
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>

      <UFormField name="remember">
        <UCheckbox
          v-model="state.remember"
          :label="$t('auth.login.remember')"
        />
      </UFormField>

      <UButton
        type="submit"
        :label="$t('auth.login.submit')"
        :loading="submitting"
        block
      />
    </UForm>

    <template #footer>
      <p class="text-center text-sm text-muted">
        {{ $t('auth.login.noAccount') }}
        <ULink
          to="/register"
          class="font-medium text-primary"
        >
          {{ $t('auth.login.register') }}
        </ULink>
      </p>
    </template>
  </UCard>
</template>
