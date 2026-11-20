import type { CurrencyCode, DecimalString, ExchangeRate, IsoDate, RateString } from '~/types/api'

/**
 * Preview of the base amount a form will get, computed exactly like the API
 * (CurrencyConversionService::convert): amount × rate at full precision, rounded once,
 * half away from zero, to 2 decimals. BigInt integer arithmetic, never floats, so the
 * preview matches the stored value to the last digit. The API remains the source of truth:
 * this is display only and is never sent.
 *
 * @param amount non-negative decimal with up to 2 decimals ("6500", "4500.50")
 * @param rate   non-negative decimal with up to 8 decimals ("21850.00000000")
 */
export function convertToBase(amount: DecimalString, rate: RateString): DecimalString {
  const scaled = (value: string, scale: number): bigint => {
    const [integer = '0', fraction = ''] = value.split('.')

    return BigInt((integer || '0') + fraction.padEnd(scale, '0').slice(0, scale))
  }

  // amount × 10^2 times rate × 10^8 is the product × 10^10; keep 2 decimals: ÷ 10^8.
  const divisor = 10n ** 8n
  const product = scaled(amount, 2) * scaled(rate, 8)
  let cents = product / divisor

  // Half away from zero (inputs are non-negative, so: half up).
  if ((product % divisor) * 2n >= divisor) {
    cents += 1n
  }

  const digits = cents.toString().padStart(3, '0')

  return `${digits.slice(0, -2)}.${digits.slice(-2)}`
}

/**
 * The rate the API will snapshot for a record in `currency` dated `date`: the latest one
 * effective on or before that date (never a later one), or null when none exists, in which
 * case the API refuses the save. The base currency itself converts at 1.
 */
export function rateOn(rates: ExchangeRate[], currency: CurrencyCode, base: CurrencyCode, date: IsoDate): ExchangeRate | 'base' | null {
  if (currency === base) {
    return 'base'
  }

  return rates
    .filter(rate => rate.from_currency === currency && rate.effective_date <= date)
    .sort((a, b) => b.effective_date.localeCompare(a.effective_date))[0] ?? null
}
