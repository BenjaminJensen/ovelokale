/**
 * Typed access to the fixture manifest written by
 * `backend/scripts/seed_e2e.php` (via ../../scripts/e2e.sh).
 *
 * The specs read dates from here rather than recomputing "the Monday of this
 * week" in TypeScript: the seed already did that arithmetic, in the same
 * timezone, and duplicating it is how the two sides drift apart.
 */
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

export type WeekParity = 'odd' | 'even' | 'all'

export interface SeededWeek {
  monday: string
  iso_week: number
  parity: WeekParity
  /** The week's seven dates, `Y-m-d`, Monday first. */
  dates: string[]
}

export interface SeededBooking {
  band: string
  /** 0 = Monday .. 6 = Sunday, matching the week view's day columns. */
  day_index: number
  date: string
  start: string
  end: string
}

export interface SeededRecurringSlot {
  band: string
  day_index: number
  start: string
  end: string
  parity: WeekParity
  start_date: string
  end_date: string | null
}

export interface FreeSlot {
  day_index: number
  date: string
  hour: number
  /** A band with nothing else booked that day, so the created occurrence is unambiguous. */
  band: string
}

export interface Fixture {
  schema: number
  timezone: string
  anchor_week: SeededWeek
  next_week: SeededWeek
  bands: { id: number; name: string; color: string }[]
  own_band: string
  bookings: Record<
    'conflict_target' | 'midweek' | 'clash_early' | 'clash_late' | 'next_week_marker',
    SeededBooking
  >
  recurring: Record<'bounded' | 'anchor_parity' | 'other_parity', SeededRecurringSlot>
  free: Record<'adhoc' | 'recurring', FreeSlot>
}

const SCHEMA = 1

export const FIXTURE_PATH = fileURLToPath(new URL('./.fixture.json', import.meta.url))

export function loadFixture(): Fixture {
  let raw: string

  try {
    raw = readFileSync(FIXTURE_PATH, 'utf8')
  } catch {
    throw new Error(
      `No fixture manifest at ${FIXTURE_PATH}. Run the suite via ./scripts/e2e.sh, ` +
        'which seeds the database and writes the manifest before starting Playwright.',
    )
  }

  const fixture = JSON.parse(raw) as Fixture

  if (fixture.schema !== SCHEMA) {
    throw new Error(
      `Fixture manifest is schema ${fixture.schema}, expected ${SCHEMA}. Reseed with ./scripts/e2e.sh.`,
    )
  }

  return fixture
}

/** The day-of-month the week view puts in a slot's `aria-label`. */
export function dayOfMonth(date: string): number {
  return Number(date.slice(8, 10))
}

/** The Danish label the booking dialog shows for a week parity. */
export const PARITY_LABELS: Record<WeekParity, string> = {
  all: 'Hver uge',
  odd: 'Ulige uger',
  even: 'Lige uger',
}
