import type { CurrencyCode, Money } from '~/types/api'

/**
 * Currency display helpers. Formatting follows each currency's own locale and decimals
 * (utils/money.ts); names follow the UI language.
 */
export function useCurrency() {
  const { t } = useI18n()

  return {
    currencies: CURRENCY_CODES,
    format: (money: Money): string => formatMoney(money.amount, money.currency),
    formatAmount: (amount: string, currency: CurrencyCode): string => formatMoney(amount, currency),
    symbol: (currency: CurrencyCode): string => CURRENCIES[currency].symbol,
    label: (currency: CurrencyCode): string => t(`currency.${currency}`),
    options: computed(() => CURRENCY_CODES.map(code => ({
      value: code,
      label: `${CURRENCIES[code].symbol} ${code} · ${t(`currency.${code}`)}`
    })))
  }
}
