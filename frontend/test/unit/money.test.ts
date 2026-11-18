import { describe, expect, it } from 'vitest'
import { formatMoney, formatMoneyObject, formatRate } from '~/utils/money'
import { todayInAppTimezone } from '~/utils/dates'

describe('formatRate', () => {
  it.each([
    ['21850.00000000', 'LAK', '₭21.850'],
    ['628.40000000', 'LAK', '₭628,4'],
    ['0.00004577', 'USD', '$0.00004577'],
    ['35.12345678', 'THB', '฿35.12345678'],
    ['123456789012.12345678', 'LAK', '₭123.456.789.012,12345678']
  ] as const)('shows %s in %s as %s, all 8 decimals kept', (rate, base, expected) => {
    expect(formatRate(rate, base)).toBe(expected)
  })
})

describe('todayInAppTimezone', () => {
  it('follows Vientiane (UTC+7), not the device clock', () => {
    // 17:30 UTC on 30 November is 00:30 on 1 December in Vientiane.
    expect(todayInAppTimezone(new Date('2026-11-30T17:30:00Z'))).toBe('2026-12-01')
    expect(todayInAppTimezone(new Date('2026-11-30T16:30:00Z'))).toBe('2026-11-30')
  })
})

describe('formatMoney', () => {
  it.each([
    // LAK: whole kip, Lao grouping (dot), symbol first.
    ['150000000.00', 'LAK', '₭150.000.000'],
    ['0.00', 'LAK', '₭0'],
    // USD and THB: always 2 decimals.
    ['1234.5', 'USD', '$1,234.50'],
    ['4500', 'USD', '$4,500.00'],
    ['628.40', 'THB', '฿628.40'],
    ['30000.00', 'THB', '฿30,000.00']
  ] as const)('formats %s %s as %s', (amount, currency, expected) => {
    expect(formatMoney(amount, currency)).toBe(expected)
  })

  it('rounds LAK half away from zero for display only', () => {
    expect(formatMoney('1500.49', 'LAK')).toBe('₭1.500')
    expect(formatMoney('1500.50', 'LAK')).toBe('₭1.501')
    expect(formatMoney('-1500.50', 'LAK')).toBe('-₭1.501')
  })

  it('keeps every digit of amounts beyond the float range', () => {
    // 1.2e17 > Number.MAX_SAFE_INTEGER (9.0e15): a Number would print ...678 as ...680.
    expect(formatMoney('123456789012345678.00', 'LAK')).toBe('₭123.456.789.012.345.678')
    expect(formatMoney('123456789012345678.49', 'USD')).toBe('$123,456,789,012,345,678.49')
  })

  it('formats an API money object', () => {
    expect(formatMoneyObject({ amount: '21850.00', currency: 'LAK' })).toBe('₭21.850')
  })

  it.each(['', '1,000', '1e5', 'abc', ' 12'])('rejects %j instead of printing NaN', (amount) => {
    expect(() => formatMoney(amount, 'USD')).toThrow(TypeError)
  })
})
