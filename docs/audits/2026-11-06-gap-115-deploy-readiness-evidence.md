# GAP-115 — Deploy readiness and production bootstrap: discovery evidence

Date: 2026-11-06. Base: `main` at `a9ff611acb4b033ac96a5cd14426b2dccd4d4c38` (after GAP-114).

Sources: the Owner's in-session request of 2026-11-05 ("Làm mục 1 và 2": a deploy plan, and permission seeding on real
environments) and the code on `main`. Method: static reading; no host, secret or production data was touched.

## 1. What already exists (GAP-049, approved earlier)

| Item | Detail |
|---|---|
| Production deploy | `.github/workflows/production.yml`: manual `workflow_dispatch` with an exact SHA reachable from `main`, release directories over SSH (`/var/www/zena/releases/<sha>`, `current` symlink), environment `production`. |
| Migration safety | `php artisan deploy:migrate` runs pending migrations only if each one is classified `expand` / `breaking` in `database/migrations/classifications.json`; it fails closed otherwise. |
| Scripts | `scripts/deploy/`: `backup.sh`, `restore.sh`, `rollback.sh`, `activate-release.sh`, `link-shared.sh`, `cleanup-releases.sh`, `queue-canary-drill.sh`. `php artisan deploy:queue-canary` checks that a real queue worker is running. |
| Runbooks | `docs/runbooks/gap-049-host-provisioning.md` (one-time host checklist: PHP 8.2, MySQL 8, nginx + TLS, Redis, directory layout); `gap-049-migration-safety.md`; `gap-049-backup-restore.md`; `docs/deploy-runbook.md` (env, queue, cron, health endpoints). |
| First company + admin | `php artisan production:bootstrap --tenant-name --tenant-slug --admin-email`. It creates one tenant and one admin with a generated password, never demo data, and refuses to run twice. It gives the admin the role `super_admin` but **does not create permissions or role grants**. |

## 2. Gaps found

1. **9 migrations are not classified.** These are GAP-106 to GAP-114: Treasury BOQ link, attachments, period lock and
   bank import, advance due date, staff / payroll, OPPM overlay, time entries, labour cost, overhead. As a result
   `deploy:migrate` would stop on the first deploy of the current `main`. All of them create new tables or add nullable
   columns, so they look like `expand`. Each must be checked and recorded.
2. **No production-safe way to load permissions.** `DatabaseSeeder` mixes RBAC seeders with demo data: `TenantSeeder`,
   `ZenaBoqTenantSeeder`, and `UserSeeder`, which creates `admin@zena.local` with password `password`. It must never run
   in production. The RBAC-only seeders need their own idempotent entry point, and a check of what each grants:
   - `ZenaPermissionsSeeder`;
   - role grants: Admin, Treasury, Payroll, Project Manager;
   - `V1PermissionGapSeeder`;
   - work-template baseline.

   Without them the bootstrap admin has a role but no permissions, and payroll, treasury and time pages stay closed.
3. **No way to create the other company roles and accounts** (accountant, PM, staff) except by direct database work.
   Existing admin screens should be checked. Otherwise a small command or page is needed.
4. **A second, legacy production path.** `.github/workflows/release-management.yml` deploys on any pushed `v*` tag: SSH
   to `/opt/zenamanage`, `git pull origin main`, docker-compose. It bypasses the exact-SHA release flow and the migration
   check. It is inert only while its secrets (`PRODUCTION_USERNAME`) are missing.
5. **Scattered and stale deploy documents.** `DEPLOYMENT_GUIDE.md` (2026-02), root and `scripts/` `deploy-production.sh`
   (2025), `DOCKER_PRODUCTION_GUIDE.md`, and `docs/GO_LIVE_CHECKLIST.md` (2026-07) predate GAP-049. They describe other
   flows (Docker, XAMPP paths). There is no single first-go-live checklist that also covers the new monthly payroll →
   overhead order.

## 3. Open points for Gate 2

- Classification of each of the 9 migrations (expected `expand`), plus a test that fails when a migration is unclassified.
- The RBAC entry point: command vs seeder class; which seeders; dry-run / report output; proof it creates no users or
  demo data.
- Creating staff accounts and roles: reuse an existing admin screen, or add a command.
- What to do with the tag-based deploy job: remove, disable, or keep.
- Documentation: one go-live runbook; mark or archive the stale guides.

## 4. Exclusions

Provisioning a real host, setting GitHub secrets, running a deploy, running seeders or bootstrap on a real database.
These stay Owner actions, per the standing rule (no deploy, no production seeding by the agent).
