import { describe, expect, it } from 'vitest'
import { mondaysInRange, toDateParam } from '../../src/utils/weeks'

describe('toDateParam', () => {
  it('formats a local date as YYYY-MM-DD', () => {
    expect(toDateParam(new Date(2026, 8, 7))).toBe('2026-09-07')
  })

  it('pads single-digit months and days', () => {
    expect(toDateParam(new Date(2026, 0, 3))).toBe('2026-01-03')
  })
})

describe('mondaysInRange', () => {
  it('returns a single Monday for a one-week range', () => {
    const from = new Date(2026, 8, 7) // Monday
    const to = new Date(2026, 8, 14) // following Monday, exclusive
    expect(mondaysInRange(from, to).map(toDateParam)).toEqual(['2026-09-07'])
  })

  it("snaps back to the week's Monday when the range starts mid-week", () => {
    // The phone day view emits one day, and `/api/calendar` reads `week_start`
    // as the Monday it resolves recurring slots against — a Thursday there
    // dates every recurring occurrence three days late.
    const thursday = new Date(2026, 8, 10)
    const friday = new Date(2026, 8, 11)
    expect(mondaysInRange(thursday, friday).map(toDateParam)).toEqual(['2026-09-07'])
  })

  it('snaps a Sunday back to the Monday six days earlier, not the next one', () => {
    const sunday = new Date(2026, 8, 13)
    const monday = new Date(2026, 8, 14)
    expect(mondaysInRange(sunday, monday).map(toDateParam)).toEqual(['2026-09-07'])
  })

  it('steps every 7 days across a month-view range', () => {
    const from = new Date(2026, 8, 7)
    const to = new Date(2026, 9, 5)
    expect(mondaysInRange(from, to).map(toDateParam)).toEqual([
      '2026-09-07',
      '2026-09-14',
      '2026-09-21',
      '2026-09-28',
    ])
  })
})
