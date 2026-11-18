import type { CurrencyCode, DecimalString, Money } from '~/types/api'

/**
 * Mirrors the backend Currency enum (symbol, display decimals, locale). LAK has no minor
 * unit in daily use, so it displays without decimals even though 2 are stored.
 *
 * Separators are spelled out instead of taken from the locale: Chrome and Edge ship no Lao
 * number data and silently fall back to en-US ("₭150,000,000"), while Firefox and Node
 * format lo-LA as "₭150.000.000". Fixed separators give the same text in every browser.
 */
export const CURRENCIES = {
  LAK: { code: 'LAK', symbol: '₭', decimals: 0, locale: 'lo-LA', group: '.', decimal: ',' },
  USD: { code: 'USD', symbol: '$', decimals: 2, locale: 'en-US', group: ',', decimal: '.' },
  THB: { code: 'THB', symbol: '฿', decimals: 2, locale: 'th-TH', group: ',', decimal: '.' }
} as const satisfies Record<CurrencyCode, {
  code: CurrencyCode
  symbol: string
  decimals: number
  locale: string
  group: string
  decimal: string
}>

export const CURRENCY_CODES = Object.keys(CURRENCIES) as CurrencyCode[]

export const DEFAULT_CURRENCY: CurrencyCode = 'LAK'

const formatters = new Map<CurrencyCode, Intl.NumberFormat>()

function formatterFor(currency: CurrencyCode): Intl.NumberFormat {
  let formatter = formatters.get(currency)

  if (!formatter) {
    const { decimals } = CURRENCIES[currency]
    // en-US is the one locale every engine ships; only its rounding, digit grouping and
    // symbol placement are used. Separators are swapped per currency below.
    formatter = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
      // "₭", "$", "฿" rather than "LAK", "US$", "THB".
      currencyDisplay: 'narrowSymbol',
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    })
    formatters.set(currency, formatter)
  }

  return formatter
}

/**
 * Formats a decimal string for display, e.g. "150000000.00" LAK as "₭150.000.000".
 *
 * The string is handed to Intl.NumberFormat as-is: since ES2023 it formats decimal strings
 * exactly, so an amount like "123456789012345678.49" is never squeezed through a float.
 * Display rounding (LAK to whole kip) is half away from zero, like the backend's storage
 * rounding, and is never written back.
 */
export function formatMoney(amount: DecimalString, currency: CurrencyCode): string {
  if (!/^-?\d+(\.\d+)?$/.test(amount)) {
    throw new TypeError(`Not a decimal amount: "${amount}"`)
  }

  const { group, decimal } = CURRENCIES[currency]

  // Intl accepts numeric strings at runtime; the cast only satisfies older lib typings.
  return formatterFor(currency)
    .formatToParts(amount as unknown as number)
    .map((part) => {
      switch (part.type) {
        case 'group': return group
        case 'decimal': return decimal
        default: return part.value
      }
    })
    .join('')
}

export function formatMoneyObject(money: Money): string {
  return formatMoney(money.amount, money.currency)
}
