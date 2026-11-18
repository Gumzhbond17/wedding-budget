<script setup lang="ts">
import type { CurrencyCode, DecimalString } from '~/types/api'

/**
 * Amount field with live thousand separators and an optional currency selector.
 *
 * v-model is the API value: a plain decimal string ("150000000", "4500.50") or "" when
 * empty; never a Number. The field shows the currency's grouping while typing, accepts
 * pasted amounts in any common format, and refuses decimals the currency cannot hold
 * (whole kip for LAK). Rules and tests: utils/moneyInput.ts.
 *
 * v-model:currency defaults to the current wedding's base currency.
 */
const props = withDefaults(defineProps<{
  /** Show the currency selector; off for amounts that must be in the base currency. */
  currencyEditable?: boolean
  /** Currencies offered by the selector. */
  currencies?: CurrencyCode[]
  placeholder?: string
  disabled?: boolean
}>(), {
  currencyEditable: true,
  currencies: () => CURRENCY_CODES,
  placeholder: '0',
  disabled: false
})

const value = defineModel<DecimalString>({ default: '' })
const currencyModel = defineModel<CurrencyCode | undefined>('currency', { default: undefined })

const { t } = useI18n()
const weddings = useWeddingsStore()
const { options } = useCurrency()

const currency = computed<CurrencyCode>({
  get: () => currencyModel.value ?? weddings.baseCurrency,
  set: (code) => {
    currencyModel.value = code
  }
})

const currencyItems = computed(() => options.value.filter(option => props.currencies.includes(option.value)))
const input = useTemplateRef('input')
const display = ref(toDisplay(value.value, currency.value))
const pasteRejected = ref(false)
// The value this component last emitted; any other value came from outside.
let emitted = value.value

function emit(next: DecimalString): void {
  emitted = next
  value.value = next
}

/** Writes text and caret straight to the element: Vue skips the patch when display is unchanged. */
function render(text: string, caret?: number): void {
  display.value = text
  const element = input.value?.inputRef

  if (element && element.value !== text) {
    element.value = text
  }

  if (element && caret !== undefined && document.activeElement === element) {
    element.setSelectionRange(caret, caret)
  }
}

function onInput(event: Event): void {
  const element = event.target as HTMLInputElement
  const state = applyTyping(element.value, element.selectionStart ?? element.value.length, currency.value)

  pasteRejected.value = false
  render(state.display, state.caret)
  emit(state.value)
}

function onPaste(event: ClipboardEvent): void {
  const text = event.clipboardData?.getData('text') ?? ''
  const parsed = parsePastedAmount(text, currency.value)

  // A pasted amount replaces the field: merging "150.000.000" into half-typed digits
  // would produce a different number than either.
  event.preventDefault()

  if (parsed === null) {
    pasteRejected.value = true

    return
  }

  pasteRejected.value = false
  const pasted = toDisplay(parsed, currency.value, { pad: false })
  render(pasted, pasted.length)
  emit(parsed)
}

/** Shows the canonical form ("4,500.5" → "4,500.50") and emits it if it differs. */
function settle(code: CurrencyCode): void {
  const normalized = normalizeValue(value.value, code)
  render(toDisplay(normalized, code))

  if (normalized !== value.value) {
    emit(normalized)
  }
}

function onBlur(): void {
  settle(currency.value)
}

// Value set from outside (form reset, record loaded for editing).
watch(value, (next) => {
  if (next !== emitted) {
    emitted = next
    render(toDisplay(next, currency.value))
  }
})

// New currency: same digits, new separators and decimals. A fraction the new currency
// cannot hold ("4500.50" switched to LAK) stays visible so validation flags it, rather
// than being dropped silently.
watch(currency, settle)
</script>

<template>
  <div class="w-full">
    <UFieldGroup class="w-full">
      <USelect
        v-if="currencyEditable"
        v-model="currency"
        :items="currencyItems"
        :disabled="disabled"
        :aria-label="t('money.currency')"
        :ui="{ content: 'min-w-56' }"
        class="w-24 shrink-0"
      >
        <template #default>
          {{ CURRENCIES[currency].symbol }} {{ currency }}
        </template>
      </USelect>

      <UInput
        ref="input"
        :model-value="display"
        :placeholder="placeholder"
        :disabled="disabled"
        :inputmode="CURRENCIES[currency].decimals === 0 ? 'numeric' : 'decimal'"
        autocomplete="off"
        class="min-w-0 flex-1"
        :ui="{ base: currencyEditable ? 'text-end tabular-nums' : 'pe-12 text-end tabular-nums' }"
        @input="onInput"
        @paste="onPaste"
        @blur="onBlur"
      >
        <template
          v-if="!currencyEditable"
          #trailing
        >
          <span class="text-sm text-muted">{{ currency }}</span>
        </template>
      </UInput>
    </UFieldGroup>

    <p
      v-if="pasteRejected"
      class="mt-1 text-sm text-error"
      role="status"
    >
      {{ CURRENCIES[currency].decimals === 0 ? t('money.pasteRejectedWhole') : t('money.pasteRejected') }}
    </p>
  </div>
</template>
