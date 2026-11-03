---
work_id: GAP-104
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-104/02-design.md
---

# GAP-104 — Project OPPM Phase 1 (read-only): implementation plan

Executes Gate 2 V-1, W-A, T-1, R-1 (`docs/owner-decisions/GAP-104/02-design.md`).

1. **Red:** `tests/Feature/ProjectOppm/ProjectOppmPageTest.php` fails (no route, no service); the new
   `ProjectHealthReadServiceTest` case for `includeTreasuryAlerts: false` fails (argument not supported).
2. **Green — health flag (T-1):** `ProjectHealthReadService::forProject($project, bool $includeTreasuryAlerts = true)`;
   when false, `treasury_alerts.availability = not_requested` and no dashboard call.
3. **Green — read service:** `app/Services/ProjectOppm/ProjectOppmReadService::forProject()`:
   - header with Service Lines;
   - root-component work groups (max 12; descendants' tasks rolled up in memory);
   - task-status summary;
   - top-10 blocked tasks, overdue tasks and blocked design items, each with its total.

   Fixed query count; tenant- and project-scoped.
4. **Green — page (V-1, R-1):**
   - Route `GET /app/projects/{project}/oppm` with `rbac:project.view`.
   - `Web\ProjectOppmController@show` uses a tenant-checked `findOrFail`.
   - `projects/oppm.blade.php` with a CSS timeline and an `@media print` A4 landscape layout.
   - Treasury block behind `treasury.view-project`.
   - "OPPM một trang" button on `projects/show.blade.php`.
5. **Verify:**
   - Query count equal for small and large projects.
   - PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac.
   - Unit + Feature + Integration and `tests/Feature/Api`.
6. **Gate 3:** release packet with subject and implementation-tree digest.
