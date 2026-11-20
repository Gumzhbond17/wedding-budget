import { describe, expect, it } from 'vitest'
import type { ExchangeRate } from '~/types/api'
import { convertToBase, rateOn } from '~/utils/conversion'

describe('convertToBase (same rule as the API)', () => {
  it.each([
    ['6500', '21850.00000000', '142025000.00'],
    ['6500.00', '21850', '142025000.00'],
    ['30000.00', '628.40000000', '18852000.00'],
    ['1000.50', '21900.50000000', '21911450.25'],
    // Half away from zero, once: 0.005 → 0.01.
    ['0.01', '0.50000000', '0.01'],
    ['0.01', '0.49999999', '0.00'],
    ['3', '0.33333333', '1.00'],
    ['1.00', '0.12345678', '0.12'],
    ['0', '21850', '0.00']
  ])('%s × %s = %s', (amount, rate, expected) => {
    expect(convertToBase(amount, rate)).toBe(expected)
  })

  it('stays exact beyond the float range', () => {
    // 1e15 USD × 21850 = 2.185e19: a Number would already lose digits.
    expect(convertToBase('999999999999999.99', '21850.12345678')).toBe('21850123456779999781.50')
  })
})

describe('rateOn (same lookup as the API)', () => {
  const rate = (id: number, from: 'USD' | 'THB', date: string): ExchangeRate =>
    ({ id, from_currency: from, to_currency: 'LAK', rate: '1', effective_date: date })
  const rates = [rate(1, 'USD', '2026-08-01'), rate(2, 'USD', '2026-10-01'), rate(3, 'THB', '2026-09-01'), rate(4, 'USD', '2026-12-01')]

  it('takes the latest rate on or before the date', () => {
    expect(rateOn(rates, 'USD', 'LAK', '2026-11-18')).toMatchObject({ id: 2 })
    expect(rateOn(rates, 'USD', 'LAK', '2026-10-01')).toMatchObject({ id: 2 })
    expect(rateOn(rates, 'USD', 'LAK', '2026-09-30')).toMatchObject({ id: 1 })
  })

  it('never falls back to a later rate', () => {
    expect(rateOn(rates, 'USD', 'LAK', '2026-07-31')).toBeNull()
    expect(rateOn(rates, 'THB', 'LAK', '2026-08-15')).toBeNull()
  })

  it('needs no rate for the base currency', () => {
    expect(rateOn([], 'LAK', 'LAK', '2026-11-18')).toBe('base')
  })
})
