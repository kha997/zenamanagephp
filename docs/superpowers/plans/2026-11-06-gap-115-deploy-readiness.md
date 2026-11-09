---
work_id: GAP-115
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-115/02-design.md
---

# GAP-115 — Deploy readiness and production RBAC bootstrap: implementation plan

Executes Gate 2 M-1, R-1, U-1, W-1, D-1 (`docs/owner-decisions/GAP-115/02-design.md`). One PR, allowlist as in that packet.

1. **Tests first (red):** `MigrationClassificationCoverageTest`, `ProductionRbacCommandTest`, `ProductionUserCommandTest`,
   `ReleaseWorkflowNoProductionDeployTest` in `tests/Feature/Deployment/`.
2. **M-1:** add the 9 GAP-106..114 migrations to `database/migrations/classifications.json` as `expand`.
3. **R-1:** `production:rbac {--dry-run}`.
   - Creates the canonical roles: `super_admin`, `Finance`, `Project Manager`, `site_engineer`, `Project Member`.
   - Runs the permission-only seeders: Zena permissions, admin, Treasury, Payroll, PM, V1 gap.
   - Runs the work-template baseline when a tenant exists.
   - Runs in one transaction and prints a role → permission count summary.
4. **U-1:** `production:user --email --name --role [--tenant] [--reset-password]`.
   - Canonical roles only; refuses duplicate emails.
   - Generates a 20-character password, shown once.
   - Writes an AuditLog entry.
5. **W-1:** remove the `deploy-release` job from `release-management.yml`.
6. **D-1:** write `docs/runbooks/go-live.md`; add outdated banners to the legacy deploy docs and scripts.
7. **Verify:**
   - full suites;
   - on MySQL 8 with an empty database, run the runbook order: migrate → rbac → bootstrap → user → login to the payroll
     page;
   - PHPStan, php-cs-fixer, SSOT lint, deptrac.
