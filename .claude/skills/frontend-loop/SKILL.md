---
name: frontend-loop
description: Drive the Vue SPA in a real browser and read the screenshots back, to verify frontend work visually instead of guessing from jsdom assertions. Use when changing anything the user sees — the calendar, the booking dialog, layout, styling — or when a change looks right in unit tests but you have not seen it render.
---

# Frontend loop

Vitest renders into jsdom: no layout, no paint, no scrolling. It will happily
confirm that a conflict disabled "Book" in a dialog that, in a real browser,
overflows its container and puts the button off screen. This loop closes that
gap — drive the running app in Chromium, capture PNGs, look at them.

**Never claim a visual change works without having looked at a screenshot of
it.** The whole point of this loop is that the assertion and the appearance are
different questions.

## The loop

### 1. Check the stack is up

```
docker compose ps
```

`web`, `frontend` and `db` must be running. If not: `docker compose up -d`.

### 2. Run the suite

```
./scripts/e2e.sh
```

This reseeds the database with the deterministic fixture, then runs every spec
in `frontend/e2e/`. It takes a few seconds. Narrow it while iterating:

```
./scripts/e2e.sh e2e/booking-create.spec.ts
./scripts/e2e.sh --grep "conflict"
```

**The seed wipes every booking, recurring slot, band and user in the dev
database.** That is intended — determinism is the point — but say so if the
user has data there they care about.

### 3. Read the screenshots

Every `capture(page, '<name>')` call writes `frontend/e2e-screenshots/<name>.png`.
Read those files directly; they are ordinary PNGs and you can see them.

```
ls frontend/e2e-screenshots/
```

Look at the image before deciding the change is done. Check the things jsdom
cannot tell you: does anything overflow or clip, is text legible against its
background, are occurrences in the day column they belong to, does the dialog
fit on screen.

### 4. Capture what you are working on

If the view you changed is not already captured, add a `capture()` call to the
relevant spec — or write a small throwaway spec — rather than working blind.
`frontend/e2e/calendar-page.ts` has the locators and actions:
`openWeekView`, `dayColumn`, `occurrenceFor`, `freeSlot`, `dialog`,
`bookButton`, `scrollWeekToHour`, `capture`.

Remember `scrollWeekToHour`: the week body scrolls internally, so evening
occurrences are below the fold and a naive screenshot shows an empty morning.

## Writing specs

- Reach for roles, labels and visible text. Day columns and occurrence blocks
  have no accessible name, so their classes live in `calendar-page.ts` and
  nowhere else — keep that coupling in one file.
- Never hardcode a date. Read everything from the fixture manifest via
  `loadFixture()`; the seed anchors to the Monday of the current week, so
  hardcoded dates rot within the week.
- The suite runs with one worker against one shared database. A spec that
  creates data must use the days the fixture leaves free (`fixture.free`), or
  it will break another spec's assertions.
- Use Playwright's retrying assertions (`await expect(locator).toBeVisible()`)
  rather than manual waits.

## When it fails

Failures keep a trace and a screenshot under `frontend/test-results/`. Read the
failure screenshot first — it usually shows the problem immediately.

See [frontend/development.md](../../../frontend/development.md#browser-testing-playwright)
for the HTML report, headed runs, and why the browser lives in its own
container.
