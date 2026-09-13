import { expect, test } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  PERSONAL_BAND_OPTION,
  bandOptions,
  bookButton,
  capture,
  chooseMode,
  choosePersonalBooking,
  conflictList,
  dialog,
  freeSlot,
  occurrenceFor,
  openWeekView,
  scrollWeekToHour,
} from './calendar-page'

const fixture = loadFixture()

/** Per seed_e2e.php, user id 1 (App\User\CurrentUser::ID) is the stand-in booker. */
const BOOKER = 'Testbruger Et'

/**
 * The free ad-hoc day, but a later hour than booking-create.spec.ts uses, so
 * the two mutating specs never overlap in the shared database.
 */
const free = fixture.free.adhoc
const HOUR = 14

test('the personal option is offered in both booking modes', async ({ page }) => {
  await openWeekView(page)
  await freeSlot(page, free.day_index, free.date, HOUR).click()
  await expect(dialog(page)).toBeVisible()

  // A native select renders its option list as browser chrome, which no
  // screenshot captures — so select the option and shoot the closed select,
  // which does show the chosen label.
  const bandSelect = dialog(page).getByLabel('Band')

  // Engangsbooking (the default mode).
  await expect(bandOptions(page).filter({ hasText: PERSONAL_BAND_OPTION })).toHaveCount(1)
  await choosePersonalBooking(page)
  await expect(bandSelect).toHaveValue(PERSONAL_BAND_OPTION)
  await capture(page, 'personal-option-adhoc')

  await chooseMode(page, 'Ugentlig gentagelse')
  await expect(bandOptions(page).filter({ hasText: PERSONAL_BAND_OPTION })).toHaveCount(1)
  await choosePersonalBooking(page)
  await expect(bandSelect).toHaveValue(PERSONAL_BAND_OPTION)
  await capture(page, 'personal-option-recurring')
})

test('a personal booking appears on the calendar under the bookers name', async ({ page }) => {
  await openWeekView(page)
  await expect(occurrenceFor(page, free.day_index, BOOKER)).toHaveCount(0)

  await freeSlot(page, free.day_index, free.date, HOUR).click()
  await expect(dialog(page)).toBeVisible()
  await choosePersonalBooking(page)

  await expect(conflictList(page)).toHaveCount(0)
  await expect(bookButton(page)).toBeEnabled()

  await bookButton(page).click()
  await expect(dialog(page)).toHaveCount(0)

  const occurrence = occurrenceFor(page, free.day_index, BOOKER)
  await expect(occurrence).toBeVisible()
  // The neutral grey RehearsalCalendar.band() falls back to when there is no
  // band to colour the occurrence by.
  await expect(occurrence).toHaveAttribute('style', /#6B7280/i)

  await scrollWeekToHour(page, 11)
  await capture(page, 'personal-occurrence')
})
