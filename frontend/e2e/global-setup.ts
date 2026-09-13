/**
 * Fails fast and legibly when the world isn't ready, instead of letting five
 * specs time out one by one: the dev server must be up, the API must answer,
 * and the database must actually hold the fixture the manifest describes.
 *
 * Seeding itself happens on the host in ../../scripts/e2e.sh — this container
 * has no PHP and no access to the Docker socket.
 */
import type { FullConfig } from '@playwright/test'
import { loadFixture } from './fixture'

const API_URL = process.env.E2E_API_URL ?? 'http://web:80'
const READY_TIMEOUT_MS = 60_000
const POLL_INTERVAL_MS = 500

async function waitForOk(url: string, label: string): Promise<Response> {
  const deadline = Date.now() + READY_TIMEOUT_MS
  let lastError = 'no attempt made'

  while (Date.now() < deadline) {
    try {
      const res = await fetch(url)
      if (res.ok) return res
      lastError = `HTTP ${res.status}`
    } catch (e) {
      lastError = e instanceof Error ? e.message : String(e)
    }

    await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL_MS))
  }

  throw new Error(
    `Timed out after ${READY_TIMEOUT_MS / 1000}s waiting for ${label} at ${url} (${lastError}). ` +
      'Is `docker compose up -d` running?',
  )
}

export default async function globalSetup(config: FullConfig): Promise<void> {
  const fixture = loadFixture()
  const baseURL = config.projects[0]?.use.baseURL

  if (baseURL === undefined) {
    throw new Error('No baseURL configured for the chromium project.')
  }

  await waitForOk(baseURL, 'the Vite dev server')

  const weekStart = fixture.anchor_week.monday
  const res = await waitForOk(`${API_URL}/api/calendar?week_start=${weekStart}`, 'the API')
  const week = (await res.json()) as { bookings: { band_name: string | null }[] }
  const bandNames = new Set(week.bookings.map((b) => b.band_name))

  // The manifest is written by the same command that seeds, so a mismatch here
  // means something reseeded the database afterwards (seed_september.php, say).
  const expected = fixture.bookings.conflict_target.band
  if (!bandNames.has(expected)) {
    throw new Error(
      `Week ${weekStart} does not contain the seeded "${expected}" booking — the database ` +
        'no longer matches the fixture manifest. Rerun ./scripts/e2e.sh.',
    )
  }
}
