import type { IsoDate } from '~/types/api'

/** The couple's calendar, as on the API (DashboardSummaryService::TIMEZONE). */
export const APP_TIMEZONE = 'Asia/Vientiane'

/**
 * Today's date in Vientiane as "YYYY-MM-DD", whatever the device's time zone: at 00:30 in
 * Vientiane a device set to UTC still shows yesterday, but rates and due dates follow Laos.
 */
export function todayInAppTimezone(now: Date = new Date()): IsoDate {
  // en-CA formats as YYYY-MM-DD.
  return new Intl.DateTimeFormat('en-CA', { timeZone: APP_TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now)
}

/**
 * A date-only API value as a Date for display. Noon keeps it on the same calendar day in
 * every time zone between UTC-11 and UTC+11.
 */
export function dateForDisplay(date: IsoDate): Date {
  return new Date(`${date}T12:00:00`)
}
