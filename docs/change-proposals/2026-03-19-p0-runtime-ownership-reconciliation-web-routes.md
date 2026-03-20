# P0 Runtime Ownership Reconciliation for Web Routes and Blade Targets

Date: 2026-03-19
Repo: `zenamanage-golden`
Scope: docs-only change proposal for the current P0 cluster
Constraints:
- no runtime code changes in this round
- do not expand into Slack, workflows, CI DB lanes, or ULID cleanup
- universal-frame and final-integration hardening remain settled context

## Top Findings

1. `/app/projects/{project}` is registered as a canonical app HTML route, but its current handler returns JSON via `Web\ProjectController::show()`. This is the highest-severity ownership mismatch in the current P0 cluster.
2. Root `/projects/*` still carries a parallel web-mounted JSON/stub surface even though `/app/projects/*` and `resources/views/projects/*.blade.php` clearly signal Blade ownership for project UI.
3. Root `/tasks/*` is not a full mirror of the project drift, but it is still load-bearing because task creation and several task UI redirects land on root paths instead of the `/app/tasks/*` namespace.
4. `/app/settings/general`, `/app/settings/security`, and `/app/settings/notifications` are live web routes pointing to missing Blade files. These are direct render-time failures, not just stale names.
5. Missing `invitations.expired` exists in code, but it is not part of the current live P0 route cluster because the routed acceptance flow uses `AuthenticatedInvitationAcceptController`, not `InvitationController::accept()`.

## P0 Ownership Map

| Current route | Current controller / closure behavior | Expected ownership signal | Actual behavior | Mismatch type | Recommended action | Disposition |
| --- | --- | --- | --- | --- | --- | --- |
| `GET /app/projects` | `Web\AppController::projects()` returns `view('layouts.app-layout', compact('projects'))` | HTML Blade app surface | HTML route, but backed by layout-level shell rather than `projects.index` | weak ownership signal, not primary mismatch | keep as canonical app entry for project list in this round; reconcile list-page ownership in later round | should remain HTML |
| `GET /app/projects/create` | `Web\ProjectController::create()` returns `view('projects.create')` | HTML Blade | HTML Blade | no runtime mismatch | keep canonical create page | should remain HTML |
| `GET /app/projects/{project}` | `Web\ProjectController::show()` returns `JsonResponse` / JSend payload | HTML Blade, because URI is under `/app/*`, route name is `app.projects.show`, and `resources/views/projects/show.blade.php` exists | JSON response | HTML-vs-JSON ownership mismatch | convert runtime owner to HTML Blade detail page; move canonical JSON detail contract to `/api/zena/projects/{project}` while leaving `/api/v1/projects/{project}` mounted only as compatibility runtime | should remain HTML |
| `GET /app/projects/{project}/edit` | `Web\ProjectController::edit()` returns `view('projects.edit')` | HTML Blade | HTML Blade | no primary mismatch | keep canonical edit page | should remain HTML |
| `GET /app/settings` | closure returns `view('settings.index')` | HTML Blade | HTML Blade | no runtime mismatch | keep as canonical settings landing page | should remain HTML |
| `GET /app/settings/general` | closure returns `view('settings.general')` | HTML Blade | missing Blade target | broken template target | replace with redirect to `/app/settings` or remove until real page exists; do not leave as broken route | should redirect |
| `GET /app/settings/security` | closure returns `view('settings.security')` | HTML Blade | missing Blade target | broken template target | replace with redirect to `/app/settings` or remove until real page exists | should redirect |
| `GET /app/settings/notifications` | closure returns `view('settings.notifications')` | HTML Blade | missing Blade target | broken template target | replace with redirect to `/app/settings` or remove until real page exists | should redirect |
| `GET /projects` | both `legacy.projects` redirect closure and `Route::permanentRedirect('/projects', '/app/projects')` are registered; route list shows legacy + redirect entries | redirect-only legacy surface | redirect surface with duplicated registration | duplicate legacy ownership / route clutter | keep one redirect owner only; remove duplicate registration path in implementation round | should redirect |
| `GET /projects/create` | closure returns minimal CSRF form stub | either redirect to `/app/projects/create` or archived legacy form, but not canonical UI | stub HTML form | stub-vs-canonical HTML mismatch | retire stub and replace with redirect to `/app/projects/create` | should redirect |
| `POST /projects` | closure creates project directly and returns JSON; testing env redirects to `/projects` | JSON/API-style if retained anywhere | JSON on web route, plus testing-only redirect branch | web-mounted API ownership mismatch | archive/remove web POST handler and require canonical mutation ownership at `/api/zena/projects`; `/api/v1/projects` stays mounted only as compatibility runtime | should remain JSON/API-style, but only under API namespace |
| `GET /projects/{project}` | closure returns JSON project payload | JSON/API-style if retained anywhere | JSON on web route | web-mounted API ownership mismatch | archive/remove root web GET detail route; HTML ownership should live at `/app/projects/{project}` and canonical API ownership at `/api/zena/projects/{project}` while `/api/v1/projects/{project}` stays compatibility-only | should be archived/removed |
| `PUT /projects/{project}` | closure updates model and returns JSON | JSON/API-style | JSON on web route | web-mounted API ownership mismatch | archive/remove root web update route and keep canonical update ownership in `/api/zena/projects/{project}`; do not treat `/api/v1/projects/{project}` as the forward owner | should be archived/removed |
| `DELETE /projects/{project}` | closure deletes model and returns JSON | JSON/API-style | JSON on web route | web-mounted API ownership mismatch | archive/remove root web delete route and keep canonical delete ownership in `/api/zena/projects/{project}`; do not treat `/api/v1/projects/{project}` as the forward owner | should be archived/removed |
| `GET /app/tasks` | `Web\AppController::tasks()` returns `view('tasks.index')` | HTML Blade app surface | HTML Blade | no primary mismatch | keep canonical task list page | should remain HTML |
| `GET /app/tasks/create` | `Web\TaskController::create()` returns `view('tasks.create')` | HTML Blade | HTML Blade | no route-level mismatch, but its form posts to root `/tasks` | downstream submit-target drift | keep page, but future runtime fix must repoint submit flow to canonical owner | should remain HTML |
| `GET /app/tasks/{task}` | `Web\TaskController::show()` returns `view('tasks.show')` | HTML Blade | HTML Blade | no primary mismatch | keep canonical detail page | should remain HTML |
| `GET /app/tasks/{task}/edit` | `Web\TaskController::edit()` returns `view('tasks.edit')` | HTML Blade | HTML Blade | no primary mismatch | keep canonical edit page | should remain HTML |
| `GET /tasks` | both `legacy.tasks` redirect closure and `Route::permanentRedirect('/tasks', '/app/tasks')` are registered; route list shows legacy + redirect entries | redirect-only legacy surface | redirect surface with duplicated registration | duplicate legacy ownership / route clutter | keep one redirect owner only; remove duplicate registration path in implementation round | should redirect |
| `GET /tasks/create` | closure returns minimal CSRF form stub | either redirect to `/app/tasks/create` or archived legacy form, but not canonical UI | stub HTML form | stub-vs-canonical HTML mismatch | replace with redirect to `/app/tasks/create` | should redirect |
| `POST /tasks` | closure returns `{"message":"Task created"}` JSON | JSON/API-style if retained anywhere | JSON stub on web route | web-mounted API/stub mismatch | archive/remove root web POST handler and keep mutation in `/api/v1/tasks` | should remain JSON/API-style, but only under API namespace |

## Additional P0-Adjacent Checks

### Missing enhanced targets

- `GET /admin-dashboard-enhanced` and `GET /projects-enhanced` still point at missing views in `routes/web.php`, but they are not part of the current P0 runtime ownership cluster requested for this round.
- They should stay out of implementation scope for this round and remain backlog material.

### Invitation expired target

- `InvitationController::accept()` still references `view('invitations.expired')`, which is missing.
- Current live route ownership does not use that method for invitation acceptance. `GET /invitations/accept/{token}` is routed to `AuthenticatedInvitationAcceptController::show()`.
- Result: this is real debt, but not part of the current P0 runtime cluster unless route ownership is re-opened later.

## Minimal Change Proposal

1. Reconcile project detail ownership first.
   - Make `/app/projects/{project}` the only canonical HTML project detail route.
   - Remove root `/projects/{project}` JSON ownership from web routes.
   - Keep canonical JSON detail ownership at `/api/zena/projects/{project}` and treat `/api/v1/projects/{project}` as compatibility-only.
2. Collapse root project/task legacy web surfaces into redirects only.
   - `/projects` -> `/app/projects`
   - `/projects/create` -> `/app/projects/create`
   - `/tasks` -> `/app/tasks`
   - `/tasks/create` -> `/app/tasks/create`
   - Remove duplicate redirect registrations so each legacy URI has exactly one owner.
3. Remove web-mounted mutation handlers from root routes.
   - retire `POST /projects`, `PUT /projects/{project}`, `DELETE /projects/{project}`, and `POST /tasks`
   - keep Project create/update/delete ownership under `/api/zena/projects*`; preserve `/api/v1/projects*` as mounted compatibility runtime
4. Stop exposing broken settings subpages.
   - redirect `/app/settings/general`, `/app/settings/security`, and `/app/settings/notifications` to `/app/settings` until real pages exist

## Recommended Next Engineering Action

Execute one narrow runtime PR that rewires only the P0 routes above so every root `/projects*` and `/tasks*` legacy path becomes redirect-only, `/app/projects/{project}` becomes HTML-owned, and broken `/app/settings/*` subpages stop resolving to missing Blade targets.

## Risk Assessment

- Medium user-flow risk: project and task Blade pages currently hardcode root `/projects*` and `/tasks*` links/forms in multiple places, so route-level reconciliation will likely require coordinated Blade target updates in the implementation round.
- Medium test risk: browser and feature suites still encode historical root-route behavior, including redirects and JSON expectations on `/projects*` and `/tasks*`.
- Low API risk if implementation is disciplined: canonical Project API ownership exists under `/api/zena/projects`, and `/api/v1/projects` remains mounted for compatibility, so removing root web JSON handlers should reduce ambiguity rather than remove a necessary data surface.
- Medium operational risk from duplicate route declarations: route precedence is currently harder to reason about because `/projects` and `/tasks` each appear as both legacy routes and permanent redirects.

## UNKNOWN

- It is not yet proven whether any production callers still depend on root web JSON mutations such as `POST /projects` or `POST /tasks`; current evidence comes from route code and tests, not traffic telemetry.
- `/app/projects` currently renders `layouts.app-layout` rather than an obvious dedicated project index view. That may be intentional shell composition or an incomplete ownership move.
- The full blast radius of updating Blade hardcoded URLs is not closed in this document. The largest visible drift is in `resources/views/projects/*.blade.php` and `resources/views/tasks/*.blade.php`, but a full implementation round should re-scan the entire repo before edits.

## Evidence Anchors

- `routes/web.php:383-470` for `/app/projects*`, `/app/tasks*`, and `/app/settings*`
- `routes/web.php:497-577` for root `/projects*` and `/tasks*` legacy runtime
- `app/Http/Controllers/Web/ProjectController.php:50-80` for HTML create/edit ownership
- `app/Http/Controllers/Web/ProjectController.php:125-141` for JSON `show()` behavior
- `app/Http/Controllers/Web/TaskController.php:86-153` for HTML task create/show/edit ownership
- `app/Http/Controllers/Web/InvitationController.php:147-155` for missing `invitations.expired` reference
- `resources/views/projects/create.blade.php` and `resources/views/tasks/create.blade.php` for current form targets still posting to root legacy routes

## Exact Files Touched

- `docs/change-proposals/2026-03-19-p0-runtime-ownership-reconciliation-web-routes.md`
