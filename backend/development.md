# Backend development

Plain PHP 8.3 JSON API (no framework), served by Apache. This folder is the
deployment unit — it mirrors `/var/www/[domain]/` on simply.com shared
hosting, so `app/` stays outside the web root and only `public_html/` is
web-exposed.

## Layout

```
backend/
├── app/              # PHP source, NOT web-exposed (PSR-4: App\)
│   └── bootstrap.php
├── public_html/      # web root — index.php front controller, .htaccess
├── tests/            # Pest tests (PSR-4: Tests\)
│   ├── Unit/
│   ├── Feature/
│   ├── Pest.php      # base test case per directory, shared expectations/helpers
│   └── TestCase.php
├── composer.json
├── phpstan.neon      # level 8, scans app/ tests/ public_html/
├── phpunit.xml
└── .php-cs-fixer.php # PSR-12 + strict_types, scans app/ tests/ public_html/
```

## Running commands

All commands run inside the `web` container:

```
docker compose exec web bash       # shell into the container
docker compose exec web composer <cmd>
```

Install/update dependencies after a fresh clone or a `composer.json` change:

```
docker compose exec web composer install
```

## Testing

Uses [Pest](https://pestphp.com/):

```
docker compose exec web composer test
# or directly:
docker compose exec web ./vendor/bin/pest
```

Add new tests under `tests/Unit` or `tests/Feature`. `tests/Pest.php` wires up
which base test case each directory uses and holds shared
expectations/helpers — check it before adding a new top-level test directory.

## Static analysis

[PHPStan](https://phpstan.org/) at level 8, configured in `phpstan.neon`:

```
docker compose exec web composer stan
# or directly:
docker compose exec web ./vendor/bin/phpstan analyse
```

Level 8 is strict — prefer fixing type issues over adding baseline ignores.

## Code style

[PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer), rules in
`.php-cs-fixer.php` (PSR-12, `declare(strict_types=1)` required, single
quotes, ordered imports, trailing commas in multiline).

```
docker compose exec web composer cs        # check only (dry-run + diff)
docker compose exec web composer cs-fix    # apply fixes
```

Run `composer cs` before committing; run `composer cs-fix` to auto-fix.

## Adding an API route

Routes are wired up in `public_html/index.php`. Existing examples:

- `GET /api/health` — `{"status":"ok"}`, proves the PHP container is up
- `GET /api/db-check` — runs `SELECT VERSION()` via PDO, proves the DB connection
- `GET /api/test-mail` — sends a test email through PHPMailer to Mailhog
- `GET /api/calendar?week_start=YYYY-MM-DD[&band_id=N]` — merges ad-hoc
  `bookings` with parity-matching `recurring_slots` for the Mon-Sun week
  starting on `week_start`; see `app/Calendar/` and `app/Http/CalendarController.php`

New application logic belongs in `app/` (autoloaded under the `App\`
namespace) and is required/included from the front controller.

## Mail in dev

Mail goes out via [PHPMailer](https://github.com/PHPMailer/PHPMailer),
configured from the `MAIL_HOST`/`MAIL_PORT` env vars (`mailhog:1025` in
`docker-compose.yml`). View sent mail at http://localhost:8025. Swapping to
real SMTP credentials in production is a config change only.

## Database

MySQL 8.0, accessed via PDO. Browse it at http://localhost:8081
(phpMyAdmin), logging in with the `DB_USER`/`DB_PASSWORD` from `.env`.

There's no migration tool (plain PHP, no framework) — schema lives as plain
SQL in `docker/mysql/init/`, which MySQL runs automatically, in filename
order, only when the `db_data` volume is first created. Adding a new `.sql`
file there does nothing for an already-initialized local database; apply it
by hand instead:

```
docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASSWORD" "$DB_DATABASE" < docker/mysql/init/00N_your_file.sql
```

For a fresh clone (or to rebuild local data from scratch), `docker compose
down -v && docker compose up -d` picks up every init file automatically. In
production (simply.com), run the same `.sql` files by hand once, e.g. via
phpMyAdmin or `mysql` over SSH — there's no automated migration step.

## Before deploying

Upload `backend/` (both `app/` and `public_html/`) to the domain's root on
simply.com via SSH/SFTP, matching the `/var/www/[domain]/` structure. Then
either run `composer install --no-dev` over SSH, or upload `vendor/`
manually if Composer isn't usable there. Keep runtime config (DB/SMTP
credentials) out of version control — set it via environment variables or a
non-web-exposed file under `app/`.
