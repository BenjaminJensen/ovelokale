import { expect, test } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  capture,
  goToNextWeek,
  occurrenceFor,
  occurrencesIn,
  openWeekView,
  scrollWeekToHour,
  weekBadge,
} from './calendar-page'

const fixture = loadFixture()

test('stepping forward loads the neighbouring week', async ({ page }) => {
  const { conflict_target: anchorWeekOnly, next_week_marker: nextWeekOnly } = fixture.bookings
  const { bounded, anchor_parity: anchorParity, other_parity: otherParity } = fixture.recurring

  await openWeekView(page)
  await expect(occurrenceFor(page, anchorWeekOnly.day_index, anchorWeekOnly.band)).toBeVisible()

  await goToNextWeek(page)
  await expect(weekBadge(page)).toHaveText(`Uge ${fixture.next_week.iso_week}`)

  // The neighbouring week's own booking is fetched and drawn...
  await expect(occurrenceFor(page, nextWeekOnly.day_index, nextWeekOnly.band)).toBeVisible()
  // ...and the previous week's is no longer on screen.
  await expect(occurrencesIn(page, anchorWeekOnly.day_index)).toHaveCount(0)

  // The parity slots swap over, and the slot bounded by an end date is gone.
  await expect(occurrenceFor(page, otherParity.day_index, otherParity.band)).toBeVisible()
  await expect(occurrenceFor(page, anchorParity.day_index, anchorParity.band)).toHaveCount(0)
  await expect(occurrenceFor(page, bounded.day_index, bounded.band)).toHaveCount(0)

  await scrollWeekToHour(page, 12)
  await capture(page, 'week-view-next')
})
