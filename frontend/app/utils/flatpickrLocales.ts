import type { CustomLocale } from 'flatpickr/dist/types/locale'

/**
 * Lao strings for flatpickr, which ships no Lao locale. English is flatpickr's built-in
 * default, so it needs no entry here.
 */
export const flatpickrLao: CustomLocale = {
  weekdays: {
    shorthand: ['ອາ', 'ຈ', 'ອ', 'ພ', 'ພຫ', 'ສຸ', 'ສ'],
    longhand: ['ອາທິດ', 'ຈັນ', 'ອັງຄານ', 'ພຸດ', 'ພະຫັດ', 'ສຸກ', 'ເສົາ']
  },
  months: {
    shorthand: ['ມ.ກ.', 'ກ.ພ.', 'ມ.ນ.', 'ມ.ສ.', 'ພ.ພ.', 'ມິ.ຖ.', 'ກ.ລ.', 'ສ.ຫ.', 'ກ.ຍ.', 'ຕ.ລ.', 'ພ.ຈ.', 'ທ.ວ.'],
    longhand: ['ມັງກອນ', 'ກຸມພາ', 'ມີນາ', 'ເມສາ', 'ພຶດສະພາ', 'ມິຖຸນາ', 'ກໍລະກົດ', 'ສິງຫາ', 'ກັນຍາ', 'ຕຸລາ', 'ພະຈິກ', 'ທັນວາ']
  },
  firstDayOfWeek: 0,
  rangeSeparator: ' ຫາ ',
  weekAbbreviation: 'ອາທິດ',
  scrollTitle: 'ເລື່ອນເພື່ອປ່ຽນ',
  toggleTitle: 'ຄລິກເພື່ອສະຫຼັບ',
  time_24hr: true
}

/** flatpickr locale for an app locale code; undefined keeps flatpickr's English default. */
export function flatpickrLocale(code: string): CustomLocale | undefined {
  return code === 'lo' ? flatpickrLao : undefined
}
