const dateFormats = {
  short: { year: 'numeric', month: 'short', day: 'numeric' },
  long: { year: 'numeric', month: 'long', day: 'numeric' }
} as const

export default defineI18nConfig(() => ({
  // Lao is filled in gradually: a key missing in lo.json shows the English text.
  fallbackLocale: 'en',
  /*
   * vue-i18n warns for every key missing in the active locale, even when English fills
   * in, which floods the console while lo.json is incomplete. A key missing from en.json
   * (a real bug) is caught by test/unit/i18n.test.ts instead.
   */
  missingWarn: false,
  fallbackWarn: false,
  datetimeFormats: {
    en: dateFormats,
    lo: dateFormats
  }
}))
