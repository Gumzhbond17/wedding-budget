import type { BudgetHealth, CurrencyCode, Dashboard, DecimalString, Money } from '~/types/api'
// Explicit import (not the Nuxt auto-import) so the unit tests can load this file.
import { CURRENCIES } from '~/utils/money'

/*
 * Dashboard helpers. The API computes every figure; nothing here adds, subtracts or rounds
 * money. Amount strings are only inspected (sign, zero) or, for drawing, turned into numbers.
 */

const DECIMAL = /^-?\d+(\.\d+)?$/

/**
 * A money string as a Chart.js data point. ONLY for bar lengths and slice angles: a double
 * holds 15–17 significant digits, plenty for a pixel, not for a label. Every label and
 * tooltip formats the original string with formatMoney().
 */
export function chartValue(amount: DecimalString): number {
  return Number(amount)
}

/**
 * Short axis tick for a plotted value: "₭150M", "₭1,5B", "$12.5K". Axis ticks are not
 * amounts from the API (Chart.js picks round steps), so this is the one place a number is
 * formatted. The currency's decimal mark is kept ("1,5" for kip, as in "₭1.500.000,5").
 */
export function compactMoney(value: number, currency: CurrencyCode): string {
  const { symbol, decimal } = CURRENCIES[currency]
  const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })
    .format(Math.abs(value))
    .replace('.', decimal)

  return (value < 0 ? '-' : '') + symbol + compact
}

/** -1, 0 or 1, read from the string itself. */
export function signOf(amount: DecimalString): -1 | 0 | 1 {
  if (!DECIMAL.test(amount)) {
    throw new TypeError(`Not a decimal amount: "${amount}"`)
  }

  if (/^-?0+(\.0+)?$/.test(amount)) {
    return 0
  }

  return amount.startsWith('-') ? -1 : 1
}

export function isZero(money: Money): boolean {
  return signOf(money.amount) === 0
}

/** The same amount without its minus sign, for figures whose label carries the direction. */
export function absolute(money: Money): Money {
  return { ...money, amount: money.amount.replace(/^-/, '') }
}

export type GapState = 'needed' | 'surplus' | 'balanced'

/**
 * Funding and secured gaps are committed expenses minus revenue: positive means money is
 * still missing, negative means a surplus.
 */
export function gapState(gap: Money): GapState {
  switch (signOf(gap.amount)) {
    case 1: return 'needed'
    case -1: return 'surplus'
    default: return 'balanced'
  }
}

/**
 * Whether the wedding has any revenue or expense yet. Without any, the dashboard shows
 * getting-started guidance instead of zeros and empty charts. Revenue amounts are > 0, so
 * a revenue makes the total non-zero; an expense always has a committed or paid amount
 * (a cancelled one without payments counts as nothing, which is what it is).
 */
export function hasActivity(dashboard: Dashboard): boolean {
  const { revenue, expenses } = dashboard.summary

  return !isZero(revenue.total) || !isZero(expenses.committed) || !isZero(expenses.paid)
}

export interface ChartPalette {
  /** Categorical slots, fixed order (validated light/dark pairs from the dataviz palette). */
  series: string[]
  /** Slices past the categorical slots, and "no budget" bars. */
  neutral: string
  /** Planned-budget bars: recessive, so the coloured committed bar reads first. */
  planned: string
  health: Record<BudgetHealth, string>
  text: string
  grid: string
  surface: string
}

/**
 * Chart colours per colour mode. Canvas cannot read CSS variables, so both modes are spelled
 * out. Status colours are reserved for budget health and never used for a series; they are
 * always paired with an icon and a label in the page.
 */
export function chartPalette(dark: boolean): ChartPalette {
  return dark
    ? {
        series: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#0ca30c', '#9085e9', '#e66767'],
        neutral: '#6b6a65',
        planned: '#52514e',
        health: { green: '#0ca30c', amber: '#fab219', red: '#d03b3b' },
        text: '#c3c2b7',
        grid: '#2c2c2a',
        // Nuxt UI's dark card background (neutral-900, slate): the slice gaps blend in.
        surface: '#0f172a'
      }
    : {
        series: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'],
        neutral: '#b5b3ab',
        planned: '#c3c2b7',
        health: { green: '#0ca30c', amber: '#fab219', red: '#d03b3b' },
        text: '#52514e',
        grid: '#e1e0d9',
        surface: '#ffffff'
      }
}
