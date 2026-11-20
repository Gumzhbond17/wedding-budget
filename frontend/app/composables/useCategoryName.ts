/**
 * The seeded default category names (backend CategoryType::defaultNames) mapped to their
 * translation keys under `categoryNames` in the locale files.
 *
 * Why translate on display instead of storing a translated name: each wedding owns editable
 * copies of these rows, saved in English, and the API stays language-neutral (CSV exports,
 * other clients). Matching by exact name keeps the data model unchanged. A renamed or custom
 * category no longer matches and shows exactly as the user typed it, which is what they expect.
 */
export const DEFAULT_CATEGORY_KEYS: Record<string, string> = {
  'Family Contribution': 'familyContribution',
  'Guest Gifts': 'guestGifts',
  'Personal Savings': 'personalSavings',
  'Sponsorship': 'sponsorship',
  'Loan': 'loan',
  'Other': 'other',
  'Venue': 'venue',
  'Catering': 'catering',
  'Attire': 'attire',
  'Photography & Video': 'photographyVideo',
  'Decoration & Flowers': 'decorationFlowers',
  'Music & Entertainment': 'musicEntertainment',
  'Invitations': 'invitations',
  'Rings & Jewelry': 'ringsJewelry',
  'Transportation': 'transportation',
  'Ceremony': 'ceremony',
  'Honeymoon': 'honeymoon',
  'Miscellaneous': 'miscellaneous'
}

/** Returns a function giving a category's display name in the current locale. */
export function useCategoryName(): (name: string | null | undefined) => string {
  const { t } = useI18n()

  return (name) => {
    if (!name) {
      return ''
    }

    const key = DEFAULT_CATEGORY_KEYS[name]

    return key ? t(`categoryNames.${key}`) : name
  }
}
