# GAP-049 Host Provisioning Runbook

One-time checklist for provisioning a production host for ZENA's Candidate A
(hardened release-based SSH) deployment architecture. This document
describes the checklist; it does not provision any real host — provisioning
a real host is an external Owner-supplied action outside this repository's
automation, per GAP-049 Gate-2 §5.

## Assumptions

- Linux host (any distribution capable of running the packages below), one
  instance, single-host (multi-host/HA explicitly deferred per Gate-2 §4).
- PHP 8.2-fpm with extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql,
  dom, filter, gd, json, redis (matches `composer.json:11` and
  `.github/workflows/production.yml`'s `PHP_VERSION: '8.2'` / extension list).
- Composer 2.x, Node matching the version pinned in `Dockerfile.prod`.
- nginx, terminating TLS via an ACME-based certificate mechanism (e.g.
  Let's Encrypt/certbot) provisioned once during setup, not per-deploy.
- MySQL 8.0 (matches CI service images across `automated-testing.yml`,
  `ci-cd.yml`, `routes-guardrails.yml`).
- Redis, if `QUEUE_CONNECTION`/`CACHE_DRIVER`/`SESSION_DRIVER` are set to
  `redis` in `shared/.env` (recommended — `sync` queue is explicitly
  rejected by the GAP-049 queue canary, see `app/Console/Commands/QueueCanaryCommand.php`).

## Directory layout (fixed contract, see Gate-2 design §A-3)

```
/var/www/zena/
  current -> releases/<exact-sha>/
  releases/<exact-sha>/
  shared/.env
  shared/storage/
```

Create `shared/.env` and `shared/storage/` once, before the first release is
ever deployed:

```bash
sudo mkdir -p /var/www/zena/releases /var/www/zena/shared/storage
sudo touch /var/www/zena/shared/.env   # populate with real production values out-of-band, never commit
sudo chmod 600 /var/www/zena/shared/.env
```

## Services

- **queue-worker systemd unit** (currently absent per Gate-1 finding) — one
  unit running `php artisan queue:work <connection> --sleep=3 --tries=3`
  against `current/artisan`, `Restart=always`.
- **websocket systemd unit** (currently assumed but never provisioned per
  Gate-1 finding) — one unit running whatever websocket server command this
  application uses in production, `Restart=always`.
- nginx vhost pointing its document root at `current/public`.

## Deployment user and sudo scope

Create a dedicated, non-root `deploy` user. Grant it, via `visudo -f
/etc/sudoers.d/zena-deploy`, **exactly**:

```
deploy ALL=(root) NOPASSWD: /bin/systemctl reload nginx
deploy ALL=(root) NOPASSWD: /bin/systemctl restart zena-queue-worker
deploy ALL=(root) NOPASSWD: /bin/systemctl restart zena-websocket
```

Nothing broader (per Gate-2 design A-7/A-10). The `deploy` user owns
`releases/*` and `shared/storage` (read/write); it needs only read access to
`shared/.env`. The web server user (e.g. `www-data`) needs read access to
`current` and read/write access to `shared/storage`.

## SSH / host-key verification

Pin `appleboy/ssh-action`'s host fingerprint explicitly (do not rely on
default `StrictHostKeyChecking` behavior) — obtain it once via
`ssh-keyscan <host>` from a trusted channel and store it as the
`PRODUCTION_HOST_KEY_FINGERPRINT` GitHub secret, referenced by
`.github/workflows/production.yml`'s `fingerprint:` input to
`appleboy/ssh-action`. Host-key verification must fail closed — never
`StrictHostKeyChecking=no`.

## Exact-SHA delivery credential contract

Per Gate-2 Round-2 Clarification 1, this architecture uses mechanism (a):
CI checks out, verifies, and builds the exact requested SHA, then transfers
the already-built release artifact to the host over the same SSH channel
already used for the deploy step. **The production host requires no git
credential of any kind** — it never independently fetches from GitHub. This
closes the Gate-1 `git pull origin main` hazard structurally, not by policy.

## Backup destination

An off-host location (separate volume, remote object store, or
provider-native snapshot mechanism) — the specific provider is an external
Owner input (Gate-2 §5) not fixed here. Whatever is chosen, it must not
share a single-disk failure domain with `/var/www/zena`.

## Log rotation

`shared/storage/logs/laravel.log` (shared per A-3, so it survives every
deploy) — configure standard `logrotate` on the host; full APM/Sentry
adoption is deferred (Gate-2 A-9), not a blocker for first deployment.

## First bootstrap

Run `php artisan production:bootstrap --tenant-name=<real> --tenant-slug=<real> --admin-email=<real>`
exactly once against an empty database — see
`app/Console/Commands/ProductionBootstrapCommand.php` and Gate-2 §3a. Never
run `php artisan db:seed`.

## Deployment procedure

See `.github/workflows/production.yml` (the sole authoritative production
entry point after GAP-049) — `workflow_dispatch` with an exact release SHA
input; see that workflow file for the exact step sequence.

## Migration classification

See `docs/runbooks/gap-049-migration-safety.md`.

## Rollback/recovery and restore drill

See `docs/runbooks/gap-049-backup-restore.md` for backup/restore. For code
rollback: `scripts/deploy/rollback.sh /var/www/zena <explicit-target-sha>`
— an explicit target SHA is always required (see
`scripts/deploy/rollback.sh`, which fails closed without one).

## Production smoke

See Gate-2 design §6 for the full acceptance sequence (auth, tenant
scoping, RBAC, DB write persistence, queue canary, storage round-trip,
critical-page 200s) — a 7-item checklist. Only item 5 (queue canary, via
`php artisan deploy:queue-canary`) is actually implemented as an automated
post-cutover step in `production.yml`, together with the minimal
readiness endpoint's own DB/cache/storage probes (`GET
/api/v1/public/production/ready`). Items 1-4, 6, and 7 — authenticated
login as the real bootstrap operator, tenant-scoping verification, RBAC
enforcement check, a DB write persisting across a request cycle, a file
upload round-tripping through shared storage, and critical-page 200
checks — are **not** currently automated in `production.yml` and remain a
manual post-deploy checklist item for the human operator until a future
task automates them. This is a known, explicit gap, not an implemented
feature.
