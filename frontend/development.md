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
├── index.html
├── vite.config.ts        # dev server, proxy, vitest config
├── tsconfig.json          # project references
├── tsconfig.app.json      # app source, strict mode
├── tsconfig.node.json     # vite.config.ts itself
├── tsconfig.vitest.json   # test files
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
