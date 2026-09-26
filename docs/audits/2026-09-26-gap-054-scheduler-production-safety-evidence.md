# GAP-054 — Scheduler & backup production-safety Gate-1 evidence

**Date:** 2026-09-26 (+07:00)

**Canonical base:** `adacc5cc5fb8a08353cc90576076724e45e6e8bc`

**Branch:** `docs/GAP-054-scheduler-production-safety-gate1`

**Scope:** Read-only investigation and Gate-1 documentation. No application,
scheduler, backup, cache, config, test, CI, deployment, or environment change
is included. Runtime probes below only *list* the schedule and invoke
nonexistent command names; no scheduled workload, backup, or maintenance task
was executed.

## Finding

When the scheduler is switched on (`ENABLE_SCHEDULER=true`), the application
registers 13 recurring workloads. Several of them are unsafe or broken for a
real production host:

1. **Two scheduled commands do not exist** (`session:gc`, `cache:optimize`) and
   fail on every run.
2. **The daily cache-maintenance job wipes the entire application cache store**
   (`Cache::flush()`), which also holds login/portal rate-limit counters, OIDC
   login state, and the scheduler's own overlap locks.
3. **The daily full backup copies the production `.env` file** (application
   key, database and third-party secrets) into every backup archive.
4. **Both database-backup implementations put the MySQL password on the
   `mysqldump` command line**, unescaped, exposing it to any process listing on
   the host/container.
5. **Backup retention silently keeps only ~2 days**, not the configured 30, and
   the configured backup disk/path is ignored.

The only thing keeping these dormant today is the `ENABLE_SCHEDULER=false`
default — but the repository's own production Docker Compose file sets it to
`true`, and `.env.example` instructs operators to set it to `true` in
production.

This is **not** covered by any existing Work ID. A prior local-only branch
(`fix/scheduler-production-safety`, 18 commits, 2026-07-26, never pushed as a
PR and never registered) designed and partially implemented a fix; it is
recorded here as prior art only and is **not** proposed for merge (it is 109
commits behind canonical main and mixes unrelated RFI documentation commits).

## Candidate-ID and base audit

Before creating these documents:

- `origin/main` and this worktree's `HEAD` both resolved to
  `adacc5cc5fb8a08353cc90576076724e45e6e8bc`;
- `OPERATIONAL_GAP_REGISTER.md` tops out at GAP-052; GAP-053 exists only as
  open Draft PR #317;
- `git grep GAP-054` over `origin/main` and every remote branch: no match;
  `gh pr list --state all --search GAP-054`: 0 results;
- no register row, backlog entry, or owner packet mentions scheduler safety,
  scheduled backups, `.env`-in-backup, or `mysqldump` password exposure.

## Scheduler registration is live (correction to a GAP-049 evidence claim)

`bootstrap/app.php:34-37` binds `Illuminate\Contracts\Console\Kernel` to
`App\Console\Kernel` (classic, pre-Laravel-11 bootstrap). The schedule is
therefore defined by `App\Console\Kernel::schedule()`
(`app/Console/Kernel.php:21-95`), gated at lines 23-25 by
`config('app.enable_scheduler', false)` (`config/app.php:54`,
`env('ENABLE_SCHEDULER', false)`).

Runtime probe on canonical main (Laravel 12.63.0):

```
$ ENABLE_SCHEDULER=false php artisan schedule:list
   INFO  No scheduled tasks have been defined.

$ ENABLE_SCHEDULER=true php artisan schedule:list
  */5 *   * * *  php artisan maintenance:run --task=metrics
  0   2   * * *  php artisan maintenance:run --task=cache
  0   3   * * 0  php artisan maintenance:run --task=database
  0   4   * * *  php artisan maintenance:run --task=logs
  0   1   * * *  php artisan backup:run --type=all
  0   */6 * * *  php artisan backup:run --type=database
  */5 *   * * *  php artisan queue:monitor
  0   *   * * *  php artisan queue:restart
  0   8   * * *  php artisan session:gc
  0   9   * * *  php artisan cache:optimize
  0   10  * * *  php artisan route:cache
  0   11  * * *  php artisan view:cache
  0   12  * * *  php artisan config:cache
```

`docs/audits/2026-09-03-gap-049-production-readiness-evidence.md:158` states
that "`routes/console.php` defines no scheduled tasks … There is currently
nothing for a cron-triggered `php artisan schedule:run` to execute." That
statement inspected only the Laravel-11-style registration points; it is
inaccurate for this application's classic Kernel binding. This Gate 1 does not
reopen GAP-049's decision; it records the corrected fact.

## Where the scheduler is (or is instructed to be) turned on

| Location | Evidence |
|---|---|
| `docker-compose.prod.yml:128-143` | dedicated `scheduler` service, `command: php artisan schedule:work`, `ENABLE_SCHEDULER=true`, `CACHE_DRIVER=redis`, mounts `./storage` |
| `docker/supervisor/supervisord.conf:51` | `artisan schedule:work` |
| `docker/supervisord.conf:29` | `schedule:run` loop every 60 s |
| `.env.example:34-36` | "supervisor already runs `artisan schedule:work` … set true in production", default `false` |
| `docs/DEPLOYMENT_GUIDE.md:288`, `docs/INSTALLATION.md:258`, `docs/deploy-runbook.md:16`, `scripts/setup-cron.sh:39`, `scripts/update-cron.sh:49` | install a per-minute `schedule:run` cron |
| `app/Services/LaunchChecklistService.php:395-403` | launch-readiness "backup system" check passes only if `backup:run` (scheduled) produced an archive recently — i.e. readiness *expects* the scheduler on |

The GAP-049 released SSH topology (`docs/runbooks/gap-049-host-provisioning.md`)
provisions no scheduler cron, and its separate `scripts/deploy/backup.sh`
does not use the application's backup command. So the two sanctioned
deployment shapes disagree: the Docker shape enables all 13 workloads, the SSH
shape enables none (and the launch checklist's backup check can then never
pass).

## Defect 1 — scheduled commands that do not exist

`app/Console/Kernel.php:72-74` schedules `session:gc`; lines 77-79 schedule
`cache:optimize`. Neither is registered:

```
$ php artisan -n session:gc
   ERROR  Command "session:gc" is not defined. Did you mean one of these?
  ⇂ session:table
(exit 1)

$ php artisan -n cache:optimize
   ERROR  Command "cache:optimize" is not defined.
(exit 1)
```

Result when enabled: two guaranteed failures per day, noise in scheduler
output, and — for `session:gc` — the false impression that expired sessions
are being collected.

## Defect 2 — daily cache maintenance wipes the whole cache store

`maintenance:run --task=cache` is scheduled daily at 02:00
(`app/Console/Kernel.php:33-35`). It runs `MaintenanceCommand::clearCache()`
(`app/Console/Commands/MaintenanceCommand.php:82-102`), which calls
`cache:clear`, `config:clear`, `route:clear`, `view:clear` and then
`Cache::flush()` (line 94).

With the production cache driver (`CACHE_DRIVER=redis`,
`docker-compose.prod.yml:140`), the default store is the `redis` store on the
`cache` Redis connection (`config/cache.php:16-19`, Redis DB
`REDIS_CACHE_DB`=1, `config/database.php`). A flush empties everything the
application keeps there, including (verified consumers):

- **Login / portal brute-force throttling** — named and inline `throttle:`
  limiters on the ZENA API login (`routes/api_zena.php:52`,
  `throttle:zena-login`), client-portal magic-link send/verify
  (`routes/web.php:848-849`, `throttle:6,1` / `throttle:10,1`), invitation
  acceptance (`routes/web.php:473`) and others (15 `throttle:` uses in
  `routes/`). Laravel's rate limiter stores its counters in the default cache
  store, so every counter resets to zero daily.
- **OIDC login state** — `app/Services/OIDCService.php:43,79,108` stores and
  validates the `oidc_state:{state}` handshake in cache; a login in flight at
  02:00 fails.
- **Scheduler overlap locks** — `withoutOverlapping()` mutexes (every entry in
  `Kernel::schedule()`) live in the same store; the flush can release a lock
  held by a still-running job (e.g. a backup started at 00:00/01:00 with
  `runInBackground()`).

Sessions are **not** affected: the Redis session driver uses
`session.connection` (null → `default` Redis connection, DB 0), not the cache
DB. No maintenance-owned cache namespace exists that a targeted invalidation
could use instead.

Also: `config:clear`/`route:clear` at 02:00 and `route:cache`/`config:cache`
at 10:00/12:00 mean production runs uncached config/routes for ~8–10 hours
every day.

## Defect 3 — every full backup contains the production `.env`

`backup:run --type=all` is scheduled daily at 01:00
(`app/Console/Kernel.php:50-53`). `BackupCommand::handle()`
(`app/Console/Commands/BackupCommand.php:24-77`) calls `backupConfig()`,
which copies the live environment file into the archive
(`BackupCommand.php:166-169`):

```php
if (file_exists(base_path('.env'))) {
    copy(base_path('.env'), $configDir . '/.env');
}
```

The `.env` holds `APP_KEY` (decrypts encrypted columns/cookies), database,
Redis, mail, Pusher and `ANTHROPIC_API_KEY` credentials (`.env.example`).
Archives are written to `storage/backups/backup_<timestamp>.tar.gz`
(`BackupCommand.php:203-214, 300-316`) with default permissions, on the same
`./storage` volume the web/app containers mount. Anyone who obtains a backup
obtains every production secret. GAP-049's own host runbook treats
`shared/.env` as `chmod 600` and "never commit"
(`docs/runbooks/gap-049-host-provisioning.md:40-41`) — the scheduled backup
undoes that protection daily.

## Defect 4 — MySQL password on the command line, unescaped

`BackupCommand::backupDatabase()` (`BackupCommand.php:98-108`):

```php
$command = sprintf(
    'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers %s > %s',
    $config['username'] ?? '', $config['password'] ?? '', ...
);
exec($command, $output, $returnCode);
```

`DatabaseBackupService` (used by `DatabaseBackupCommand`) does the same at
`app/Services/DatabaseBackupService.php:337, 373, 403`
(`" --password={$config['password']}"`).

Consequences:

- the password is visible in the process table (`ps`) of the host/container
  for the duration of the dump, and MySQL itself warns that command-line
  passwords are insecure;
- no `escapeshellarg()` — a password containing shell metacharacters
  (`$`, `` ` ``, `;`, `&`, spaces, quotes) breaks the dump (backup marked
  failed) or is interpreted by the shell.

Contrast: GAP-049's `scripts/deploy/backup.sh:19` calls `mysqldump -u "$DB_USER"`
with no password argument (credentials supplied out-of-band), i.e. the safe
pattern already exists in the repository.

## Defect 5 — retention keeps ~2 days, configured storage is ignored

`cleanupOldBackups()` (`BackupCommand.php:322-363`) keeps at most
`config('backup.max_backups', 10)` archives matching `backup_*.tar.gz`, then
applies `max_age_days` (30). The schedule produces one `--type=all` archive per
day plus four `--type=database` archives per day (`Kernel.php:50-59`) — five
archives/day under the same pattern — so the count cap deletes anything older
than ~2 days long before the 30-day age limit applies. There is no
distinction between full and database-only archives, so the only daily
full (files + config) backup is rotated out with the rest.

`config/backup.php` also declares `disk` and `path`, but no code reads
`backup.disk` or `backup.path` (`git grep` over `app/` and `src/`): archives
always land in local `storage/backups`, on the same host/volume as the data
they protect.

## Prior art (not proposed for merge)

Local-only branch `fix/scheduler-production-safety` (tip `dea526c5`, base
`d6ca498b` from 2026-07-25) contains a design, delta design, plan, and
implementation commits (`e1a71518` fail-closed maintenance, `c0e76c3e`
consolidated verified backups, `e362be4a` scheduler heartbeat, `048e2e69`
bounded log retention, `62be1b6f` distributed-lock policy) with tests. It was
never opened as a PR, never passed any owner gate, and is 109 commits behind
canonical main. It is preserved (not deleted) and also archived in
`~/zenamanage-backups/2026-09-26/branches/all-branches-2026-09-26.bundle` on
the operator workstation. If Gate 1 is approved, Gate 2 may consult it as
reference material; nothing from it is adopted by this Gate 1.

## Out of scope for this Gate 1

- Changing `ENABLE_SCHEDULER`, any production environment variable, cron,
  supervisor, Docker Compose, or deploy workflow.
- Choosing between the Docker and SSH deployment shapes (owned by GAP-049
  follow-up / Owner topology decision).
- Rotating any credential. No evidence was found that a backup containing
  `.env` has left a production host; whether any production host has ever run
  the scheduler is not observable from the repository.
- Queue-worker health (`queue:monitor`, `queue:restart`), metrics collection,
  database `OPTIMIZE TABLE` scheduling, and `event_logs`/`audit_logs`
  retention — noted by the prior-art design but not re-verified here.
- Multi-node scheduler topology (`onOneServer()`), which depends on the
  topology decision above.

## Reproduction commands (all read-only)

```bash
git rev-parse origin/main                          # adacc5cc5fb8…
ENABLE_SCHEDULER=false php artisan schedule:list   # no tasks
ENABLE_SCHEDULER=true  php artisan schedule:list   # 13 tasks
php artisan -n session:gc                          # not defined, exit 1
php artisan -n cache:optimize                      # not defined, exit 1
git grep -n "Cache::flush" origin/main -- app/Console/Commands/MaintenanceCommand.php
git grep -n "base_path('.env')" origin/main -- app/Console/Commands/BackupCommand.php
git grep -n -- "--password" origin/main -- app/Console/Commands/BackupCommand.php app/Services/DatabaseBackupService.php
git grep -n "backup\.disk\|backup\.path" origin/main -- app src   # no readers
```
