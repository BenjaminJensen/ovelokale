import { expect, test } from '@playwright/test'
import { PARITY_LABELS, loadFixture } from './fixture'
import {
  bookButton,
  capture,
  chooseBand,
  conflictList,
  dialog,
  freeSlot,
  goToNextWeek,
  occurrenceFor,
  openWeekView,
  scrollWeekToHour,
} from './calendar-page'

const fixture = loadFixture()

test('a recurring slot only produces occurrences inside its parity and its date bounds', async ({
  page,
}) => {
  const free = fixture.free.recurring
  // Ends on the last day of the *following* week, so three weeks tell the two
  // rules apart: this week matches the parity and is in bounds, next week is in
  // bounds but the wrong parity, the week after matches the parity again but
  // falls past the end date.
  const endDate = fixture.next_week.dates[6]!

  await openWeekView(page)
  await freeSlot(page, free.day_index, free.date, free.hour).click()
  await expect(dialog(page)).toBeVisible()

  await dialog(page).getByRole('button', { name: 'Ugentlig gentagelse' }).click()
  await chooseBand(page, free.band)
  await dialog(page).getByLabel('Slutdato').fill(endDate)
  await dialog(page)
    .getByLabel('Gentagelse')
    .selectOption({ label: PARITY_LABELS[fixture.anchor_week.parity] })

  await expect(conflictList(page)).toHaveCount(0)
  await expect(bookButton(page)).toBeEnabled()
  await capture(page, 'recurring-dialog')

  await bookButton(page).click()
  await expect(dialog(page)).toHaveCount(0)

  // In bounds, parity matches.
  await expect(occurrenceFor(page, free.day_index, free.band)).toBeVisible()
  await scrollWeekToHour(page, 6)
  await capture(page, 'recurring-created')

  // In bounds, parity does not match.
  await goToNextWeek(page)
  await expect(occurrenceFor(page, free.day_index, free.band)).toHaveCount(0)

  // Parity matches again, but past the end date.
  await goToNextWeek(page)
  await expect(occurrenceFor(page, free.day_index, free.band)).toHaveCount(0)
})
