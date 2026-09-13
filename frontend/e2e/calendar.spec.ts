import { expect, test } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  capture,
  conflictingOccurrencesIn,
  occurrenceFor,
  occurrencesIn,
  openWeekView,
  recurringMarkerIn,
  scrollWeekToHour,
  weekBadge,
} from './calendar-page'

const fixture = loadFixture()

test('the week view draws every seeded occurrence in its own day column', async ({ page }) => {
  await openWeekView(page)
  await expect(weekBadge(page)).toHaveText(`Uge ${fixture.anchor_week.iso_week}`)

  const { conflict_target: adHocMonday, midweek } = fixture.bookings
  const { bounded, anchor_parity: anchorParity, other_parity: otherParity } = fixture.recurring

  // Ad-hoc bookings land on their own date.
  await expect(occurrenceFor(page, adHocMonday.day_index, adHocMonday.band)).toBeVisible()
  await expect(occurrencesIn(page, adHocMonday.day_index)).toHaveCount(1)

  await expect(occurrenceFor(page, midweek.day_index, midweek.band)).toBeVisible()
  await expect(occurrencesIn(page, midweek.day_index)).toHaveCount(1)

  // A recurring slot resolves to an occurrence on its weekday, inside its bounds.
  await expect(occurrenceFor(page, bounded.day_index, bounded.band)).toBeVisible()
  await expect(occurrencesIn(page, bounded.day_index)).toHaveCount(1)

  // Both parity slots share a weekday; only the one matching this week's parity shows.
  await expect(occurrenceFor(page, anchorParity.day_index, anchorParity.band)).toBeVisible()
  await expect(occurrenceFor(page, otherParity.day_index, otherParity.band)).toHaveCount(0)

  // The repeat marker separates the two sources at a glance.
  await expect(
    recurringMarkerIn(occurrenceFor(page, bounded.day_index, bounded.band)),
  ).toBeVisible()
  await expect(recurringMarkerIn(occurrenceFor(page, midweek.day_index, midweek.band))).toHaveCount(
    0,
  )

  await scrollWeekToHour(page, 12)
  await capture(page, 'week-view')
})

test('overlapping occurrences are marked as clashing', async ({ page }) => {
  const { clash_early: early, clash_late: late } = fixture.bookings

  await openWeekView(page)

  await expect(occurrenceFor(page, early.day_index, early.band)).toBeVisible()
  await expect(occurrenceFor(page, late.day_index, late.band)).toBeVisible()
  await expect(conflictingOccurrencesIn(page, early.day_index)).toHaveCount(2)

  await scrollWeekToHour(page, 12)
  await capture(page, 'week-view-clash')
})
