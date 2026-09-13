/**
 * Locators and actions for the rehearsal calendar, shared by the specs.
 *
 * Everything the app gives an accessible name — buttons, the dialog, form
 * fields — is reached by role or label. The week view's day columns and
 * occurrence blocks are the exception: they carry no role, so this file is
 * the one place allowed to reach for their classes. Keep that coupling here.
 */
import { expect, type Locator, type Page } from '@playwright/test'
import { mkdir } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { dayOfMonth } from './fixture'

const SCREENSHOT_DIR = fileURLToPath(new URL('../e2e-screenshots/', import.meta.url))

/** Height in px of one hour row in the week view, from RehearsalCalendar's `.t-label`/`.slot`. */
const HOUR_HEIGHT = 48

/** Opens the app and switches from the default month view to the week containing today. */
export async function openWeekView(page: Page): Promise<void> {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Ovelokale' })).toBeVisible()
  await page.getByRole('button', { name: 'Uge', exact: true }).click()
  await expect(weekBadge(page)).toBeVisible()
}

/** The "Uge 37" badge next to the period heading — the week view's identity. */
export function weekBadge(page: Page): Locator {
  return page.getByText(/^Uge \d+$/)
}

export async function goToNextWeek(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Næste uge' }).click()
}

/** One day column of the week view; 0 = Monday .. 6 = Sunday. */
export function dayColumn(page: Page, dayIndex: number): Locator {
  return page.locator('.w-col').nth(dayIndex)
}

/** Every occurrence drawn in a day column. */
export function occurrencesIn(page: Page, dayIndex: number): Locator {
  return dayColumn(page, dayIndex).locator('.block')
}

/** Occurrences the calendar marks as overlapping (one room, so any overlap is a data error). */
export function conflictingOccurrencesIn(page: Page, dayIndex: number): Locator {
  return dayColumn(page, dayIndex).locator('.block.clash')
}

/** The occurrence a given band has in a given day column. */
export function occurrenceFor(page: Page, dayIndex: number, bandName: string): Locator {
  return dayColumn(page, dayIndex).getByRole('button', { name: bandName })
}

/** The empty hour slot a user clicks to start a booking. */
export function freeSlot(page: Page, dayIndex: number, date: string, hour: number): Locator {
  return dayColumn(page, dayIndex).getByRole('button', {
    name: `Book ${dayOfMonth(date)}. kl. ${hour}`,
    exact: true,
  })
}

/* ---------- booking dialog ---------- */

export function dialog(page: Page): Locator {
  return page.getByRole('dialog', { name: 'Book øvelokale' })
}

/** The submit button — hard-disabled while any conflict exists (ADR 0002). */
export function bookButton(page: Page): Locator {
  return dialog(page).getByRole('button', { name: 'Book', exact: true })
}

export function conflictList(page: Page): Locator {
  return dialog(page).locator('.conflicts')
}

export async function chooseBand(page: Page, bandName: string): Promise<void> {
  await dialog(page).getByLabel('Band').selectOption({ label: bandName })
}

/** The Band select's first option — a booking that belongs to its booker, not a band. */
export const PERSONAL_BAND_OPTION = 'Ingen – personlig øvning'

export async function choosePersonalBooking(page: Page): Promise<void> {
  await dialog(page).getByLabel('Band').selectOption({ label: PERSONAL_BAND_OPTION })
}

export function bandOptions(page: Page): Locator {
  return dialog(page).getByLabel('Band').locator('option')
}

/** Switches the dialog between "Engangsbooking" and "Ugentlig gentagelse". */
export async function chooseMode(page: Page, label: string): Promise<void> {
  await dialog(page).getByRole('button', { name: label, exact: true }).click()
}

/**
 * The week body scrolls internally (it is capped at 620px but renders all 24
 * hours), so evening occurrences sit below the fold. Scroll them into frame
 * before capturing, or the screenshot shows an empty morning.
 */
export async function scrollWeekToHour(page: Page, hour: number): Promise<void> {
  await page
    .locator('.w-body')
    .evaluate((el, top) => el.scrollTo({ top, behavior: 'instant' }), hour * HOUR_HEIGHT)
}

/** Writes a PNG into the gitignored `frontend/e2e-screenshots/` directory and returns its path. */
export async function capture(page: Page, name: string): Promise<string> {
  await mkdir(SCREENSHOT_DIR, { recursive: true })
  const path = `${SCREENSHOT_DIR}${name}.png`
  await page.screenshot({ path, fullPage: true })

  return path
}
