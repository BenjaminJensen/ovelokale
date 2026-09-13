import { defineConfig, devices } from '@playwright/test'

/**
 * Runs inside the `playwright` compose service (see docker-compose.yml), which
 * reaches the Vite dev server and the PHP API over the compose network. The
 * suite is invoked through ../scripts/e2e.sh, which reseeds the database
 * first — see e2e/global-setup.ts.
 */
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  outputDir: './test-results',

  // One shared dev database and one rehearsal room: parallel workers would
  // see each other's bookings. Determinism beats speed for five specs.
  fullyParallel: false,
  workers: 1,
  retries: 0,

  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],

  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://frontend:5173',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // The UI formats dates and times with `da-DK` and does all its date
        // maths in local time; the seed script pins PHP to the same zone.
        locale: 'da-DK',
        timezoneId: 'Europe/Copenhagen',
        // Tall enough that a full week column fits without inner scrolling,
        // so screenshots show the whole calendar.
        viewport: { width: 1440, height: 1000 },
      },
    },
  ],
})
