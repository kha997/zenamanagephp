---
work_id: GAP-089
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-089/02-design.md
---

# GAP-089 — Dockerfile builds, Dockerfile.websocket retired: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-089/02-design.md`).

1. **Dockerfile** — add `libicu-dev`; `composer install --no-dev --no-scripts --no-autoloader` before `COPY . .`
   and `composer dump-autoload --optimize --no-dev` after it; copy `docker/php-fpm.conf`.
2. **Retire the websocket image** — delete `Dockerfile.websocket`, remove the WebSocket image step from
   `release-management.yml` and the `websocket` service from `docker-compose.yml`.
3. **CI** — new `docker-dev-image.yml` (pull_request and push to main, path-filtered) builds `Dockerfile` and checks
   `php -m` lists `redis` and `intl` and `php artisan --version` runs.
4. **Proof** — a temporary commit restores the wrong `php-fpm.conf` path; the new job must go red; reverted.
5. **Verify** — actionlint, `docker compose -f docker-compose.yml config`, exact-head CI, governance and docs lints.
