#!/usr/bin/env bash
#
# Runs the Playwright suite against the running dev stack.
#
# Three steps, in order, because each depends on the one before:
#   1. reseed the database with the deterministic e2e fixture, capturing the
#      manifest the specs read;
#   2. install @playwright/test inside the browser container;
#   3. run the suite.
#
# Seeding has to happen here rather than in Playwright's globalSetup: the
# browser container has no PHP and no access to the Docker socket.
#
# Usage:
#   ./scripts/e2e.sh                                  # whole suite
#   ./scripts/e2e.sh e2e/booking-conflict.spec.ts     # one spec
#   ./scripts/e2e.sh --grep "conflict"                # by title
#
# WARNING: step 1 wipes every booking, recurring slot, band and user in the
# dev database. It is for local development only.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FIXTURE="frontend/e2e/.fixture.json"

# The browser binaries come from the image, the @playwright/test package comes
# from npm, and Playwright refuses to launch when the two versions differ — so
# fail here with a readable message rather than deep inside the test run.
package_version="$(sed -n 's/.*"@playwright\/test": "\([^"]*\)".*/\1/p' frontend/package.json)"
image_version="$(sed -n 's|.*mcr\.microsoft\.com/playwright:v\([^-]*\)-.*|\1|p' docker-compose.yml)"

if [ -z "$package_version" ] || [ "$package_version" != "$image_version" ]; then
  echo "Version mismatch: frontend/package.json pins @playwright/test ${package_version:-<none>}," >&2
  echo "but docker-compose.yml uses the v${image_version:-<none>} image. Update both together." >&2
  exit 1
fi

echo "==> Seeding the deterministic e2e fixture"
# Redirect via a temp file so a failed seed leaves the previous manifest intact
# rather than truncating it to nothing.
tmp_fixture="$(mktemp)"
trap 'rm -f "$tmp_fixture"' EXIT
docker compose exec -T web php scripts/seed_e2e.php > "$tmp_fixture"
mv "$tmp_fixture" "$FIXTURE"
trap - EXIT

# Only this one package, and without touching package-lock.json: the lockfile is
# resolved for the musl `frontend` container, and this one is glibc. Skipped when
# the volume already has the right version — `npm install` costs ~20s even as a
# no-op, and this loop is meant to be run over and over.
installed_version="$(docker compose run --rm --no-deps -T playwright \
  node -p "require('@playwright/test/package.json').version" 2>/dev/null | tr -d '\r' || true)"

if [ "$installed_version" != "$package_version" ]; then
  echo "==> Installing @playwright/test ${package_version} in the browser container"
  docker compose run --rm --no-deps playwright \
    npm install --no-save --no-package-lock --no-audit --no-fund "@playwright/test@${package_version}"
fi

echo "==> Running Playwright"
docker compose run --rm playwright npx playwright test "$@"
