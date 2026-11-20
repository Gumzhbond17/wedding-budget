import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import en from '../../i18n/locales/en.json'
import lo from '../../i18n/locales/lo.json'
import { DEFAULT_CATEGORY_KEYS } from '../../app/composables/useCategoryName'
import { GUEST_GROUPS } from '../../app/utils/guestGroups'

/*
 * Every translation key the app uses must exist in en.json (the fallback for every
 * language). Runtime missing-key warnings are off while lo.json is being filled in, so
 * this is the check that catches a typo or a forgotten key.
 */

const appDir = fileURLToPath(new URL('../../app', import.meta.url))

function sourceFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name)

    if (entry.isDirectory()) {
      return sourceFiles(path)
    }

    return /\.(vue|ts)$/.test(entry.name) ? [path] : []
  })
}

function lookup(messages: unknown, key: string): unknown {
  return key.split('.').reduce<unknown>(
    (node, segment) => (node !== null && typeof node === 'object' ? (node as Record<string, unknown>)[segment] : undefined),
    messages
  )
}

function leafKeys(messages: unknown, prefix = ''): string[] {
  if (messages === null || typeof messages !== 'object') {
    return [prefix]
  }

  return Object.entries(messages).flatMap(([key, value]) => leafKeys(value, prefix ? `${prefix}.${key}` : key))
}

const sources = sourceFiles(appDir).map(path => ({ path, code: readFileSync(path, 'utf8') }))

// t('a.b'), $t("a.b"), and keys handed around as values, e.g. errorKey() returning 'errors.network'.
const staticKeys = new Set(sources.flatMap(({ code }) => [
  ...[...code.matchAll(/\$?\bt\(\s*['"]([\w.]+)['"]/g)].map(match => match[1]!),
  ...[...code.matchAll(/['"](errors\.\w+)['"]/g)].map(match => match[1]!)
]))

// t(`weddings.status.${status}`): the prefix must be a group of messages.
const dynamicPrefixes = new Set(sources.flatMap(({ code }) =>
  [...code.matchAll(/\$?\bt\(\s*`([\w.]+)\.\$\{/g)].map(match => match[1]!)
))

describe('translation keys', () => {
  it('finds the keys used in app/', () => {
    expect(staticKeys.size).toBeGreaterThan(30)
    expect(dynamicPrefixes).toEqual(new Set(['weddings.status', 'currency', 'categories.form.createTitle', 'status', 'paymentMethod', 'dashboard.health', 'dashboard.gap', 'dashboard.perCurrency', 'dashboard.start', 'categoryNames', 'guestGroup', 'giftEnvelopes.sort']))
  })

  it('translates exactly the default categories the backend seeds', () => {
    // The display lookup matches stored names, so it must follow CategoryType::defaultNames().
    const enumSource = readFileSync(fileURLToPath(new URL('../../../backend/app/Enums/CategoryType.php', import.meta.url)), 'utf8')
    const seeded = [...enumSource.matchAll(/^\s+'([^']+)',$/gm)].map(match => match[1]!).sort()

    expect(Object.keys(DEFAULT_CATEGORY_KEYS).sort()).toEqual(seeded)
    // English shows the stored name unchanged.
    expect(Object.entries(DEFAULT_CATEGORY_KEYS).every(([name, key]) => lookup(en, `categoryNames.${key}`) === name)).toBe(true)
    expect(Object.keys(lookup(en, 'categoryNames') as object).sort()).toEqual(Object.values(DEFAULT_CATEGORY_KEYS).sort())
  })

  it.each([...staticKeys].sort())('en.json has "%s"', (key) => {
    expect(typeof lookup(en, key)).toBe('string')
  })

  it.each([...dynamicPrefixes].sort())('en.json has the "%s" group', (prefix) => {
    expect(typeof lookup(en, prefix)).toBe('object')
  })

  it('gives every wedding status and currency a label', () => {
    expect(Object.keys(lookup(en, 'weddings.status') as object).sort()).toEqual(['completed', 'planning'])
    expect(Object.keys(lookup(en, 'currency') as object).sort()).toEqual(['LAK', 'THB', 'USD'])
    expect(Object.keys(lookup(en, 'categories.form.createTitle') as object).sort()).toEqual(['expense', 'revenue'])
    // Every API status (ExpenseStatus, RevenueStatus) and payment method has a label.
    expect(Object.keys(lookup(en, 'status') as object).sort())
      .toEqual(['booked', 'cancelled', 'expected', 'paid', 'partially_paid', 'planned', 'received'])
    expect(Object.keys(lookup(en, 'paymentMethod') as object).sort()).toEqual(['bank_transfer', 'cash', 'none', 'other'])
    expect(Object.keys(lookup(en, 'guestGroup') as object).sort()).toEqual([...GUEST_GROUPS].sort())
  })

  // Top-level keys starting with "_" are file metadata (JSON has no comments), e.g. the draft note.
  const laoKeys = leafKeys(lo).filter(key => key !== '' && !key.startsWith('_'))

  it('has no Lao key that English lacks', () => {
    const english = new Set(leafKeys(en))

    expect(laoKeys.filter(key => !english.has(key))).toEqual([])
  })

  it('translates every English key into Lao', () => {
    const lao = new Set(laoKeys)

    expect(leafKeys(en).filter(key => !lao.has(key))).toEqual([])
  })

  it.each(leafKeys(en))('keeps the placeholders of "%s" in Lao', (key) => {
    const placeholders = (text: unknown) => [...String(text).matchAll(/\{(\w+)\}/g)].map(match => match[1]).sort()

    expect(placeholders(lookup(lo, key))).toEqual(placeholders(lookup(en, key)))
  })
})
