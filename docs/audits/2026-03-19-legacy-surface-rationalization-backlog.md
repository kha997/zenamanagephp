# Legacy Surface Rationalization Backlog

Date: 2026-03-19
Repo: `zenamanage-golden`
Scope: docs-only backlog for remaining Blade/web route mismatches and stale view ownership
Constraints:
- no runtime behavior changes
- do not expand scope into Slack, workflows, CI DB lanes, or ULID cleanup
- universal-frame and final-integration route hardening are already treated as settled context, not open work in this round

## Inputs Consolidated

- [`docs/audits/2026-03-19-blade-ownership-reachability-audit.md`](./2026-03-19-blade-ownership-reachability-audit.md)
- [`docs/audits/2026-03-19-universal-frame-ownership-audit.md`](./2026-03-19-universal-frame-ownership-audit.md)
- [`docs/repo-architecture-review.md`](../repo-architecture-review.md)
- [`docs/architecture/routing-architecture.md`](../architecture/routing-architecture.md)
- direct route/view/controller evidence in:
  - `routes/web.php`
  - `app/Http/Controllers/Web/*`
  - `resources/views/*`

## Top Findings

1. Several reachable web routes still point at Blade views that do not exist on disk, so the current repo contains known HTML entrypoints that would fail at render time.
2. The web layer still mixes HTML routes and API-style behavior. Some route names and URIs imply Blade ownership, but the effective response is JSON or a stub form response instead of a maintained page.
3. The `_debug` namespace still owns multiple Blade demo/test pages that look product-adjacent enough to confuse future contributors about canonical web surfaces.
4. `layouts/universal-frame` is no longer an active ownership question at the top level, but its support-component cluster remains a stale shell surface with no canonical production route owner.
5. Admin-facing orphan artifacts remain on disk in production-looking locations such as `resources/views/admin/*/index.blade.php`, even though runtime routes render different files.

## Rationalization Backlog

### 1. Broken route targets

| ID | Item / cluster | Evidence | Runtime impact | Contributor confusion risk | Recommended disposition | Priority |
| --- | --- | --- | --- | --- | --- | --- |
| BR-1 | Missing template create/show views | `routes/web.php` registers `app.templates.create` and `app.templates.show` via `view('templates.create')` and `view('templates.show')`, but `resources/views/templates/create.blade.php` and `resources/views/templates/show.blade.php` are missing. Existing template views on disk are only `index`, `builder`, `construction-builder`, and `analytics`. | `GET /app/templates/create` and `GET /app/templates/{template}` would fail with missing view errors. | High: the route family makes template CRUD look live when only a subset of template pages exists. | `change-proposal-needed` | P0 |
| BR-2 | Missing settings subpage views | `routes/web.php` registers `app.settings.general`, `app.settings.security`, and `app.settings.notifications` via `view('settings.general')`, `view('settings.security')`, and `view('settings.notifications')`, but only `resources/views/settings/index.blade.php` exists. | `GET /app/settings/general`, `/app/settings/security`, and `/app/settings/notifications` would fail at render time. | High: settings looks like a full Blade subtree but is only partially implemented. | `change-proposal-needed` | P0 |
| BR-3 | Missing standalone enhanced pages | `routes/web.php` still defines `/admin-dashboard-enhanced` -> `view('admin.dashboard-enhanced')` and `/projects-enhanced` -> `view('app.projects-enhanced')`, but neither Blade file exists on disk. | Reachable top-level routes fail immediately when accessed. | Medium-high: names imply sanctioned alternates for admin/projects surfaces. | `delete-candidate` | P1 |
| BR-4 | Missing invitation expired view | `App\Http\Controllers\Web\InvitationController::accept()` returns `view('invitations.expired')`, but `resources/views/invitations/expired.blade.php` is missing; only `create`, `accept`, `manage`, and `index` exist. | Expired or invalid invitation flow would fail instead of rendering a terminal page. | Medium: contributors may assume invitation web UX is complete because the controller is present. | `change-proposal-needed` | P1 |

### 2. Route/controller/view mismatches

| ID | Item / cluster | Evidence | Runtime impact | Contributor confusion risk | Recommended disposition | Priority |
| --- | --- | --- | --- | --- | --- | --- |
| MM-1 | `/app/projects/{project}` route points to a Web controller method that returns JSON, not HTML | `routes/web.php` maps `GET /app/projects/{project}` to `App\Http\Controllers\Web\ProjectController@show`, but `show()` returns `JsonResponse` and wraps a project resource instead of rendering `resources/views/projects/show.blade.php`. A Blade file for `projects.show` exists on disk. | Web navigation into a project detail path does not match page ownership implied by the route and file tree. | High: contributors can easily edit `resources/views/projects/show.blade.php` and never affect the actual route. | `change-proposal-needed` | P0 |
| MM-2 | Root-level project routes behave like API endpoints while parallel Blade pages also exist | `routes/web.php` defines `POST /projects`, `GET /projects/{project}`, `PUT /projects/{project}`, and `DELETE /projects/{project}` as JSON closures. In the same repo, `/app/projects/*` routes and `resources/views/projects/*.blade.php` suggest Blade ownership for project UI. `docs/repo-architecture-review.md` also flags web-mounted API-style surfaces as structural debt. | Split behavior across root web routes and `/app/*` routes increases contract drift and weakens discoverability. | High: route names like `projects.show`, `projects.update`, and `projects.destroy` look canonical but are actually API-like web closures. | `change-proposal-needed` | P0 |
| MM-3 | Root-level `tasks.create` and `documents.create` are stub HTML forms while real Blade pages exist elsewhere | `routes/web.php` defines `GET /tasks/create` and `GET /documents/create` as literal response stubs containing a CSRF form, while `/app/tasks/create` and `/app/documents/create` route to Web controllers that render Blade pages. | Users or tests hitting legacy names land on placeholder forms rather than maintained UI. | Medium-high: legacy route names overlap with real page ownership and can mislead test writers. | `archive` | P1 |
| MM-4 | Root-level `tasks.store` is a JSON web closure while task UI lives under `/app/tasks/*` | `routes/web.php` defines `POST /tasks` as a closure returning `{"message":"Task created"}` while `App\Http\Controllers\Web\TaskController` owns HTML task pages and dedicated CRUD logic. | Partial task flow lives outside the canonical `/app` surface and does not reflect real task UI behavior. | Medium-high: route name `tasks.store` looks like the natural pair for Blade task forms but is not owned by the same surface. | `archive` | P2 |

### 3. Debug/demo pages

| ID | Item / cluster | Evidence | Runtime impact | Contributor confusion risk | Recommended disposition | Priority |
| --- | --- | --- | --- | --- | --- | --- |
| DD-1 | `_debug` suite still exposes archived demo/test Blade pages | `routes/web.php` keeps `_debug/testing-suite`, `_debug/performance-optimization`, `_debug/final-integration`, `_debug/test-mobile-optimization`, `_debug/test-mobile-simple`, `_debug/test-accessibility`, and `_debug/test-permissions`. The earlier Blade audit already classified several of these as archive/debug surfaces only. | Limited production impact because DebugGate gates them, but they still remain reachable in debug contexts. | High: pages under `resources/views/test*.blade.php`, `performance-optimization.blade.php`, and `final-integration.blade.php` still look like active product surfaces. | `archive` | P1 |
| DD-2 | `testing-suite` still points at non-canonical demo inventory | `resources/views/testing-suite.blade.php` now contains deprecation language for smart-tools and universal-frame, but it still catalogs test/demo entrypoints such as `/test-universal-frame`. | No direct runtime failure required; the risk is continued discoverability of dead or non-canonical pages. | High: contributors may still treat the page as an endorsed UI index. | `archive` | P2 |
| DD-3 | `demo.blade.php` remains on disk without a clear route owner | The Blade ownership audit already marked `resources/views/demo.blade.php` as archive material. No canonical route ownership was established in the current route map. | Low direct impact if unrouted, but it preserves stale surface area. | Medium: generic names like `demo.blade.php` attract speculative reuse. | `archive` | P3 |

### 4. Orphan admin artifacts

| ID | Item / cluster | Evidence | Runtime impact | Contributor confusion risk | Recommended disposition | Priority |
| --- | --- | --- | --- | --- | --- | --- |
| OA-1 | `admin/users/index.blade.php` and `admin/tenants/index.blade.php` are production-looking but unrouted | The universal-frame audit confirmed both files extend `layouts.universal-frame`, while `routes/web.php` renders `view('admin.users')` and `view('admin.tenants')` for current admin routes. Inline Blade comments already mark both as non-canonical orphan artifacts. | Low immediate runtime impact because routes bypass these files, but stale code remains available for accidental edits. | High: filenames match standard Laravel conventions for active index pages. | `archive` | P1 |
| OA-2 | Admin alternate/dashboard builder artifacts have unclear route ownership | Files such as `resources/views/admin/dashboard-layout-system.blade.php`, `sidebar-builder*.blade.php`, `simple-sidebar-builder.blade.php`, `users-content.blade.php`, `projects-content.blade.php`, `tenants-content.blade.php`, and other `*-content.blade.php` variants exist alongside simpler routed pages. Current `routes/web.php` only proves ownership for a subset such as `admin.dashboard`, `admin.dashboard-css-inline`, `admin.projects`, `admin.users`, and `admin.tenants`. | Unknown runtime impact because some variants may be partially wired, but ownership is not obvious from route naming alone. | High: admin view tree currently contains multiple parallel versions of dashboard/sidebar/content surfaces. | `UNKNOWN` | P2 |

### 5. Unknown shell/component clusters

| ID | Item / cluster | Evidence | Runtime impact | Contributor confusion risk | Recommended disposition | Priority |
| --- | --- | --- | --- | --- | --- | --- |
| UC-1 | Universal-frame support components remain a non-canonical shell cluster | `docs/audits/2026-03-19-universal-frame-ownership-audit.md` classifies `components.universal-header`, `universal-navigation`, `kpi-strip`, `alert-bar`, `activity-panel`, `mobile-fab`, `mobile-drawer`, and `mobile-navigation` as shell support inherited from `layouts/universal-frame`, with no canonical production route owner. | Low direct impact until revived, but any attempted reuse risks pulling in a stale admin-ish shell contract. | High: these components look reusable and production-ready despite unresolved ownership. | `UNKNOWN` | P1 |
| UC-2 | Accessibility/mobile component cluster is still shell-adjacent and not cleanly owned | The Blade ownership audit classified `components/mobile-navigation.blade.php` and `components/accessibility-dashboard.blade.php` as mixed or shell/test dependent. Additional files such as `accessibility-skip-links`, `accessibility-color-contrast`, `accessibility-focus-manager`, and `accessibility-aria-labels` remain on disk without a current canonical page map in the audited route set. | Unknown direct runtime effect; components may be safe but lack clear owner pages. | Medium-high: future contributors may treat them as shared primitives when they may actually be debug-era shell support. | `UNKNOWN` | P2 |

## Priority Ordering

1. `P0`
   - BR-1 Missing template create/show views
   - BR-2 Missing settings subpage views
   - MM-1 `/app/projects/{project}` JSON-vs-Blade mismatch
   - MM-2 Root-level project web routes behaving as API endpoints
2. `P1`
   - BR-3 Missing standalone enhanced pages
   - BR-4 Missing invitation expired view
   - MM-3 Root-level task/document create stub routes
   - DD-1 `_debug` archived demo/test pages
   - OA-1 Orphan admin index artifacts
   - UC-1 Universal-frame support component cluster
3. `P2`
   - MM-4 Root-level `tasks.store` JSON closure
   - DD-2 `testing-suite` as non-canonical surface index
   - OA-2 Unknown admin alternate/content variants
   - UC-2 Accessibility/mobile shell-adjacent component cluster
4. `P3`
   - DD-3 `demo.blade.php`

## Recommended Next Engineering Round

Run one focused runtime ownership round for web Blade rationalization:

1. remove or redirect broken Blade route targets that currently point to missing views
2. reconcile web routes whose current handlers return JSON/stub responses despite Blade ownership signals
3. archive or relocate `_debug` demo pages and orphan admin/universal-frame artifacts behind explicit non-canonical structure
4. end with one route-to-view ownership map proving which Blade surfaces remain canonical

## Exact Files Touched

- `docs/audits/2026-03-19-legacy-surface-rationalization-backlog.md`
