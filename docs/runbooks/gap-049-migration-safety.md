# GAP-049 Migration Safety Runbook

Enforced in code by `app/Services/Deployment/MigrationClassificationService.php`
and `app/Console/Commands/DeployMigrateCommand.php` — this document is the
human-readable procedure that command's error messages point to.

## Classification (required before every deploy containing migrations)

Every migration file must have an entry in `database/migrations/classifications.json`:
`"expand"` or `"breaking"`. `deploy:migrate` fails closed (exit 1) if any
pending migration lacks an entry — classification is never informal or
assumed.

- **expand**: the *old, still-`current`* release's code can tolerate the
  change unchanged (new nullable column, new table, new compatible index).
  Runs pre-cutover, from the new release, against the shared database — the
  old release keeps serving correctly during that window.
- **breaking**: the current code would break against the migrated schema
  (dropping/renaming a column still read/written by current code,
  destructive data conversion, incompatible constraint). Requires the full
  procedure below — `deploy:migrate` refuses to run it as an ordinary
  pre-cutover step (exit 2 without `--allow-breaking`, exit 3 with
  `--allow-breaking` but no active maintenance mode).

## Breaking-migration procedure (manual, in this order)

1. Classify the migration `"breaking"` in `database/migrations/classifications.json`
   *before* triggering the deploy — this is a code review decision, not a
   deploy-time judgment call.
2. Take a fresh backup: `scripts/deploy/backup.sh <db_name> <db_user> <backup_dir> <storage_dir>`
   (see `docs/runbooks/gap-049-backup-restore.md`). Record the resulting
   `.sql.gz`/`.sha256` paths — they are the recovery point for this specific
   migration.
3. Enter maintenance mode on the **currently-serving** release (this affects
   real traffic immediately): `php artisan down` — writes the maintenance
   flag into `shared/storage/framework/down` (shared per A-3, so it is
   visible to whichever release is `current`, not orphaned in a
   not-yet-active new release directory).
4. Write (or open, if pre-written for a known migration) a
   migration-specific forward/rollback/data-fix runbook describing exactly
   what this migration does and how to reverse or repair it if it fails
   partway — do this *before* running the migration, not improvised after a
   failure.
5. Run `php artisan deploy:migrate --allow-breaking` (this only proceeds
   because `app()->isDownForMaintenance()` is now true, per Step 3).
6. Complete the code cutover (`scripts/deploy/activate-release.sh`).
7. Run the readiness check (`GET /api/v1/public/production/ready`) — it
   must return 200 before maintenance mode is lifted.
8. Lift maintenance mode: `php artisan up`.

## Rollback semantics (never automatic `migrate:rollback`)

- **After an expand migration**: `scripts/deploy/rollback.sh` (code-only,
  re-pointing `current` to the explicit prior release SHA) is sufficient —
  the prior code already tolerates the expanded schema; nothing further is
  needed.
- **After a breaking migration**: "rollback" is not simply switching
  `current`. It requires maintenance mode (already active per Step 3 above)
  plus the migration-specific data/schema recovery procedure written in
  Step 4. No automatic schema-rollback mechanism exists or is invented by
  this repository — `grep -rn "migrate:rollback" app/ scripts/` must return
  no matches in production code paths (verified by
  `tests/Feature/Deployment/DeployMigrateCommandTest::test_command_never_invokes_migrate_rollback`).

## Automatic post-cutover recovery (`.github/workflows/production.yml`, Gate-2 A-2/A-5)

`production.yml`'s `deploy` job captures the explicit previous `current`
release target immediately before cutover (`shared/`-independent host
state, `${ROOT}/.previous-release` — never inferred as a relative-commit
guess or "most recent releases/ entry"). If post-cutover verification
(the readiness endpoint and/or the queue canary) fails after a successful
cutover, a dedicated recovery step runs automatically:

- **Expand deployment, prior release exists**: automatically invokes
  `scripts/deploy/rollback.sh` against the captured explicit previous SHA,
  restarts the required services, and the workflow reports `rolled_back`
  only if a post-recovery readiness check against the restored release
  also passes. The overall attempted deployment is still reported as
  failed to reach the new release — `rolled_back` is never conflated with
  a successful new deployment.
- **Breaking deployment** (`allow_breaking_migrations=true`): the recovery
  step deliberately does **not** invoke `rollback.sh` — the new schema may
  be incompatible with the previous release's code. It ensures/retains
  maintenance mode (`php artisan down`) and reports `failed`; a Slack
  escalation (if configured) explicitly flags that operator action is
  required per this runbook's breaking-migration procedure above.
- **First-ever deployment** (no prior release existed): there is no
  rollback target to invent. The recovery step records the failure
  truthfully and enters maintenance mode as the safe no-service state.
- **Recorded previous release missing** (pruned by cleanup — should not
  happen given `cleanup-releases.sh` always keeps the rollback target, but
  handled defensively): same maintenance-mode fallback as the first-deploy
  case, rather than attempting a rollback against a release that no longer
  exists on disk.

A readiness check that genuinely ran and failed is never subsequently
reported as `deployed_unverified` — that state is reserved for the narrow
case where the readiness step itself never reached a real pass/fail
(e.g. the workflow run was cancelled mid-flight).

## Concurrency

Deployment-level serialization (no two `workflow_dispatch` production
deploys running concurrently) is enforced by
`.github/workflows/production.yml`'s `concurrency: { group: production-deploy, cancel-in-progress: false }`
block. `migrate --isolated` (used inside `deploy:migrate`) is a *separate*
mechanism — a migration-command mutex only, not a substitute for workflow
serialization.
