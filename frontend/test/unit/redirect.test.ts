import { describe, expect, it } from 'vitest'
import { safeRedirect } from '~/utils/redirect'

describe('safeRedirect', () => {
  it('keeps a path inside the app', () => {
    expect(safeRedirect('/weddings/3?tab=expenses')).toBe('/weddings/3?tab=expenses')
  })

  it.each([
    'https://evil.example',
    '//evil.example',
    '/\\evil.example',
    'javascript:alert(1)',
    'weddings',
    undefined,
    ['/weddings']
  ])('falls back for %j', (target) => {
    expect(safeRedirect(target)).toBe('/weddings')
  })
})
