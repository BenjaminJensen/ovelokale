/** Local-time YYYY-MM-DD, safe for the `week_start` query param (avoids the UTC day-shift `toISOString()` can cause near midnight). */
export function toDateParam(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** The Monday of `d`'s week, in local time. */
function mondayOf(d: Date): Date {
  const shift = (d.getDay() + 6) % 7
  return new Date(d.getFullYear(), d.getMonth(), d.getDate() - shift)
}

/**
 * Every Monday whose week overlaps `from` (inclusive) to `to` (exclusive) —
 * exactly the set of `week_start` values that need fetching.
 *
 * It starts from the Monday of `from`'s week rather than `from` itself. The
 * week and month views emit whole Mon-Sun ranges, but the phone's day view
 * emits a single day, and `/api/calendar` reads `week_start` as the first day
 * of the week it resolves recurring slots against: handed a Thursday, it
 * returns that Thursday's recurring occurrences dated on the following Sunday.
 */
export function mondaysInRange(from: Date, to: Date): Date[] {
  const mondays: Date[] = []
  let cursor = mondayOf(from)
  while (cursor < to) {
    mondays.push(cursor)
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 7)
  }
  return mondays
}
