/**
 * The same calendar at the three viewports it has to work on (issue #11).
 *
 * The phone block is the interesting one: below 640px the week view renders a
 * single day column with a day picker, and the booking dialog becomes a bottom
 * sheet. The tablet and desktop blocks exist to prove that neither of those
 * leaked upwards — an iPad gets the seven-column week, unchanged.
 */
import { expect, test, type Page } from '@playwright/test'
import { loadFixture } from './fixture'
import {
  bookButton,
  capture,
  chooseBand,
  chooseMode,
  dayColumns,
  dayPickerButton,
  dialog,
  freeSlotIn,
  hasHorizontalOverflow,
  monthChips,
  occurrenceIn,
  openDayView,
  openWeekView,
  recurringMarkerIn,
  scrollWeekToHour,
  selectedDayButton,
  shownDayColumn,
  todayDateButton,
  weekBadge,
} from './calendar-page'

const fixture = loadFixture()

const PHONE = { width: 390, height: 844 }
const TABLET = { width: 820, height: 1180 }
const DESKTOP = { width: 1440, height: 1000 }

/**
 * The free ad-hoc day again, at hours no other spec touches (9 is
 * booking-create's, 14 is personal-booking's), and with a band that has nothing
 * else there — the specs share one database and run in file order.
 */
const free = fixture.free.adhoc
const BOOKING_HOUR = 11
const REACHABILITY_HOUR = 12
const BAND = fixture.own_band

/** The day picker only offers the shown week, which is the week the fixture seeds. */
async function showDay(page: Page, dayIndex: number): Promise<void> {
  await dayPickerButton(page, dayIndex).click()
  await expect(dayPickerButton(page, dayIndex)).toHaveAttribute('aria-pressed', 'true')
}

test.describe('phone', () => {
  test.use({ viewport: PHONE })

  test('opens on a single-day view that fits the viewport', async ({ page }) => {
    await openDayView(page)

    await expect(dayColumns(page)).toHaveCount(1)
    await expect(weekBadge(page)).toHaveText(`Uge ${fixture.anchor_week.iso_week}`)
    await expect(page.getByRole('button', { name: 'Dag', exact: true })).toBeVisible()
    expect(await hasHorizontalOverflow(page)).toBe(false)

    await capture(page, 'phone-day-view')
  })

  test('the day picker moves the day view to another weekday', async ({ page }) => {
    const { midweek } = fixture.bookings

    await openDayView(page)
    await showDay(page, midweek.day_index)

    await expect(occurrenceIn(shownDayColumn(page), midweek.band)).toBeVisible()
    await expect(dayColumns(page)).toHaveCount(1)

    await scrollWeekToHour(page, 12)
    await capture(page, 'phone-day-occurrence')
  })

  test('the booking dialog is a bottom sheet whose Book button is reachable', async ({ page }) => {
    await openDayView(page)
    await showDay(page, free.day_index)
    await freeSlotIn(shownDayColumn(page), free.date, REACHABILITY_HOUR).click()

    await expect(dialog(page)).toBeVisible()
    await expect(bookButton(page)).toBeInViewport()
    await capture(page, 'phone-booking-dialog')

    // The recurring form is the taller of the two — the one that used to push
    // "Book" off the bottom of a phone screen.
    await chooseMode(page, 'Ugentlig gentagelse')
    await expect(dialog(page).getByLabel('Gentagelse')).toBeVisible()
    await expect(bookButton(page)).toBeInViewport()
    await capture(page, 'phone-recurring-dialog')

    await dialog(page).getByRole('button', { name: 'Annuller' }).click()
    await expect(dialog(page)).toHaveCount(0)
  })

  test('drills from the month view into one day', async ({ page }) => {
    await openDayView(page)
    await page.getByRole('button', { name: 'Måned', exact: true }).click()
    expect(await hasHorizontalOverflow(page)).toBe(false)
    await capture(page, 'phone-month-view')

    // The date number is a button because double-tap — the desktop's way into
    // a booking — is the browser's zoom gesture on a touch screen.
    const dayNumber = (await todayDateButton(page).innerText()).trim()
    await todayDateButton(page).click()

    await expect(dayColumns(page)).toHaveCount(1)
    await expect(selectedDayButton(page)).toContainText(dayNumber)
  })

  test('keeps the recurring marker in the day view and drops it from the month chips', async ({
    page,
  }) => {
    const recurring = fixture.recurring.bounded

    await openDayView(page)
    await showDay(page, recurring.day_index)

    const occurrence = occurrenceIn(shownDayColumn(page), recurring.band)
    await expect(recurringMarkerIn(occurrence)).toBeVisible()

    await scrollWeekToHour(page, 15)
    await capture(page, 'phone-recurring-occurrence')

    await page.getByRole('button', { name: 'Måned', exact: true }).click()

    // Rendered but hidden: a month chip at this width has barely room for a
    // truncated band name, so the marker gives its pixels up to the name.
    const markers = recurringMarkerIn(monthChips(page))
    expect(await markers.count()).toBeGreaterThan(0)
    await expect(markers.first()).toBeHidden()
  })

  test('books a free slot from the day view', async ({ page }) => {
    await openDayView(page)
    await showDay(page, free.day_index)

    const column = shownDayColumn(page)
    await expect(occurrenceIn(column, BAND)).toHaveCount(0)

    await freeSlotIn(column, free.date, BOOKING_HOUR).click()
    await chooseBand(page, BAND)
    await expect(bookButton(page)).toBeEnabled()
    await bookButton(page).click()

    await expect(dialog(page)).toHaveCount(0)
    await expect(occurrenceIn(shownDayColumn(page), BAND)).toBeVisible()

    await scrollWeekToHour(page, 9)
    await capture(page, 'phone-booking-created')
  })
})

test.describe('tablet', () => {
  test.use({ viewport: TABLET })

  test('keeps the seven-column week view', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { name: 'Ovelokale' })).toBeVisible()
    expect(await hasHorizontalOverflow(page)).toBe(false)
    await capture(page, 'tablet-month-view')

    await openWeekView(page)

    await expect(dayColumns(page)).toHaveCount(7)
    expect(await hasHorizontalOverflow(page)).toBe(false)

    await scrollWeekToHour(page, 12)
    await capture(page, 'tablet-week-view')
  })
})

test.describe('desktop', () => {
  test.use({ viewport: DESKTOP })

  test('is unchanged', async ({ page }) => {
    await openWeekView(page)

    await expect(dayColumns(page)).toHaveCount(7)
    await expect(page.getByRole('button', { name: 'Uge', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Forrige uge' })).toBeVisible()
    expect(await hasHorizontalOverflow(page)).toBe(false)

    await scrollWeekToHour(page, 12)
    await capture(page, 'desktop-week-view')
  })
})
