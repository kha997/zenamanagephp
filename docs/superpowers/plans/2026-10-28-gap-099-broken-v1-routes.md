---
work_id: GAP-099
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-099/02-design.md
---

# GAP-099 — Broken `/api/v1` project update and change-request routes: implementation plan

Executes approved Gate 2 Option A with Owner amendment 1 (`docs/owner-decisions/GAP-099/02-design.md`).

1. **Red:** add `tests/Feature/Api/V1ProjectAndChangeRequestRoutesTest.php`: v1 project rename, status change and delete
   succeed; the ten retired change-request route names are absent and `change-requests.store` remains POST only; v1
   change-request creation still answers 201. Expect 3 of 4 failing (500, 500, routes present).
2. **Green:** `UpdateProjectRequest` status rule uses `Project::VALID_STATUSES`; v1 `ProjectController::update()` and
   `destroy()` dispatch `ProjectUpdated` with the `App\Models\Project` built from the same row and capture `$oldData`
   before deleting (amendment 1); `src/ChangeRequest/routes/api.php` keeps only `POST /`.
3. Verify: Feature/Api, Architecture, IntegrationTest, RouteHygieneTest, Feature and Unit suites; PHPStan; php-cs-fixer;
   `composer ssot:lint`; route guard; deptrac; governance and docs lints.
