# ButtonFormSubmissionTest Phase 1 Freeze Note

Status: frozen legacy coverage.

Reason:
- `tests/Browser/Buttons/ButtonFormSubmissionTest.php` is a mixed legacy/live suite with stale selectors/routes and mock-flow assumptions.
- Freeze decision and evidence are documented in:
  - `docs/change-proposals/button-form-submission-audit-rewrite-plan.md`

Phase 1 action applied:
- Legacy suite is explicitly skipped in test setup to stop blind patching while preserving traceability of prior coverage intent.

Next replacement suites (from proposal, evidence-backed only):
- `tests/Browser/Projects/ProjectCreateTest.php` (`/app/projects/create`)
- `tests/Browser/Projects/ProjectEditTest.php` (`/app/projects/{project}/edit`)
- `tests/Browser/Tasks/TaskCreateTest.php` (`/app/tasks/create`)
- `tests/Browser/Documents/DocumentCreateSurfaceTest.php` (`/app/documents/create` surface only)

Not done in Phase 1:
- No replacement suites implemented yet.
- No app/domain/routes/controllers/views/workflows changed.
