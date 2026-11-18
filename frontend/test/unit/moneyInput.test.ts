import { describe, expect, it } from 'vitest'
import type { CurrencyCode } from '~/types/api'
import {
  amountPattern,
  applyTyping,
  normalizeValue,
  parsePastedAmount,
  toDisplay,
  type MoneyInputState
} from '~/utils/moneyInput'

/*
 * A tiny keyboard: each key is applied to the field text at the caret, like a browser does,
 * then MoneyInput's handler (applyTyping) regroups it.
 */
function press(state: MoneyInputState, key: string, currency: CurrencyCode): MoneyInputState {
  const { display, caret } = state

  if (key === 'Backspace') {
    if (caret === 0) {
      return state
    }

    return applyTyping(display.slice(0, caret - 1) + display.slice(caret), caret - 1, currency)
  }

  return applyTyping(display.slice(0, caret) + key + display.slice(caret), caret + key.length, currency)
}

function type(keys: string[] | string, currency: CurrencyCode, from: MoneyInputState = { display: '', value: '', caret: 0 }): MoneyInputState {
  return [...keys].reduce((state, key) => press(state, key, currency), from)
}

/** The field with the caret placed at `caret`. */
function at(display: string, caret: number): MoneyInputState {
  return { display, value: '', caret }
}

describe('typing', () => {
  it('groups LAK with dots as the user types, and sends plain digits', () => {
    expect(type('150000000', 'LAK')).toEqual({ display: '150.000.000', value: '150000000', caret: 11 })
  })

  it('groups USD with commas and keeps up to 2 decimals', () => {
    expect(type('4500.5', 'USD')).toEqual({ display: '4,500.5', value: '4500.5', caret: 7 })
    expect(type('4500.505', 'USD')).toMatchObject({ display: '4,500.50', value: '4500.50' })
  })

  it('ignores the decimal mark for LAK, which has no minor unit', () => {
    expect(type('1500.50', 'LAK')).toMatchObject({ display: '150.050', value: '150050' })
    expect(type('1500,50', 'LAK')).toMatchObject({ display: '150.050', value: '150050' })
  })

  it('accepts only one decimal mark', () => {
    expect(type('12.3.4', 'THB')).toMatchObject({ display: '12.34', value: '12.34' })
  })

  it('drops letters, signs and typed separators; grouping is automatic', () => {
    expect(type('-1a,2b3 4', 'USD')).toMatchObject({ display: '1,234', value: '1234' })
  })

  it('removes leading zeros and completes a leading decimal mark', () => {
    expect(type('0007', 'LAK')).toMatchObject({ display: '7', value: '7' })
    expect(type('.5', 'USD')).toEqual({ display: '0.5', value: '0.5', caret: 3 })
  })

  it('keeps the caret next to the same digit when a group separator appears', () => {
    // "1.000" with the caret after the first "1"; typing "2" makes "12.000".
    expect(press(at('1.000', 1), '2', 'LAK')).toEqual({ display: '12.000', value: '12000', caret: 2 })
  })

  it('keeps the caret in place when typing in the middle of a grouped number', () => {
    // "150.000" with the caret before the last "0": typing "5" gives "1.500.050".
    expect(press(at('150.000', 6), '5', 'LAK')).toEqual({ display: '1.500.050', value: '1500050', caret: 8 })
  })

  it('handles backspace across a group separator', () => {
    // Caret right after the separator in "1.000": backspace removes the separator only,
    // the regroup restores it, and the caret stays after the "1".
    expect(press(at('1.000', 2), 'Backspace', 'LAK')).toEqual({ display: '1.000', value: '1000', caret: 1 })
    // Caret at the end of "12.000": backspace leaves "1.200".
    expect(press(at('12.000', 6), 'Backspace', 'LAK')).toEqual({ display: '1.200', value: '1200', caret: 5 })
  })

  it('empties the value when everything is deleted', () => {
    expect(press(at('5', 1), 'Backspace', 'LAK')).toEqual({ display: '', value: '', caret: 0 })
  })
})

describe('very large LAK values', () => {
  const eighteen = '123456789012345678'

  it('keeps all 18 integer digits exactly', () => {
    const state = type(eighteen, 'LAK')

    expect(state.display).toBe('123.456.789.012.345.678')
    expect(state.value).toBe(eighteen)
    // Number would have rounded it: 123456789012345680.
    expect(String(Number(eighteen))).not.toBe(eighteen)
  })

  it('refuses a 19th digit, which the API column cannot store', () => {
    expect(type(`${eighteen}9`, 'LAK').value).toBe(eighteen)
  })

  it('formats an 18-digit API value for display', () => {
    expect(toDisplay(`${eighteen}.00`, 'LAK')).toBe('123.456.789.012.345.678')
  })
})

describe('pasting', () => {
  it.each([
    ['150.000.000', 'LAK', '150000000'],
    ['150,000,000', 'LAK', '150000000'],
    ['₭ 150 000 000', 'LAK', '150000000'],
    ['150000000 LAK', 'LAK', '150000000'],
    ['150.000.000,00', 'LAK', '150000000'],
    ['1,234.50', 'USD', '1234.50'],
    ['1.234,50', 'USD', '1234.50'],
    ['$4,500', 'USD', '4500'],
    ['4500.5', 'USD', '4500.5'],
    ['  30,000.00 THB ', 'THB', '30000.00'],
    ['฿628.4', 'THB', '628.4'],
    ['1500.', 'USD', '1500'],
    ['123.456.789.012.345.678', 'LAK', '123456789012345678'],
    ['', 'LAK', '']
  ] as const)('reads %j as %s %s', (text, currency, expected) => {
    expect(parsePastedAmount(text, currency)).toBe(expected)
  })

  it('reads no-break and narrow no-break spaces as group separators', () => {
    const nbsp = String.fromCharCode(0xA0)
    const narrow = String.fromCharCode(0x202F)

    expect(parsePastedAmount(`150${nbsp}000${narrow}000${nbsp}₭`, 'LAK')).toBe('150000000')
  })

  it.each([
    ['1500.50', 'LAK', 'kip has no minor unit; dropping 50 would change the amount'],
    ['1,50,000', 'LAK', 'groups must have 3 digits'],
    ['1.234.56', 'USD', 'two decimal marks'],
    ['12.3456', 'USD', '4 digits after the mark'],
    ['-500', 'USD', 'negative'],
    ['five hundred', 'USD', 'words'],
    ['1234567890123456789', 'LAK', '19 integer digits'],
    ['.', 'USD', 'no digits']
  ] as const)('refuses %j for %s (%s)', (text, currency) => {
    expect(parsePastedAmount(text, currency)).toBeNull()
  })
})

describe('display and API value', () => {
  it.each([
    ['150000000.00', 'LAK', '150.000.000'],
    ['4500', 'USD', '4,500.00'],
    ['4500.5', 'USD', '4,500.50'],
    ['628.40', 'THB', '628.40'],
    ['0.00', 'LAK', '0'],
    ['', 'USD', '']
  ] as const)('shows %s %s as %j', (value, currency, display) => {
    expect(toDisplay(value, currency)).toBe(display)
  })

  it('keeps a fraction the currency cannot hold visible, so validation can flag it', () => {
    expect(toDisplay('1500.50', 'LAK')).toBe('1.500,50')
    expect(normalizeValue('1500.50', 'LAK')).toBe('1500.50')
    expect(amountPattern('LAK').test('1500.50')).toBe(false)
  })

  it.each([
    ['150000000.00', 'LAK', '150000000'],
    ['4500.5', 'USD', '4500.50'],
    ['0045', 'THB', '45'],
    ['', 'LAK', '']
  ] as const)('normalizes %s %s to %j for the API', (value, currency, expected) => {
    expect(normalizeValue(value, currency)).toBe(expected)
  })

  it('validates against the currency: whole kip, up to 2 decimals otherwise', () => {
    expect(amountPattern('LAK').test('150000000')).toBe(true)
    expect(amountPattern('LAK').test('150000000.5')).toBe(false)
    expect(amountPattern('USD').test('4500.50')).toBe(true)
    expect(amountPattern('USD').test('4500.505')).toBe(false)
    expect(amountPattern('THB').test('1234567890123456789')).toBe(false)
  })
})
