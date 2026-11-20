import { describe, expect, it } from 'vitest'
import type { Dashboard, Money } from '~/types/api'
import { absolute, compactMoney, gapState, hasActivity, signOf } from '~/utils/dashboard'

const lak = (amount: string): Money => ({ amount, currency: 'LAK' })

describe('compactMoney (axis ticks only)', () => {
  it.each([
    [150_000_000, 'LAK', '₭150M'],
    [1_500_000_000, 'LAK', '₭1,5B'],
    [999_999_999_999, 'LAK', '₭1T'],
    [0, 'LAK', '₭0'],
    [-25_000_000, 'LAK', '-₭25M'],
    [12_500, 'USD', '$12.5K'],
    [800, 'THB', '฿800']
  ] as const)('%d %s → %s', (value, currency, expected) => {
    expect(compactMoney(value, currency)).toBe(expected)
  })
})

describe('signOf', () => {
  it.each([
    ['150000000.00', 1],
    ['0.01', 1],
    ['-0.01', -1],
    ['-123456789012345678.99', -1],
    ['0.00', 0],
    ['-0.00', 0],
    ['0', 0]
  ] as const)('%s → %d', (amount, sign) => {
    expect(signOf(amount)).toBe(sign)
  })

  it('refuses anything that is not a decimal string', () => {
    expect(() => signOf('1,000')).toThrow(TypeError)
  })
})

describe('gaps', () => {
  it.each([
    ['25000000.00', 'needed'],
    ['-25000000.00', 'surplus'],
    ['0.00', 'balanced']
  ] as const)('%s is %s', (amount, state) => {
    expect(gapState(lak(amount))).toBe(state)
  })

  it('shows the amount without its sign, keeping every digit', () => {
    expect(absolute(lak('-123456789012345678.49'))).toEqual(lak('123456789012345678.49'))
    expect(absolute(lak('5.00'))).toEqual(lak('5.00'))
  })
})

describe('hasActivity', () => {
  function dashboard(total: string, committed: string, paid: string): Dashboard {
    return {
      summary: {
        revenue: { expected: lak('0.00'), received: lak('0.00'), total: lak(total) },
        expenses: { estimated: lak('0.00'), actual: lak('0.00'), paid: lak(paid), committed: lak(committed) }
      }
    } as Dashboard
  }

  it.each([
    ['0.00', '0.00', '0.00', false],
    ['1000.00', '0.00', '0.00', true],
    ['0.00', '1000.00', '0.00', true],
    // Cancelled expense whose deposit was forfeited.
    ['0.00', '0.00', '1000.00', true]
  ] as const)('revenue %s, committed %s, paid %s → %s', (total, committed, paid, expected) => {
    expect(hasActivity(dashboard(total, committed, paid))).toBe(expected)
  })
})
