# Frontend development

Vue 3 SPA built with Vite, written in strict TypeScript. Talks to the backend
as a JSON API (see [../backend/development.md](../backend/development.md)).
Built as static files for simply.com — there is no Node.js runtime in
production.

## Layout

```
frontend/
├── src/
│   ├── App.vue
│   ├── main.ts
│   └── env.d.ts        # Vite/SFC ambient type shims
├── tests/
│   └── unit/            # Vitest + Vue Test Utils
├── e2e/                  # Playwright specs, run in a real Chromium
├── index.html
├── vite.config.ts        # dev server, proxy, vitest config
├── playwright.config.ts  # e2e suite, browser context
├── tsconfig.json          # project references
├── tsconfig.app.json      # app source, strict mode
├── tsconfig.node.json     # vite.config.ts itself
├── tsconfig.vitest.json   # unit test files
├── tsconfig.e2e.json      # e2e specs
├── eslint.config.js       # flat config
└── .prettierrc.json
```

## Running commands

All commands run inside the `frontend` container:

```
docker compose exec frontend sh       # shell into the container
docker compose exec frontend npm <cmd>
```

Install/update dependencies after a fresh clone or a `package.json` change —
this also happens automatically every time the `frontend` container starts:

```
docker compose exec frontend npm install
```

## Testing

Uses [Vitest](https://vitest.dev/) + [Vue Test Utils](https://test-utils.vuejs.org/):

```
docker compose exec frontend npm test         # run once
docker compose exec frontend npm run test:watch
```

Add new tests under `tests/unit`.

## Browser testing (Playwright)

Vitest renders into jsdom, which has no layout and no paint: it can tell you a
conflict disabled "Book", but not that the dialog overflowed its container or
that occurrences landed in the wrong day column. [Playwright](https://playwright.dev/)
drives a real Chromium against the running dev server and real backend data,
and writes screenshots you can look at.

Run the whole suite from the repo root:

```
./scripts/e2e.sh
```

That script does three things in order: reseeds the database with the
deterministic e2e fixture, installs `@playwright/test` in the browser
container, then runs the specs. **It wipes every booking, recurring slot,
band and user in the dev database** — it is for local development only.

Narrow it down by file or by test title:

```
./scripts/e2e.sh e2e/booking-conflict.spec.ts
./scripts/e2e.sh --grep "hard-disabled"
```

Screenshots land in `frontend/e2e-screenshots/` (gitignored), one PNG per
`capture()` call, overwritten on each run.

### Why a separate container

The browser runs in its own `playwright` compose service, not in `frontend`:
that one is `node:20-alpine`, and Playwright publishes no browser builds for
musl. The service pins `mcr.microsoft.com/playwright:v<version>-noble`, whose
tag **must** match the `@playwright/test` version in `package.json` exactly —
`scripts/e2e.sh` checks this and refuses to run otherwise. Pinning also keeps
rendering (and therefore screenshots) identical across machines.

`@playwright/test` is still a dev dependency here so `typecheck` and `lint`
cover the specs; it is just never executed in this container.

### The fixture

`backend/scripts/seed_e2e.php` inserts a _fixed_ fixture — unlike
`seed_september.php`, whose randomness is right for eyeballing a full calendar
and fatal for assertions. It anchors everything to the Monday of the current
week, so the fixture is always where the calendar opens, and leaves Tuesday and
Sunday empty for the specs that create bookings.

It writes a JSON manifest to stdout, which `scripts/e2e.sh` captures as
`frontend/e2e/.fixture.json`; the specs read dates and band names from there
rather than recomputing the same date maths in TypeScript.

### Debugging a failing spec

Failures keep a trace and a screenshot under `frontend/test-results/`. The HTML
report is the fastest way in — serve it from the container and open it on the
host:

```
docker compose run --rm --service-ports playwright \
  npx playwright show-report --host 0.0.0.0
```

To run one spec in a headed browser (under Xvfb, so there is nothing to watch
live — but videos and traces record as a real headed run):

```
docker compose run --rm playwright \
  xvfb-run npx playwright test e2e/booking-create.spec.ts --headed
```

Running `docker compose run` directly like this skips the reseed, so start from
`./scripts/e2e.sh` whenever a spec creates or depends on data.

## Type checking

Strict TypeScript, checked via [vue-tsc](https://github.com/vuejs/language-tools)
(plain `tsc` can't type-check `.vue` template bindings):

```
docker compose exec frontend npm run typecheck
```

`npm run build` runs this first and fails the build on type errors.

## Static analysis

[ESLint](https://eslint.org/) flat config in `eslint.config.js`
(`typescript-eslint` recommended rules + `eslint-plugin-vue` recommended rules):

```
docker compose exec frontend npm run lint
docker compose exec frontend npm run lint:fix
```

## Code style

[Prettier](https://prettier.io/), rules in `.prettierrc.json` (no semicolons,
single quotes, trailing commas):

```
docker compose exec frontend npm run format:check
docker compose exec frontend npm run format
```

Run `lint` and `format:check` before committing.

## Before deploying

Run `npm run build` to output static files to `dist/`, then upload its
contents into `backend/public_html/` (e.g. under an `/assets` path),
updating the SPA's asset references/`index.html` accordingly — there is no
Node.js runtime on simply.com, so the build must be produced locally/in CI
and shipped as static assets. See
[backend/development.md](../backend/development.md#before-deploying) for
uploading the backend itself.
