import { expect, test } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  bookButton,
  capture,
  chooseBand,
  conflictList,
  dialog,
  freeSlot,
  occurrenceFor,
  openWeekView,
  scrollWeekToHour,
} from './calendar-page'

const fixture = loadFixture()

test('booking a free slot creates an ad-hoc booking that appears on the calendar', async ({
  page,
}) => {
  const free = fixture.free.adhoc

  await openWeekView(page)
  await expect(occurrenceFor(page, free.day_index, free.band)).toHaveCount(0)

  await freeSlot(page, free.day_index, free.date, free.hour).click()
  await expect(dialog(page)).toBeVisible()
  await chooseBand(page, free.band)

  await expect(conflictList(page)).toHaveCount(0)
  await expect(bookButton(page)).toBeEnabled()
  await capture(page, 'booking-dialog-free')

  await bookButton(page).click()
  await expect(dialog(page)).toHaveCount(0)

  await expect(occurrenceFor(page, free.day_index, free.band)).toBeVisible()

  await scrollWeekToHour(page, 6)
  await capture(page, 'booking-created')
})
