import type { CurrencyCode, DecimalString } from '~/types/api'
// Explicit import (not the Nuxt auto-import) so the unit tests can load this file.
import { CURRENCIES } from '~/utils/money'

/**
 * Text handling behind MoneyInput, kept free of Vue so every rule is unit tested.
 *
 * Two representations:
 * - display: what the field shows, with the currency's separators ("150.000.000" for LAK,
 *   "4,500.50" for USD), see CURRENCIES in utils/money.ts;
 * - value: what the API receives, a plain decimal string ("150000000", "4500.50") or ""
 *   when empty. It never passes through a Number, so 18-digit kip amounts stay exact.
 */

/** DECIMAL(20,2) and the API's Money rule: at most 18 integer digits. */
export const MAX_INTEGER_DIGITS = 18

export interface MoneyInputState {
  display: string
  value: DecimalString
  /** Caret position in display after the edit. */
  caret: number
}

function groupDigits(integer: string, separator: string): string {
  return integer.replace(/\B(?=(\d{3})+(?!\d))/g, separator)
}

function stripLeadingZeros(integer: string): string {
  return integer.replace(/^0+(?=\d)/, '')
}

/** Pattern of a valid API value in this currency (no fraction for LAK). */
export function amountPattern(currency: CurrencyCode): RegExp {
  const { decimals } = CURRENCIES[currency]

  return decimals === 0
    ? new RegExp(`^\\d{1,${MAX_INTEGER_DIGITS}}$`)
    : new RegExp(`^\\d{1,${MAX_INTEGER_DIGITS}}(\\.\\d{1,${decimals}})?$`)
}

/**
 * Display text for a stored value, e.g. from the API ("150000000.00" LAK → "150.000.000").
 * With pad, the fraction is completed to the currency's decimals ("4500.5" USD → "4,500.50").
 * A zero fraction beyond the currency's decimals is dropped; a non-zero one is kept so the
 * form can flag it instead of silently changing the amount.
 */
export function toDisplay(value: DecimalString, currency: CurrencyCode, { pad = true } = {}): string {
  if (value === '') {
    return ''
  }

  const { decimals, group, decimal } = CURRENCIES[currency]
  const [rawInteger = '', rawFraction = ''] = value.split('.')
  const integer = stripLeadingZeros(rawInteger === '' ? '0' : rawInteger)
  let fraction = rawFraction

  if (fraction.length > decimals && /^0*$/.test(fraction.slice(decimals))) {
    fraction = fraction.slice(0, decimals)
  }

  if (pad && decimals > 0) {
    fraction = fraction.padEnd(decimals, '0')
  }

  return groupDigits(integer, group) + (fraction === '' ? '' : decimal + fraction)
}

/**
 * Canonical API value: no leading zeros, fraction padded to the currency's decimals
 * ("4500.5" USD → "4500.50"), zero fractions dropped where the currency has none
 * ("150000000.00" LAK → "150000000"). A non-zero fraction the currency cannot hold is kept
 * as is, for validation to reject.
 */
export function normalizeValue(value: DecimalString, currency: CurrencyCode): DecimalString {
  if (value === '') {
    return ''
  }

  const { decimals } = CURRENCIES[currency]
  const [rawInteger = '', rawFraction = ''] = value.split('.')
  const integer = stripLeadingZeros(rawInteger === '' ? '0' : rawInteger)
  let fraction = rawFraction

  if (/^0*$/.test(fraction.slice(decimals))) {
    fraction = fraction.slice(0, decimals)
  }

  if (fraction === '') {
    return integer
  }

  return `${integer}.${fraction.padEnd(decimals, '0')}`
}

/**
 * Re-reads the field after a keystroke and regroups it, keeping the caret next to the same
 * digit. Only digits and (for currencies with a minor unit) the currency's decimal mark are
 * kept; separators the user types are ignored because grouping is automatic. Extra decimals
 * and digits beyond 18 are dropped, so the field can never hold an amount the API rejects.
 *
 * @param raw   the field's text right after the browser applied the keystroke
 * @param caret selectionStart in raw
 */
export function applyTyping(raw: string, caret: number, currency: CurrencyCode): MoneyInputState {
  const { decimals, group, decimal } = CURRENCIES[currency]
  let integer = ''
  let fraction = ''
  let hasDecimal = false
  // Kept characters (digits and the decimal mark) left of the caret.
  let keptBeforeCaret = 0

  for (let index = 0; index < raw.length; index++) {
    const char = raw[index]!
    let kept = false

    if (char >= '0' && char <= '9') {
      if (!hasDecimal && integer.length < MAX_INTEGER_DIGITS) {
        integer += char
        kept = true
      } else if (hasDecimal && fraction.length < decimals) {
        fraction += char
        kept = true
      }
    } else if (decimals > 0 && !hasDecimal && (char === decimal || char === '.')) {
      // The currency's mark, or "." which every keyboard's numeric pad produces.
      hasDecimal = true
      kept = true
    }

    if (kept && index < caret) {
      keptBeforeCaret++
    }
  }

  // "007" → "7"; the removed zeros sat left of the caret.
  const trimmed = stripLeadingZeros(integer)
  keptBeforeCaret = Math.max(0, keptBeforeCaret - (integer.length - trimmed.length))
  integer = trimmed

  // ".5" → "0.5": the inserted zero precedes the decimal mark.
  if (hasDecimal && integer === '') {
    integer = '0'
    keptBeforeCaret++
  }

  const display = groupDigits(integer, group) + (hasDecimal ? decimal + fraction : '')
  const value = integer === '' ? '' : integer + (fraction === '' ? '' : `.${fraction}`)

  // Place the caret after the same number of kept characters in the regrouped text.
  let position = 0
  for (let seen = 0; position < display.length && seen < keptBeforeCaret; position++) {
    if (display[position] !== group) {
      seen++
    }
  }

  return { display, value, caret: position }
}

/**
 * Reads a pasted amount whatever its convention: "150.000.000", "150,000,000",
 * "₭ 150 000 000", "1,234.50", "1.234,50", "$4,500" or "4500 USD".
 *
 * A separator followed by exactly 3 digits is a thousands separator (no currency here has
 * 3 decimals); one followed by 1 or 2 digits is the decimal mark. Groups must have 3 digits,
 * so "1,50,000" is refused rather than guessed. Returns the API value, "" for blank text, or
 * null when the text is not an amount this currency can hold (e.g. "1500.50" for LAK, which
 * has no minor unit: dropping the 50 would change the amount).
 */
export function parsePastedAmount(text: string, currency: CurrencyCode): DecimalString | null {
  const { decimals } = CURRENCIES[currency]
  const cleaned = text
    // \s includes no-break and narrow no-break spaces (U+00A0, U+202F) used as group separators.
    .replace(/[\s'’]/g, '')
    .replace(/[₭$฿]|LAK|USD|THB|kip|baht/gi, '')
    .replace(/^\+/, '')

  if (cleaned === '') {
    return ''
  }

  if (!/^[\d.,]+$/.test(cleaned) || !/\d/.test(cleaned)) {
    return null
  }

  const lastSeparator = Math.max(cleaned.lastIndexOf('.'), cleaned.lastIndexOf(','))
  let integerPart = cleaned
  let fraction = ''

  if (lastSeparator !== -1) {
    const tail = cleaned.slice(lastSeparator + 1)
    const mark = cleaned[lastSeparator]!
    const marksOfThatKind = cleaned.split(mark).length - 1

    if (tail.length === 1 || tail.length === 2 || tail.length === 0) {
      // Decimal mark (or a trailing one, "1500."), used once.
      if (marksOfThatKind !== 1) {
        return null
      }
      integerPart = cleaned.slice(0, lastSeparator)
      fraction = tail
    } else if (tail.length !== 3) {
      return null
    }
  }

  const groups = integerPart.split(/[.,]/)
  if (groups.length > 1 && (groups[0]!.length < 1 || groups[0]!.length > 3 || groups.slice(1).some(part => part.length !== 3))) {
    return null
  }

  const integer = stripLeadingZeros(groups.join('') || '0')

  if (integer.length > MAX_INTEGER_DIGITS) {
    return null
  }

  if (fraction.length > decimals) {
    if (!/^0+$/.test(fraction.slice(decimals))) {
      return null
    }
    fraction = fraction.slice(0, decimals)
  }

  return fraction === '' ? integer : `${integer}.${fraction}`
}
