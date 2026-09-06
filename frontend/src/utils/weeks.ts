/** Local-time YYYY-MM-DD, safe for the `week_start` query param (avoids the UTC day-shift `toISOString()` can cause near midnight). */
export function toDateParam(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/**
 * Every Monday from `from` (inclusive) up to `to` (exclusive), stepping 7
 * days at a time. RehearsalCalendar.vue's `range-change` always emits whole
 * Mon-Sun weeks in both week and month view, so this covers exactly the set
 * of `week_start` values that need fetching.
 */
export function mondaysInRange(from: Date, to: Date): Date[] {
  const mondays: Date[] = []
  let cursor = new Date(from.getFullYear(), from.getMonth(), from.getDate())
  while (cursor < to) {
    mondays.push(cursor)
    cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 7)
  }
  return mondays
}
