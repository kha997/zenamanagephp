---
work_id: GAP-098
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-098/02-design.md
---

# GAP-098 — Unseeded `/api/v1` permission codes: implementation plan

Executes approved Gate 2 Option A with Owner amendment 1 (`docs/owner-decisions/GAP-098/02-design.md`).

1. **Red:** add `tests/Feature/Architecture/RouteRbacCodesSeededTest.php` seeding the RBAC block of `DatabaseSeeder` and
   asserting every dotted `rbac:<code>` on a route exists in `permissions`; expect the unseeded codes listed (33 with
   `component.view`, see amendment 1). Add `tests/Feature/Api/V1PermissionGapTest.php` (non-super-admin seeded roles);
   expect 403s.
2. **Green:** rename `project.edit`, `component.edit`, `component.view` (amendment 1) in `src/CoreProject/routes/api.php`
   and `change_request.view|stats|edit` in `src/ChangeRequest/routes/api.php`; add `database/seeders/V1PermissionGapSeeder.php`
   (27 codes, default grants) and register it in `DatabaseSeeder`; add it to the guard test's seeder list.
3. Verify: both new tests green; RBAC, GAP-042, Api, Architecture, Feature and Unit suites; PHPStan; php-cs-fixer; SSOT
   lint; route guard; deptrac; governance and docs lints.
