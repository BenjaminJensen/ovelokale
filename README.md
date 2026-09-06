# Ovelokale

A PHP JSON API backend with a Vue 3 SPA frontend and a MySQL database, developed
in Docker and deployed to [simply.com](https://www.simply.com/) shared hosting.

## Stack

- **Backend**: Plain PHP 8.3 (no framework), served by Apache
- **Frontend**: Vue 3 + Vite + TypeScript (strict), decoupled SPA built to static
  files, talks to the backend as a JSON API
- **Database**: MySQL 8.0
- **Testing**: [Pest](https://pestphp.com/) (backend), [Vitest](https://vitest.dev/)
  + Vue Test Utils (frontend)
- **Static analysis**: [PHPStan](https://phpstan.org/) level 8 (backend),
  [ESLint](https://eslint.org/) + [vue-tsc](https://github.com/vuejs/language-tools)
  (frontend)
- **Formatting**: [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
  (backend), [Prettier](https://prettier.io/) (frontend)
- **Dev tooling**: phpMyAdmin (DB browser), Mailhog (catches outgoing dev emails)

## Why it's structured this way

Production hosting is simply.com shared hosting, which:

- Serves each domain from `/var/www/[domain]/`, with only the `public_html/`
  subfolder web-exposed — anything outside it is private but still reachable
  from PHP via `require`/`include`
- Uses Apache with full `.htaccess` support (`AllowOverride All`)
- Has no Node.js runtime — a built frontend must ship as static files

`backend/` mirrors that domain folder exactly, so deployment is just uploading
`backend/` as-is plus a production Vite build — see the "Before deploying"
sections in [backend/development.md](backend/development.md) and
[frontend/development.md](frontend/development.md) for the exact steps.

## Directory layout

```
ovelokale/
├── docker-compose.yml
├── .env                    # local config (gitignored) — copy from .env.example
├── docker/
│   └── php/                # Dockerfile + Apache vhost for the `web` service
├── backend/                 # == /var/www/[domain]/ on simply.com
│   ├── app/                 # PHP source, NOT web-exposed
│   ├── public_html/          # web root — index.php front controller, .htaccess
│   ├── tests/                # Pest tests (Unit/ and Feature/)
│   ├── composer.json
│   └── development.md        # backend dev guide (testing, stan, cs, routes)
└── frontend/                 # Vue 3 + Vite + TypeScript SPA
    ├── src/
    ├── tests/unit/
    └── development.md         # frontend dev guide (testing, typecheck, lint, format)
```

## Prerequisites

- Docker + Docker Compose

## Setup

1. Copy the env file and adjust if needed:
   ```
   cp .env.example .env
   ```
2. Build and start everything:
   ```
   docker compose up --build -d
   ```
3. Install PHP dependencies (only needed after a fresh clone or when
   `composer.json` changes):
   ```
   docker compose exec web composer install
   ```
   Frontend dependencies (`npm install`) run automatically every time the
   `frontend` container starts.

## Services

| Service      | URL                          | Notes                                  |
|--------------|-------------------------------|------------------------------------------|
| `web`        | http://localhost:8080         | PHP API (Apache), serves `backend/public_html` |
| `frontend`   | http://localhost:5173         | Vite dev server, hot reload, proxies `/api` to `web` |
| `db`         | localhost:3306                 | MySQL 8.0                                |
| `phpmyadmin` | http://localhost:8081          | Log in with the `DB_USER`/`DB_PASSWORD` from `.env` |
| `mailhog`    | http://localhost:8025          | Catches all mail sent by the PHP app (SMTP on :1025) |

Ports are configurable via `.env`.

## Backend development

For everything backend-specific — API routes, testing, static analysis, code
style, and sending mail in dev — see
[backend/development.md](backend/development.md).

## Frontend development

For everything frontend-specific — testing, type checking, static analysis,
and code style — see [frontend/development.md](frontend/development.md).

## Common commands

```
docker compose up -d              # start everything in the background
docker compose down                # stop everything
docker compose logs -f <service>   # tail logs for one service
docker compose exec web bash       # shell into the PHP container
docker compose exec web composer <cmd>
docker compose exec frontend sh    # shell into the frontend container
```

