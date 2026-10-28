---
work_id: GAP-021
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-021/02-design.md
---

# GAP-021 — Tasks API permission and middleware drift: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-021/02-design.md`).

1. **Red:** add `tests/Feature/Api/V1TaskUpdatePermissionTest.php` (users are not super admins) and run it on unchanged
   routes: expect 403 on `PUT|PATCH /api/v1/tasks/{task}` and on the work-template progress route for a `task.update`
   holder, and 201 instead of 400 for suspicious input on `POST /api/v1/tasks`.
2. **Green:** in `src/CoreProject/routes/api.php` wrap the six `/api/v1/tasks*` routes in
   `input.sanitization` + `error.envelope` and change update to `rbac:task.update`; in `src/WorkTemplate/routes/api.php`
   change the three task routes to `rbac:task.update`. Re-run the new test: all pass.
3. Flip the two `assertNotContains` lines in `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php` to
   `assertContains`.
4. Add the GAP-098 OPEN row to `OPERATIONAL_GAP_REGISTER.md` (Tier 2).
5. Verify: Feature/Api, Architecture, Feature and Unit suites; PHPStan; php-cs-fixer; SSOT test lint; route guard;
   deptrac; governance and docs lints.
