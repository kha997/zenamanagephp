# GAP-056 — Shell scripts expose the MySQL password: Gate-1 evidence

**Date:** 2026-09-29 (+07:00)

**Canonical base:** `368536793117816417373a3bde2ac636d46b7d42`

**Branch:** `docs/GAP-056-script-mysql-password-argv`

**Scope:** Read-only investigation and Gate-1 documentation. No script, code,
config, CI, credential, host or deployment change. No script was executed.

## Summary

The register row (OWN-2026-013) counts 31 command-line sites in 11 shell
scripts. Re-verified on the canonical base: still 31 sites / 11 files. Deeper
reading adds three facts that change the risk picture:

1. `scripts/setup-production.sh` **writes the database password in plaintext
   into a world-readable executable** `/usr/local/bin/zenamanage-backup` and
   installs a nightly cron entry for it — a persistent host artefact that
   outlives the repository.
2. `docker-manage.sh` — the one script on an automated path — also copies
   `production.env` (all secrets) into every backup directory.
3. GAP-054 already added `tests/Architecture/NoCommandLineDatabasePasswordTest`
   but it only scans `app/**/*.php`; shell scripts are outside any guard.

## Method

- `git grep -n -E -e '-p ?"?\$\{?[A-Z_]*(PASS|PWD)'` over the repository
  excluding `vendor`, `node_modules`, `docs`, `*.md` → 31 sites (same as the
  OWN-2026-013 correction).
- `git grep` for `--password[= ]`, `sshpass`, `redis-cli -a`, `PGPASSWORD`
  → no further database-credential sites (see "Related, out of scope").
- `git grep` of each script name over `.github`, `docker*`, `Dockerfile*`,
  `composer.json`, `package.json`, `docs/runbooks` for automated references.
- Read each call site and its surrounding function.

## Sites (canonical base)

| File | Lines | Account | Invoked by |
|---|---|---|---|
| `docker-manage.sh` | 160 (`mysqldump`), 207 (`mysql` restore) | **root**, fallback `root_password` | `.github/workflows/automated-deployment.yml:227` (`./docker-manage.sh backup` over SSH, `deploy-production` job) |
| `scripts/deploy.sh` | 75, 268 | **root**, fallback `root_password` | manual |
| `scripts/setup-production.sh` | 358 (inside generated cron script) | `$DB_USER`, **value baked into file** | manual, then **cron nightly** |
| `scripts/backup-database.sh` | 46 (unquoted) | `$DB_USER` | manual |
| `scripts/backup-system.sh` | 115, 447 | `$DB_USER` | manual |
| `scripts/deploy-production.sh` | 86 | `$DB_USER` | manual |
| `scripts/dr-automation.sh` | 108, 159, 193, 315, 357 | `$DB_USER` | manual |
| `scripts/maintenance-database.sh` | 28, 35, 42, 48, 54, 60, 66 | `$MYSQL_USER` | manual |
| `scripts/monitor-system.sh` | 128 | `$DB_USERNAME` | manual |
| `scripts/performance-monitor.sh` | 28 | `$DB_USERNAME` | manual |
| `scripts/setup-replication.sh` | 40, 58, 65, 92, 109, 118, 126, 136 | `$MASTER_USER` | manual |

The sanctioned deployment path (`.github/workflows/production.yml` →
`scripts/deploy/*.sh`, GAP-049) is clean: `scripts/deploy/backup.sh:19` passes
no password argument. The application-side backup uses
`App\Services\Backup\MysqlClient` with `--defaults-extra-file` (GAP-054).

## Why it matters

- A `-p<password>` argument is part of the process command line and is readable
  by other local users (`ps`, `/proc/<pid>/cmdline`) while the command runs; for
  `docker-compose exec … mysqldump -p…` the host-side `docker-compose` process
  carries it too. MySQL's own documentation classes command-line passwords as
  insecure for this reason.
- Four sites fall back to the literal `root_password` when the variable is
  unset. `docker-compose.yml:46` (development) uses the same default for the
  container's root account; `docker-compose.prod.yml:64` requires
  `MYSQL_ROOT_PASSWORD` explicitly. On the automated path the SSH script does
  not export `MYSQL_ROOT_PASSWORD`, so `docker-manage.sh` will send the
  fallback unless the host shell profile sets it.

## Finding 1 — plaintext password persisted by `setup-production.sh`

`create_backup_script()` (`scripts/setup-production.sh:347-373`) writes an
unquoted heredoc to `/usr/local/bin/zenamanage-backup` via `sudo tee`, so
`$DB_USER`, `$DB_PASS`, `$DB_NAME` are expanded **at generation time** and the
password is stored literally in the file. `chmod +x` on a file created under
the default umask leaves it world-readable (0755). A crontab entry
`0 2 * * *` runs it nightly, passing the password on the command line each
night. The same script tars the whole `$PROJECT_PATH` (including `.env`) into
`$BACKUP_PATH` with no permission restriction. Whether this script was ever run
on a real host cannot be determined from the repository.

## Finding 2 — `docker-manage.sh backup` copies secrets into backups

`create_backup()` (`docker-manage.sh:152-172`) copies `production.env` and the
compose file into `backups/<timestamp>/` next to the database dump — the same
class of defect GAP-054 removed from the application backup (`.env` in
archives). This is the automated-path script.

## Finding 3 — no guard for shell scripts

`tests/Architecture/NoCommandLineDatabasePasswordTest.php` iterates only
`app/` PHP files. Nothing prevents a new `-p"$PASS"` in a `.sh` file.

## Related, out of scope for GAP-056

- `scripts/configure-production-smtp.sh:176` passes `--password="$SMTP_PASSWORD"`
  to `php artisan smtp:configure` (SMTP credential on argv; the artisan
  signature `app/Console/Commands/ConfigureSMTP.php:21` defines the option).
  Different credential class; noted for a separate item.
- `scripts/run-comprehensive-tests.sh:194` uses a literal test SMTP password.
- `.github/workflows/*` service containers use fixed test root passwords
  (`root`, `password`, `root_password`) for ephemeral CI databases — not
  production credentials.

## Open operational question (for the Owner)

Were any of these scripts — in particular `setup-production.sh` and
`docker-manage.sh` — ever run on a real server? If yes, the password in
`/usr/local/bin/zenamanage-backup` and any `backups/*/production.env` copies
exist outside the repository and a repository fix alone will not remove them.

## Out of scope for this Gate 1

Any script edit or deletion, credential rotation, host inspection or cleanup,
deployment.
