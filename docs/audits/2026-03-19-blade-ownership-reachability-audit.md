# Blade Ownership + Reachability Audit

Date: 2026-03-19
Repo: `zenamanage-golden`
HEAD audited: `afd8b6aa5906d56772744a662fe41b970148ab33`
Scope: docs-only audit, no UI/route/code changes

## Follow-up Deprecation Status

- Status after follow-up formalization: smart-tools Blade stack is now explicitly marked `DEPRECATED` / `NON-CANONICAL`.
- Archive-target files:
  - `resources/views/components/analysis-drawer.blade.php`
  - `resources/views/components/export-component.blade.php`
  - `resources/views/components/smart-filters.blade.php`
  - `resources/views/components/smart-search.blade.php`
  - `resources/views/test-smart-tools.blade.php`
- Revival rule: do not revive any of the above without a new ownership decision naming one canonical runtime owner and reconciling routes/API contracts.
- `resources/views/layouts/universal-frame.blade.php` remains `UNKNOWN` and `NON-CANONICAL-UNTIL-PROVEN`.
- `resources/views/testing-suite.blade.php` should not advertise `test-smart-tools` as a valid test surface.

## Top Findings

1. The repo still contains a large Blade surface: `166` `*.blade.php` files, including `32` components, `10` top-level `test*.blade.php` views, `10` layouts, `5` email views, `1` auth view, and `1` error view.
2. The four smart-tool components are not routed directly anywhere canonical. Their only confirmed Blade consumer is [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php), and that test page has no registered route in `php artisan route:list`.
3. All four smart-tool components still call `/api/universal-frame/*` non-v1 endpoints, while runtime routes are registered under `/api/v1/universal-frame/*` in [`routes/web.php`](../../routes/web.php#L75). That makes their runtime API reachability broken even if the Blade were mounted.
4. Two of the four smart-tool components also call endpoints that do not exist at all in route registration:
   - [`resources/views/components/export-component.blade.php`](../../resources/views/components/export-component.blade.php) calls `/api/universal-frame/user/role`
   - [`resources/views/components/analysis-drawer.blade.php`](../../resources/views/components/analysis-drawer.blade.php) calls `/api/universal-frame/export/analysis`
5. Duplication risk is real, not hypothetical. App/web surfaces already embed their own search/filter UX in canonical tenant routes, especially [`resources/views/layouts/app-layout.blade.php`](../../resources/views/layouts/app-layout.blade.php), [`resources/views/app/documents-content.blade.php`](../../resources/views/app/documents-content.blade.php), and [`resources/js/alpine-data-functions.js`](../../resources/js/alpine-data-functions.js). Keeping Blade smart-tools as a parallel UI system would preserve split ownership.
6. Several Blade routes remain reachable but are not architecturally clean canonical surfaces. There are routes pointing at missing views, routes pointing at web controllers that return JSON instead of HTML, and debug-only Blade pages under `/_debug`.

## Reachability Evidence

- Runtime route file is [`app/Providers/RouteServiceProvider.php`](../../app/Providers/RouteServiceProvider.php), which loads [`routes/web.php`](../../routes/web.php).
- Smart-tool API routes are registered only under `/api/v1/universal-frame/*` in [`routes/web.php`](../../routes/web.php#L75).
- `php artisan route:list --path=api/v1/universal-frame` shows 38 registered routes.
- `php artisan route:list --path=api/universal-frame` returns no matching routes.
- `php artisan route:list --path=test-smart-tools` returns no matching routes.
- `php artisan route:list --path=final-integration` shows:
  - `_debug/final-integration`
  - `api/v1/final-integration/*`
- Debug/test Blade routes are still registered in [`routes/web.php`](../../routes/web.php#L607), including `_debug/test-permissions`, `_debug/testing-suite`, `_debug/performance-optimization`, and `_debug/final-integration`.

## Duplication Evidence

- [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) composes a second UI system from:
  - [`resources/views/components/smart-search.blade.php`](../../resources/views/components/smart-search.blade.php)
  - [`resources/views/components/smart-filters.blade.php`](../../resources/views/components/smart-filters.blade.php)
  - [`resources/views/components/analysis-drawer.blade.php`](../../resources/views/components/analysis-drawer.blade.php)
  - [`resources/views/components/export-component.blade.php`](../../resources/views/components/export-component.blade.php)
- Canonical tenant app shell already owns navigation and feature composition in [`resources/views/layouts/app-layout.blade.php`](../../resources/views/layouts/app-layout.blade.php).
- Canonical tenant documents UX already owns search and filters in [`resources/views/app/documents-content.blade.php`](../../resources/views/app/documents-content.blade.php).
- Shared Alpine-side page behavior also exists in [`resources/js/alpine-data-functions.js`](../../resources/js/alpine-data-functions.js), including project/documents/dashboard state for search, filters, and export-like actions.
- Keeping smart-tools Blade components would preserve a second API-driven UX stack alongside app shell surfaces, with different state models and endpoint assumptions.

## Special Audit Targets

| Blade file | Direct references | Runtime reachability | Architectural role | Decision |
| --- | --- | --- | --- | --- |
| [`resources/views/components/analysis-drawer.blade.php`](../../resources/views/components/analysis-drawer.blade.php) | Included only by [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) | Not reachable through any registered page route; API calls use wrong prefix and one missing endpoint | legacy demo component | deprecate |
| [`resources/views/components/export-component.blade.php`](../../resources/views/components/export-component.blade.php) | Included only by [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) | Not reachable through any registered page route; calls wrong prefix plus missing `/user/role` endpoint | legacy demo component | deprecate |
| [`resources/views/components/smart-filters.blade.php`](../../resources/views/components/smart-filters.blade.php) | Included only by [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) | Not reachable through any registered page route; API calls use wrong prefix | legacy demo component | deprecate |
| [`resources/views/components/smart-search.blade.php`](../../resources/views/components/smart-search.blade.php) | Included only by [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) | Not reachable through any registered page route; API calls use wrong prefix | legacy demo component | deprecate |
| [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php) | Standalone test page; referenced textually by [`resources/views/testing-suite.blade.php`](../../resources/views/testing-suite.blade.php) | No route registration; test suite links to dead URL | demo/test harness, currently orphaned | archive |

## Blade Ownership Matrix

| Group | Example files | Render/owner evidence | Runtime status | Role | Decision |
| --- | --- | --- | --- | --- | --- |
| Auth | [`resources/views/auth/login.blade.php`](../../resources/views/auth/login.blade.php) | Local/testing `/login` route in [`routes/web.php`](../../routes/web.php#L50) | reachable in local/testing | auth | keep |
| Error | [`resources/views/errors/404.blade.php`](../../resources/views/errors/404.blade.php) | framework error rendering | conditionally reachable | error | keep |
| Email | [`resources/views/emails/layout.blade.php`](../../resources/views/emails/layout.blade.php), [`resources/views/emails/invitation.blade.php`](../../resources/views/emails/invitation.blade.php) | mail templates, no contradictory ownership found | indirectly reachable | email | keep |
| App shell/layout | [`resources/views/layouts/app-layout.blade.php`](../../resources/views/layouts/app-layout.blade.php), [`resources/views/layouts/app-base.blade.php`](../../resources/views/layouts/app-base.blade.php) | rendered by [`App\Http\Controllers\Web\AppController`](../../app/Http/Controllers/Web/AppController.php) | reachable via `/app/*` routes | shell/layout | keep |
| Admin shell/layout | [`resources/views/layouts/admin-layout.blade.php`](../../resources/views/layouts/admin-layout.blade.php), [`resources/views/layouts/admin-base.blade.php`](../../resources/views/layouts/admin-base.blade.php) | still referenced by controllers; admin routes also render direct admin pages | partially reachable, partially stale | shell/layout | keep |
| Universal-frame shell | [`resources/views/layouts/universal-frame.blade.php`](../../resources/views/layouts/universal-frame.blade.php) | extended by smart-tools demos and some admin pages | shell exists, consumers mostly non-canonical | shell/layout | UNKNOWN |
| Canonical tenant web views | [`resources/views/tasks/index.blade.php`](../../resources/views/tasks/index.blade.php), [`resources/views/documents/index.blade.php`](../../resources/views/documents/index.blade.php), [`resources/views/settings/index.blade.php`](../../resources/views/settings/index.blade.php), [`resources/views/profile/index.blade.php`](../../resources/views/profile/index.blade.php) | mapped from `/app/*` routes in [`routes/web.php`](../../routes/web.php#L383) | reachable | production UI | keep |
| Team/templates/project-detail web views | [`resources/views/team/index.blade.php`](../../resources/views/team/index.blade.php), [`resources/views/templates/index.blade.php`](../../resources/views/templates/index.blade.php), [`resources/views/projects/design-project.blade.php`](../../resources/views/projects/design-project.blade.php) | mapped from `/app/*` routes in [`routes/web.php`](../../routes/web.php#L429) | reachable | production UI, though legacy-styled | keep |
| Debug/test routed pages | [`resources/views/test-permissions.blade.php`](../../resources/views/test-permissions.blade.php), [`resources/views/testing-suite.blade.php`](../../resources/views/testing-suite.blade.php), [`resources/views/performance-optimization.blade.php`](../../resources/views/performance-optimization.blade.php), [`resources/views/final-integration.blade.php`](../../resources/views/final-integration.blade.php), [`resources/views/test-mobile-optimization.blade.php`](../../resources/views/test-mobile-optimization.blade.php) | `/_debug/*` routes in [`routes/web.php`](../../routes/web.php#L607) | reachable only behind debug gate | debug/demo | archive |
| Smart-tools demo components | [`resources/views/components/smart-search.blade.php`](../../resources/views/components/smart-search.blade.php), [`resources/views/components/smart-filters.blade.php`](../../resources/views/components/smart-filters.blade.php), [`resources/views/components/analysis-drawer.blade.php`](../../resources/views/components/analysis-drawer.blade.php), [`resources/views/components/export-component.blade.php`](../../resources/views/components/export-component.blade.php) | only included by dead test page | effectively unreachable | legacy/demo | deprecate |
| Generic reusable components | [`resources/views/components/header.blade.php`](../../resources/views/components/header.blade.php), [`resources/views/components/navigation.blade.php`](../../resources/views/components/navigation.blade.php), [`resources/views/components/breadcrumb.blade.php`](../../resources/views/components/breadcrumb.blade.php), [`resources/views/components/zena-logo.blade.php`](../../resources/views/components/zena-logo.blade.php) | included by active layouts/views | reachable through active shells | shared UI primitives | keep |
| Mobile/accessibility components | [`resources/views/components/mobile-navigation.blade.php`](../../resources/views/components/mobile-navigation.blade.php), [`resources/views/components/accessibility-dashboard.blade.php`](../../resources/views/components/accessibility-dashboard.blade.php) | included by universal-frame or test pages | mixed reachability, mostly shell/test dependent | support/debug | UNKNOWN |
| Vendor swagger | [`resources/views/vendor/l5-swagger/index.blade.php`](../../resources/views/vendor/l5-swagger/index.blade.php) | packaged vendor view; explicit route is commented out | not currently routed here | vendor/debug | keep |
| Broken route targets | `templates.create`, `templates.show`, `settings.general`, `settings.security`, `settings.notifications`, `admin.dashboard-enhanced`, `app.projects-enhanced`, `invitations.expired` | referenced in active `web.php` routes or controllers but missing on disk | reachable path would fail at runtime | broken/orphan | Change Proposal |
| Route/controller mismatches | [`resources/views/projects/*.blade.php`](../../resources/views/projects/index.blade.php), [`resources/views/tasks/*.blade.php`](../../resources/views/tasks/index.blade.php) | some `/app/*` routes map to controllers returning JSON or methods not present | partially reachable, partially broken contract | mixed production/legacy | UNKNOWN |

## Keep / Deprecate / Archive / Delete-Candidate / UNKNOWN

### Keep

- Auth/error/email views
- Active app/admin shell layouts
- Reusable components actively included by reachable layouts
- Canonical `/app/*` tenant pages that still render HTML

### Deprecate

- [`resources/views/components/smart-search.blade.php`](../../resources/views/components/smart-search.blade.php)
- [`resources/views/components/smart-filters.blade.php`](../../resources/views/components/smart-filters.blade.php)
- [`resources/views/components/analysis-drawer.blade.php`](../../resources/views/components/analysis-drawer.blade.php)
- [`resources/views/components/export-component.blade.php`](../../resources/views/components/export-component.blade.php)

### Archive

- [`resources/views/test-smart-tools.blade.php`](../../resources/views/test-smart-tools.blade.php)
- Debug/demo pages under `resources/views/test*.blade.php`
- [`resources/views/testing-suite.blade.php`](../../resources/views/testing-suite.blade.php)
- [`resources/views/performance-optimization.blade.php`](../../resources/views/performance-optimization.blade.php)
- [`resources/views/final-integration.blade.php`](../../resources/views/final-integration.blade.php)
- [`resources/views/demo.blade.php`](../../resources/views/demo.blade.php)

### Delete-candidate

- None in this round.
- Reason: several files are stale, but route/controller mismatches are widespread enough that hard deletion should wait until route cleanup is done in one ownership pass.

### UNKNOWN

- [`resources/views/layouts/universal-frame.blade.php`](../../resources/views/layouts/universal-frame.blade.php)
- Universal-frame support components such as KPI/alert/activity/mobile/accessibility blocks
- Some admin pages extending universal-frame
- Some project/task/detail views whose routes/controllers no longer cleanly align with HTML ownership

## Change Proposal

Do not patch in this round. Proposed next implementation round:

1. Freeze smart-tools Blade as non-canonical and remove it from any future ownership claims.
2. Delete or relocate dead demo entrypoints first: `test-smart-tools`, `testing-suite` links to dead URLs, and `_debug`-only launch/performance/test pages that duplicate API-only ownership.
3. Decide `layouts/universal-frame` ownership explicitly:
   - either keep only as debug/demo shell
   - or promote it to one canonical admin surface and migrate consumers onto `/api/v1/*` endpoints
4. Run a second pass for broken route targets and controller/view contract mismatches before any file deletion.

## Recommended Next Action

Open one focused follow-up PR that formally deprecates the smart-tools Blade stack by removing `test-smart-tools` and classifying the four smart-tool components plus `layouts/universal-frame` as non-canonical unless a single owning runtime route is nominated.

## Exact Files Touched

- [`docs/audits/2026-03-19-blade-ownership-reachability-audit.md`](../../docs/audits/2026-03-19-blade-ownership-reachability-audit.md)
