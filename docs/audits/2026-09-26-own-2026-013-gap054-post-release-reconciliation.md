# OWN-2026-013 — GAP-054 post-release register reconciliation: Gate-1 evidence

**Date:** 2026-09-26 (+07:00)

**Canonical base:** `5441bc2e9c4c48b2f0c5feac2a0118d5cfcb3c58`

**Branch:** `docs/OWN-2026-013-gap054-post-release-reconciliation`

**Scope:** Read-only investigation and Gate-1 documentation. No register,
code, script, config, CI, or deployment change is included.

## Why a separate Work ID

`OPERATIONAL_GAP_REGISTER.md`'s GAP-054 row still reads "Gate 2 APPROVED …
implementation authorized, not started", although GAP-054 was released on
2026-09-26 (squash `a473298e2fc6aabada1b41291ec5478fee7b73c3`, PR #318; release
execution record merged in PR #319 at `5441bc2e`).

Updating that row under Work ID GAP-054 is structurally blocked: the register
is part of GAP-054's implementation-tree digest. A local probe on the
reconciliation branch showed the digest changing from the Owner-bound
`7108b2925e8c8300207afecb7994e2c300cd9d9778746670112c8a5c6c29cce7` to
`2f507d45aff7af6866071924c9687da68d1b9c7aa490ab1736eb2d27a08e6bf6`, which
`scripts/ci/check-evidence-freshness.sh:20-46` rejects as a STALE Owner
decision. The same situation for GAP-052 was resolved through the separately
governed OWN-2026-011.

## Candidate-ID audit

`docs/owner-decisions/` contains OWN-2026-008, -009, -011, -012; `git grep
OWN-2026-013` over `origin/main` and every remote branch: no match. (A GitHub
full-text search hit on PR #237 does not reference the ID.) The next free
register numbers are GAP-055 and GAP-056 (register tops out at GAP-054;
GAP-053 is open PR #317).

## Item 1 — GAP-054 row is stale

Verified facts for a RESOLVED row:

- PR #318 `MERGED`, merge commit `a473298e2fc6aabada1b41291ec5478fee7b73c3`;
  `origin/main` tree at that commit is identical to the Owner-approved head
  `be72d6e5d183f3e30ce94b56c0a7d8cdd3a6f7d7`; implementation-tree digest at the
  merge commit equals the Owner-bound digest.
- Post-merge `main` CI on `a473298e`: Auth Guard Lint, Automated Testing,
  Button Test Suite, CI/CD Pipeline, Code Quality & Security, Owner Governance
  Lint, Routes Guardrails, Staging Smoke — all `success`.
- Not deployed; scheduler not enabled on any host (recorded in
  `docs/owner-decisions/GAP-054/03-release.md` → "Release execution record").

## Item 2 — admin "clear cache" still flushes the whole cache (candidate GAP-055)

- `app/Http/Controllers/Admin/MaintenanceController.php:283-292`
  `clearCache()` calls `cache:clear`, `config:clear`, `route:clear`,
  `view:clear` and `Cache::flush()`.
- Live route (runtime `php artisan route:list --json` on canonical main):
  `POST admin/maintenance/clear-cache` → `MaintenanceController@clearCache`,
  middleware `web`, `Authenticate`, `TenantIsolationMiddleware`,
  `RoleBasedAccessControlMiddleware:admin` (`routes/web.php:194`).
- Effect (same mechanism as GAP-054 Defect 2, evidence
  `docs/audits/2026-09-26-gap-054-scheduler-production-safety-evidence.md`):
  one admin click resets login/portal/invitation `throttle:` counters, OIDC
  login state and scheduler overlap locks for every tenant, and drops compiled
  config/route/view caches until the next deploy.
- Not live (checked): `App\Http\Controllers\PerformanceController::clearCaches()`
  (`routes/legacy/api_v1.php:38`, not in the runtime route list) and
  `Api\Admin\PerformanceController::clearCaches()` (route commented out,
  `routes/web.php:347`). Both also call `cache:clear`; noted for the same GAP.
- GAP-054 deliberately excluded this site (manual HTTP action outside the
  approved technical contract).

## Item 3 — shell scripts pass the MySQL password on the command line (candidate GAP-056)

`-p"$PASSWORD"` / `-p$PASSWORD` process arguments on canonical main:

| File:line | Command |
|---|---|
| `scripts/backup-database.sh:46` | `mysqldump -u$DB_USER -p$DB_PASSWORD` (unquoted) |
| `scripts/backup-system.sh:115, 447` | `mysqldump` / `mysql … -p"$DB_PASSWORD"` |
| `scripts/deploy-production.sh:86` | `mysqldump -u "$DB_USER" -p"$DB_PASS"` |
| `scripts/deploy.sh:75` | `mysqldump -u root -p${DB_ROOT_PASSWORD:-root_password} --all-databases` (root, hard-coded fallback password) |
| `scripts/dr-automation.sh:108, 159, 193, 315, 357` | `mysqldump` / `mysql … -p"$DB_PASSWORD"` |
| `scripts/monitor-system.sh:128` | `mysql -p"${DB_PASSWORD}"` |
| `scripts/performance-monitor.sh:28` | `mysql -p"${DB_PASSWORD}"` |
| `scripts/setup-production.sh:358` | generated cron script with `mysqldump -p$DB_PASS` |

Context: the sanctioned deployment path (`.github/workflows/production.yml`,
GAP-049) uses only `scripts/deploy/*.sh`; `scripts/deploy/backup.sh:19` calls
`mysqldump -u "$DB_USER"` with no password argument. None of the scripts above
is referenced by any workflow, Dockerfile, compose file, or GAP-049 runbook
(`git grep` over `.github docker Dockerfile* docs/runbooks composer.json
package.json`: no match). They are legacy/manual operator scripts — dangerous
if run, but not on an automated path.

## Out of scope for this Gate 1

- Fixing items 2 and 3 (each becomes its own governed GAP at Gate 1 later).
- Any change to GAP-054's approved packets or implementation.
- Deleting legacy scripts, rotating credentials, deploying, enabling the
  scheduler.
