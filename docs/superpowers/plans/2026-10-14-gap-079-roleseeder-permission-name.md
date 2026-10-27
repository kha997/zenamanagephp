---
work_id: GAP-079
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-079/02-design.md
---

# GAP-079 — RoleSeeder permission name: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-079/02-design.md`).

1. **Red** — remove the AUD-28 allowlist from `AudChangeRequestPermissionSeedingTest`; run it on the
   unchanged seeder and record the failure (`project.read`, `user.manage`).
2. **Fix** — `RoleSeeder` sets `name => code`; add the data-only backfill migration and its `expand`
   classification; add `PermissionNameBackfillMigrationTest`.
3. **Docs** — mark AUD-28 fixed by GAP-079 in the audit index.
4. **Verify** — both tests green, Unit/Feature/Integration suites, PHPStan, lints, exact-head CI.
