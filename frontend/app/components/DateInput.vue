<script setup lang="ts">
import flatpickr from 'flatpickr'
import type { Instance } from 'flatpickr/dist/types/instance'
import type { IsoDate } from '~/types/api'

/**
 * Date field: a read-only UInput showing the date in the active language, which opens a
 * flatpickr calendar. The model stays a "YYYY-MM-DD" string ('' when empty), as the API
 * expects, so forms keep the same state shape as with the old native date input.
 *
 * Why the calendar sits inline inside a UPopover instead of flatpickr's own floating popup:
 * flatpickr appends its popup to <body>, outside any open UModal. The modal then treats a
 * click on the calendar as an outside click and closes, and its focus trap blocks the
 * calendar's month and year controls. A UPopover joins the modal's layer stack, so neither
 * happens, and it brings Nuxt UI's positioning, collision handling and Escape key.
 */
// The root is a UPopover, which renders no element of its own: class and other attributes
// go to the visible UInput instead.
defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  placeholder?: string
  /** Earliest selectable date, "YYYY-MM-DD". */
  min?: IsoDate
  /** Latest selectable date, "YYYY-MM-DD". */
  max?: IsoDate
  /** Shows a button that empties the field. Leave off for required dates. */
  clearable?: boolean
  disabled?: boolean
}>(), {
  placeholder: undefined,
  min: undefined,
  max: undefined,
  clearable: false,
  disabled: false
})

const model = defineModel<string>({ default: '' })

const { d, locale, t } = useI18n()

const open = ref(false)
const input = useTemplateRef('input')
const anchor = useTemplateRef<HTMLInputElement>('anchor')
let picker: Instance | null = null

// Same short format as the record tables ("Dec 15, 2026"), so it also fits the filter bar.
const display = computed(() => (model.value ? d(dateForDisplay(model.value), 'short') : ''))

/*
 * The popover content only exists while it is open, so the calendar is created each time
 * it opens and destroyed on close. That also picks up a language switch or a new min/max
 * without keeping an instance in sync.
 */
watch(anchor, (element) => {
  picker?.destroy()
  picker = null
  if (!element) {
    return
  }
  picker = flatpickr(element, {
    inline: true,
    dateFormat: 'Y-m-d',
    defaultDate: model.value || undefined,
    minDate: props.min,
    maxDate: props.max,
    locale: flatpickrLocale(locale.value),
    onChange: (_dates, dateStr) => {
      select(dateStr)
      open.value = false
    }
  })
})

onBeforeUnmount(() => picker?.destroy())

function select(value: string): void {
  model.value = value
  /*
   * UForm re-validates a field on the input's own change event. The value changed from
   * code, not typing, so fire that event once Vue has rendered the new value; otherwise a
   * "required" error stays on screen after a date is picked.
   */
  nextTick(() => input.value?.inputRef?.dispatchEvent(new Event('change')))
}

// Keyboard access: the input is read-only, so Enter, Space and ArrowDown open the calendar.
function onKeydown(event: KeyboardEvent): void {
  if (['Enter', ' ', 'ArrowDown'].includes(event.key)) {
    event.preventDefault()
    open.value = true
  }
}
</script>

<template>
  <UPopover
    v-model:open="open"
    :disabled="disabled"
    :content="{ align: 'start' }"
  >
    <UInput
      v-bind="$attrs"
      ref="input"
      :model-value="display"
      :placeholder="placeholder ?? t('common.pickDate')"
      :disabled="disabled"
      icon="i-lucide-calendar"
      readonly
      class="w-full"
      :ui="{ base: 'cursor-pointer', trailing: 'pe-1' }"
      @keydown="onKeydown"
    >
      <template
        v-if="clearable && model && !disabled"
        #trailing
      >
        <!-- .stop: the click must not reach the popover trigger and reopen the calendar. -->
        <UButton
          icon="i-lucide-x"
          color="neutral"
          variant="link"
          size="sm"
          :aria-label="t('common.clearDate')"
          @click.stop="select('')"
        />
      </template>
    </UInput>

    <template #content>
      <div class="p-2">
        <!-- flatpickr needs an input to attach to and turns it into a text box: keep it hidden. -->
        <input
          ref="anchor"
          type="hidden"
          class="hidden"
          tabindex="-1"
          aria-hidden="true"
        >
      </div>
    </template>
  </UPopover>
</template>
