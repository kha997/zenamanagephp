# CHANGE PROPOSAL: ButtonFormSubmissionTest Audit & Rewrite Plan

## 1. Executive Decision
DEPRECATE+REPLACE. `ButtonFormSubmissionTest` is not a trustworthy single E2E suite for current live UI surfaces; it mixes partial `/app/*` coverage with legacy redirect routes (`/projects`, `/tasks`, `/documents`), mock/demo JS flows, and selectors/assertions that are no longer present. Keep only evidence-backed intent by splitting into new focused suites and freezing/removing stale methods.

## 2. Evidence Summary
- Test scope is mixed in one file: [`tests/Browser/Buttons/ButtonFormSubmissionTest.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/Browser/Buttons/ButtonFormSubmissionTest.php).
- Current app create/edit surfaces exist and are routed:
  - `app/projects/create` -> `Web\ProjectController@create` (`app.projects.create`)
  - `app/projects/{project}/edit` -> `Web\ProjectController@edit` (`app.projects.edit`)
  - `app/tasks/create` -> `Web\TaskController@create` (`app.tasks.create`)
  - `app/documents/create` -> `Web\DocumentController@create` (`app.documents.create`)
  - Verified from `php artisan route:list --json`.
- Legacy routes still exist and are redirect/closure-driven:
  - `GET /projects` -> `legacy.projects` closure + redirect route
  - `POST /projects` -> closure (`projects.store`) in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php:507)
  - `GET /tasks` -> `legacy.tasks` closure + redirect route
  - `POST /tasks` -> closure (`tasks.store`) in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php:576)
  - `GET /tasks/create` -> closure (`tasks.create.form`) in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php:568)
- Project create view is mock/demo-like:
  - Form uses `@submit.prevent="createProject"` and JS `setTimeout`, `alert`, `window.location.href='/projects'` in [`resources/views/projects/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/create.blade.php:73), [`resources/views/projects/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/create.blade.php:456), [`resources/views/projects/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/create.blade.php:461), [`resources/views/projects/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/create.blade.php:464), [`resources/views/projects/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/create.blade.php:469).
- Project edit view is mock/demo-like:
  - Form is JS-only `@submit.prevent="updateProject()"`, simulated save, delayed redirect to `/projects/{id}` in [`resources/views/projects/edit.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/edit.blade.php:69), [`resources/views/projects/edit.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/edit.blade.php:375), [`resources/views/projects/edit.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/projects/edit.blade.php:384).
- Documents create view is disconnected from trusted backend surface:
  - Form action is `/api/v1/upload-document` with `@submit.prevent`, alert/setTimeout flow in [`resources/views/documents/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/documents/create.blade.php:93), [`resources/views/documents/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/documents/create.blade.php:292), [`resources/views/documents/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/documents/create.blade.php:299).
  - No route matches `api/v1/upload-document` in active route list.
  - Active API document route is `POST api/v1/documents` and web store is `POST /documents`.
- Tasks create view has dusk selectors but posts to legacy `/tasks` closure:
  - [`resources/views/tasks/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/tasks/create.blade.php:27), [`resources/views/tasks/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/tasks/create.blade.php:42), [`resources/views/tasks/create.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/tasks/create.blade.php:251).
- Stale selectors in suite methods are absent in current views:
  - `.select-all-checkbox`, `.bulk-actions-button`, `.search-input`, `.search-button`, `.filter-button`, `.filter-form`, `.status-filter`, `.priority-filter`, `.loading-spinner` are not found under relevant `resources/views` files.
- Test upload fixture path is invalid:
  - `attach('file', __DIR__ . '/test-file.txt')` is used, but no such file exists in [`tests/Browser/Buttons`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/tests/Browser/Buttons).

## 3. Test-by-Test Classification
- Method name: `test_project_creation_form`
- Current status: `UPDATE`
- Why: Uses real app route and existing dusk selectors, but submit flow depends on mock JS and legacy redirect behavior.
- Evidence: test lines 70-83; create form `@submit.prevent`, JS redirect to `/projects`.

- Method name: `test_project_edit_form`
- Current status: `SPLIT`
- Why: Edit surface exists, but assertion path `/projects/{id}` and downstream behavior is legacy/permission-sensitive; not a stable app-surface E2E.
- Evidence: test lines 93-104; edit view redirects to `/projects/{id}` via JS.

- Method name: `test_task_creation_form`
- Current status: `UPDATE`
- Why: Route and dusk selectors exist, but posts to legacy `/tasks` closure; current assert validates path, not trusted domain behavior.
- Evidence: test lines 118-130; tasks create action `/tasks`; route `tasks.store` is closure.

- Method name: `test_form_validation`
- Current status: `UPDATE`
- Why: Checks browser-native required constraints on real fields, but still coupled to mock JS submit/cancel behavior and timing.
- Evidence: test lines 141-147; required inputs exist in project create view.

- Method name: `test_form_reset`
- Current status: `DEPRECATE`
- Why: This is not true reset; it depends on cancel navigation and reopening page, plus legacy redirect from `/projects` to `/app/projects`.
- Evidence: test lines 161-166; create view cancel points to `/projects`.

- Method name: `test_form_cancel`
- Current status: `UPDATE`
- Why: Intent is valid, but assertion should be route-name or evidence-backed app destination and must not depend on legacy redirect chain.
- Evidence: test lines 179-181; create view `cancelCreate()` uses `/projects`.

- Method name: `test_file_upload_form`
- Current status: `DEPRECATE`
- Why: Route/path/field model is stale (`/documents/create`, `name` field, `/api/v1/upload-document`, missing fixture).
- Evidence: test lines 192-199; documents create uses `title` not `name`; no `api/v1/upload-document` route; fixture missing.

- Method name: `test_bulk_action_form`
- Current status: `DEPRECATE`
- Why: Selectors and success message are not present in current task index surface.
- Evidence: test lines 225-232; selectors not found in `resources/views/tasks/index.blade.php`.

- Method name: `test_search_form`
- Current status: `DEPRECATE`
- Why: Uses legacy `/projects` and stale selectors/messages not present in current projects index.
- Evidence: test lines 243-250; no `.search-input`/`.search-button` in current projects index.

- Method name: `test_filter_form`
- Current status: `DEPRECATE`
- Why: Uses stale filter button/selectors not present in current projects index.
- Evidence: test lines 262-266; no `.filter-button`, `.filter-form`, `.status-filter`, `.priority-filter` in current projects index.

- Method name: `test_form_loading_states`
- Current status: `DEPRECATE`
- Why: Expects `.loading-spinner` that does not exist; loading state implemented as text toggle only.
- Evidence: test line 286; create view uses `x-show` text spans, no spinner selector.

- Method name: `test_form_error_handling`
- Current status: `DEPRECATE`
- Why: Expects specific server validation messages that are not evidenced on this surface; project store route in testing short-circuits to redirect.
- Evidence: test lines 303-305; `routes/web.php` project store closure redirects in testing.

## 4. Live Surface Map
- Route: `/app/projects/create`
- Backing view/controller: `Web\ProjectController@create` -> `projects.create`
- Key selectors/assertions that appear stable: `@project-name`, `@project-description`, `@project-code`, `@project-start-date`, `@project-end-date`, `@project-status`, `@project-budget-total`, `@project-submit`, `@project-cancel`.
- Caveats: Submit/cancel are JS-driven mock flow (`alert`, `setTimeout`, redirect to `/projects`).

- Route: `/app/projects/{project}/edit`
- Backing view/controller: `Web\ProjectController@edit` -> `projects.edit`
- Key selectors/assertions that appear stable: `@project-name`, `@project-description`, `@project-code`, `@project-status`, `@project-budget-total`, `@project-submit`.
- Caveats: JS-simulated submit and redirect to `/projects/{id}` rather than explicit app route behavior.

- Route: `/app/tasks/create`
- Backing view/controller: `Web\TaskController@create` -> `tasks.create`
- Key selectors/assertions that appear stable: `@task-name`, `@task-description`, `@task-project`, `@task-priority`, `@task-status`, `@task-start-date`, `@task-end-date`, `@task-estimated-hours`, `@task-submit`.
- Caveats: Form action is `/tasks` (legacy closure).

- Route: `/app/documents/create`
- Backing view/controller: `Web\DocumentController@create` -> `documents.create`
- Key selectors/assertions that appear stable: field names `title`, `project_id`, `description`, `file`.
- Caveats: No dusk selectors; form posts to missing `/api/v1/upload-document`; flow is mock JS.

## 5. Legacy / Stale Surface Map
- `/projects`, `/tasks`, `/documents/create` assumptions inside this suite are legacy/redirect or closure surfaces, not clean app-route E2E anchors.
- Selector families not present in current UI: `.select-all-checkbox`, `.bulk-actions-button`, `.bulk-actions-menu`, `.bulk-status-change`, `.bulk-status-select`, `.apply-bulk-action`, `.search-input`, `.search-button`, `.filter-button`, `.filter-form`, `.status-filter`, `.priority-filter`, `.loading-spinner`.
- Upload assumptions are stale: `name` input for document title, `form[action="/api/v1/upload-document"]`, and local fixture `test-file.txt`.
- Project create/edit/document create views contain mock indicators (`@submit.prevent`, `alert`, `setTimeout`, hardcoded redirects) rather than robust server-assertable workflows.

## 6. Rewrite Strategy
- Phase 1: stop-the-bleeding actions
- Freeze `ButtonFormSubmissionTest` from further patching except critical unblockers.
- Mark stale methods for deprecation in Change Proposal record and stop relying on them for release confidence.
- Keep temporary smoke-only checks only where route+selector evidence is strong.

- Phase 2: create new trusted smoke/browser suites
- Build route-anchored tests on `/app/projects/create`, `/app/projects/{project}/edit`, `/app/tasks/create`.
- Prefer dusk attributes and route names over CSS utility class selectors.
- Assert deterministic outcomes: page load, required fields, button enabled/disabled states, and path transitions that are explicitly defined.

- Phase 3: deprecate or remove stale suite parts
- Remove/deprecate bulk/search/filter/loading/error/upload methods from `ButtonFormSubmissionTest` after replacement suites are in place.
- Remove all assertions tied to legacy endpoints (`/projects`, `/tasks`, `/documents/create`) unless explicitly testing legacy redirects.

- Phase 4: longer-term cleanup
- Align create/edit/document views with real store/update endpoints and replace mock JS submit handlers.
- Add explicit dusk selectors to document/create and any live search/filter/bulk surfaces before browser coverage is reintroduced.
- Reduce route duplication/closures in `routes/web.php` to eliminate redirect/legacy ambiguity.

## 7. Proposed Test File Split
- `tests/Browser/Projects/ProjectCreateTest.php`
- `tests/Browser/Projects/ProjectEditTest.php`
- `tests/Browser/Tasks/TaskCreateTest.php`
- `tests/Browser/Documents/DocumentCreateSurfaceTest.php`

## 8. Risks and Assumptions
- PROVEN
- Current suite gives false confidence by mixing live app routes with legacy closures/redirects.
- Multiple methods assert selectors/messages that do not exist in current views.
- Document upload path under test does not map to an active route.

- UNKNOWN
- Whether `/app/documents/create` is intended to be fully live or still transitional mock UI.
- Whether `/projects/{id}` behavior is intentionally protected for these browser test users or currently misconfigured.
- Whether existing console/runtime errors are reproducible in current branch without running browser session.

## 9. Change Proposal Recommendation
Do not continue patching `ButtonFormSubmissionTest` as a single suite. Freeze it and replace it with focused route-anchored suites for project create/edit and task create, while formally deprecating stale methods tied to legacy selectors/routes and mock upload flows. This yields lower flake risk and restores confidence that browser tests reflect current production-aligned UI surfaces.
