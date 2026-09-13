import { expect, test } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  bookButton,
  capture,
  conflictList,
  dialog,
  freeSlot,
  openWeekView,
  scrollWeekToHour,
} from './calendar-page'

const fixture = loadFixture()

test('extending a booking into an existing occurrence hard-disables "Book"', async ({ page }) => {
  const occupied = fixture.bookings.conflict_target
  const occupiedFrom = Number(occupied.start.slice(0, 2))
  const hour = (h: number): string => `${String(h).padStart(2, '0')}:00`

  // Start from the hour *before* the seeded booking. The occurrence itself is
  // drawn on top of its own hour slots, so an occupied hour cannot be clicked
  // — a user reaches a conflict by extending a free slot into one.
  await openWeekView(page)
  await freeSlot(page, occupied.day_index, occupied.date, occupiedFrom - 1).click()
  await expect(dialog(page)).toBeVisible()

  // An hour that merely abuts the booking is not a conflict.
  await expect(bookButton(page)).toBeEnabled()
  await expect(conflictList(page)).toHaveCount(0)

  await dialog(page)
    .getByLabel('Til', { exact: true })
    .fill(hour(occupiedFrom + 1))

  await expect(conflictList(page)).toContainText('Denne tid er optaget')
  await expect(conflictList(page)).toContainText(occupied.band)
  await expect(conflictList(page)).toContainText(occupied.start)

  // Per ADR 0002 there is no submit-anyway path: the button is disabled, full stop.
  await expect(bookButton(page)).toBeDisabled()

  await scrollWeekToHour(page, 12)
  await capture(page, 'booking-dialog-conflict')
})
