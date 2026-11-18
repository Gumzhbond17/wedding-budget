import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import en from '../../i18n/locales/en.json'
import lo from '../../i18n/locales/lo.json'

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
    expect(dynamicPrefixes).toEqual(new Set(['weddings.status', 'currency']))
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
  })

  it('has no Lao key that English lacks', () => {
    const english = new Set(leafKeys(en))

    expect(leafKeys(lo).filter(key => key !== '' && !english.has(key))).toEqual([])
  })
})
