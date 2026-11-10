---
work_id: GAP-119
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-119/02-design.md
---

# GAP-119 — Broken project analytics API and dead event code: implementation plan

Executes Gate 2 A-1, E-1, P-1 (`docs/owner-decisions/GAP-119/02-design.md`). Only files in the packet's allowlist change.

1. **Red:** add `tests/Feature/Architecture/DeadProjectAnalyticsAndEventCodeRemovedTest.php` and confirm it fails. It
   checks that:
   - the routes, classes, `ProjectActivity` helpers and `Project::calculateProgress` are gone;
   - `api/analytics/dashboard`, `ProjectActivity::log` / `logProjectCreated` and `Project::recalculateProgress` remain.
2. **A-1:** remove the four routes from `routes/api.php`, then delete `ProjectAnalyticsController` and
   `ProjectAnalyticsService`.
3. **E-1:**
   - delete the three events, `LogProjectActivity` and `RealTimeNotificationService`;
   - remove the three `ProjectActivity::log*` helpers and any imports they alone used.
4. **P-1:** remove `Project::calculateProgress()` and `ProjectModuleTest::test_project_progress_calculation`.
5. **Housekeeping:**
   - remove the `phpstan-baseline.neon` entries for the deleted files;
   - note the removal in `docs/architecture/project-model-reference-inventory.md`;
   - fix the comment in `ProjectMilestoneServiceTest`.
6. **Green:**
   - guard test passes;
   - `route:list` runs;
   - full suites pass;
   - PHPStan, php-cs-fixer, `ssot:lint`, deptrac and governance lint pass.
