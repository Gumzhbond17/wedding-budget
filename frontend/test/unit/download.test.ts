import { describe, expect, it } from 'vitest'
import { fileNameFromDisposition } from '~/utils/download'

describe('fileNameFromDisposition', () => {
  it.each([
    ['UTF-8 name wins over the ASCII fallback', 'attachment; filename=wedding-3-expenses-2026-11-20.csv; filename*=utf-8\'\'%E0%BA%87%E0%BA%B2%E0%BA%99-expenses-2026-11-20.csv', 'ງານ-expenses-2026-11-20.csv'],
    ['plain token', 'attachment; filename=vanh-noy-revenues-2026-11-20.csv', 'vanh-noy-revenues-2026-11-20.csv'],
    ['quoted with an escaped quote', 'attachment; filename="say \\"hi\\".csv"', 'say "hi".csv'],
    ['malformed encoding falls back', 'attachment; filename=fallback.csv; filename*=utf-8\'\'%E0%ZZ.csv', 'fallback.csv'],
    ['path separators are stripped', 'attachment; filename="../../etc/passwd"', '....etcpasswd']
  ])('%s', (_label, header, expected) => {
    expect(fileNameFromDisposition(header)).toBe(expected)
  })

  it.each([null, '', 'attachment', 'attachment; filename=""', 'attachment; filename=".."'])('has no name in %j', (header) => {
    expect(fileNameFromDisposition(header)).toBeNull()
  })
})
