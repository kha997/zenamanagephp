# Universal Frame Ownership Resolution Audit

Date: 2026-03-19
Repo: `zenamanage-golden`
Scope: docs-only audit for `resources/views/layouts/universal-frame.blade.php` and dependent debug/demo/admin Blade consumers
Constraint: no route or runtime behavior changes in this round

## Top Findings

1. `resources/views/layouts/universal-frame.blade.php` still exists as a fully composed shell, but no registered runtime route was found that directly renders it as a canonical production surface.
2. Only four direct `@extends('layouts.universal-frame')` consumers remain:
   - `resources/views/admin/users/index.blade.php`
   - `resources/views/admin/tenants/index.blade.php`
   - `resources/views/test-universal-frame.blade.php`
   - `resources/views/test-smart-tools.blade.php`
3. The two production-looking admin consumers are not the views mounted by current admin routes. Runtime routes `GET /admin/users` and `GET /admin/tenants` render `admin.users` and `admin.tenants`, not `admin.users.index` or `admin.tenants.index`.
4. `test-universal-frame` and `test-smart-tools` have no registered runtime routes. They are debug/demo or archive artifacts, not live owners.
5. The shell includes a broad support surface:
   - `components.universal-header`
   - `components.universal-navigation`
   - `components.kpi-strip`
   - `components.alert-bar`
   - `components.activity-panel`
   - `components.mobile-fab`
   - `components.mobile-drawer`
   - `components.mobile-navigation`
6. That support surface is tightly coupled to admin-ish UX assumptions and to `/api/v1/universal-frame/*` data families, but the shell itself is not the canonical admin layout today. Current admin runtime ownership is split across `resources/views/admin/*.blade.php` direct pages and `resources/views/layouts/admin-layout.blade.php`.

## Docs Signaling Applied In This Round

- `resources/views/admin/users/index.blade.php` now carries an explicit Blade comment marking it as an orphan admin artifact and non-canonical consumer of `layouts/universal-frame`.
- `resources/views/admin/tenants/index.blade.php` now carries the same orphan admin / non-canonical ownership marker.
- `resources/views/test-universal-frame.blade.php` now carries an explicit debug/demo-only non-canonical marker aligned with the existing archived signaling on `resources/views/test-smart-tools.blade.php`.
- No route, controller, API, or runtime rendering behavior was changed in this round.

## Ownership Decision

Recommended ownership decision: keep `layouts/universal-frame` only as a debug/demo shell.

Reasoning:

- There is no current production route owner that renders it.
- The only direct consumers are two unrouted test/archive pages plus two admin-looking orphan views that are bypassed by runtime.
- Promoting it to canonical admin shell in this round would be an architectural claim not supported by current route ownership.
- The repo already has separate admin runtime surfaces in `resources/views/admin/*.blade.php` and `resources/views/layouts/admin-layout.blade.php`; promoting `universal-frame` now would increase shell ambiguity instead of reducing it.

## Consumer Inventory

| Consumer | Type | Direct dependency | Runtime route owner | Classification | Production relevance | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `resources/views/admin/users/index.blade.php` | Blade view | `@extends('layouts.universal-frame')` | none found; `GET /admin/users` renders `view('admin.users')` in `routes/web.php` | orphan admin | low | Looks like an abandoned admin alternative, not active runtime |
| `resources/views/admin/tenants/index.blade.php` | Blade view | `@extends('layouts.universal-frame')` | none found; `GET /admin/tenants` renders `view('admin.tenants')` in `routes/web.php` | orphan admin | low | Same pattern as users index |
| `resources/views/test-universal-frame.blade.php` | Blade view | `@extends('layouts.universal-frame')` | none; `php artisan route:list --path=test-universal-frame` returns no match | demo | none | Demonstration page only |
| `resources/views/test-smart-tools.blade.php` | Blade view | `@extends('layouts.universal-frame')` | none; `php artisan route:list --path=test-smart-tools` returns no match | legacy archive | none | Archived marker for deprecated smart-tools stack |

## Included Support Components

These are included by `resources/views/layouts/universal-frame.blade.php` and therefore inherit its non-canonical ownership status unless a later round assigns a canonical owner:

| Included file | Runtime owner if any | Classification | Production relevance | Notes |
| --- | --- | --- | --- | --- |
| `resources/views/components/universal-header.blade.php` | only via `layouts/universal-frame` | shell support | low | Uses auth/user menu assumptions |
| `resources/views/components/universal-navigation.blade.php` | only via `layouts/universal-frame` | shell support | low | Pulls in admin or tenant nav by role |
| `resources/views/components/kpi-strip.blade.php` | only via `layouts/universal-frame` | shell support | low | Coupled to universal-frame KPI state |
| `resources/views/components/alert-bar.blade.php` | only via `layouts/universal-frame` | shell support | low | Coupled to universal-frame alert state |
| `resources/views/components/activity-panel.blade.php` | only via `layouts/universal-frame` | shell support | low | Links to `/admin/activities` but not itself canonical |
| `resources/views/components/mobile-fab.blade.php` | only via `layouts/universal-frame` | debug/demo shell support | none to low | Quick-action demo behavior |
| `resources/views/components/mobile-drawer.blade.php` | only via `layouts/universal-frame` | debug/demo shell support | none to low | Drawer demo behavior |
| `resources/views/components/mobile-navigation.blade.php` | only via `layouts/universal-frame` | debug/demo shell support | none to low | Mobile nav demo behavior |

## Route Ownership Evidence

- Runtime route family for universal-frame APIs exists only at `/api/v1/universal-frame/*` in `routes/web.php`.
- No runtime route exists for `/test-universal-frame`.
- No runtime route exists for `/test-smart-tools`.
- `php artisan route:list --path=admin/users` shows `GET|HEAD admin/users` only.
- `php artisan route:list --path=admin/tenants` shows `GET|HEAD admin/tenants` only.
- In `routes/web.php`, those admin routes render:
  - `view('admin.users')`
  - `view('admin.tenants')`
- Therefore `resources/views/admin/users/index.blade.php` and `resources/views/admin/tenants/index.blade.php` are not current route-owned runtime views.

## Risk Assessment

- Primary risk: false canonicalization. If `universal-frame` were treated as canonical admin shell now, future cleanup could accidentally preserve an orphan shell and deepen layout split-brain.
- Secondary risk: stale admin artifacts may mislead future contributors because `admin/*/index.blade.php` looks production-ready while being unrouted.
- Integration risk: the shell support components imply live KPI/alert/activity/mobile composition, but there is no single page route proving that composition works as a maintained product surface.
- Documentation risk: without an explicit ownership decision, future docs may continue to cite `universal-frame` as if it is an active admin foundation.

## UNKNOWN

- Whether any controller, test harness, or unpublished branch outside current route registration still relies on `resources/views/admin/users/index.blade.php` or `resources/views/admin/tenants/index.blade.php`.
- Whether `resources/views/components/universal-header.blade.php`, `resources/views/components/universal-navigation.blade.php`, and the KPI/alert/activity/mobile support components should be archived outright or retained as debug/demo references.
- Whether a future admin consolidation should target:
  - `resources/views/layouts/admin-layout.blade.php`
  - direct `resources/views/admin/*.blade.php` pages
  - or a redesigned shell replacing both
- Whether the broad `/api/v1/universal-frame/*` route family should ultimately belong to a canonical admin web shell at all, or remain API-only infrastructure.

## Change Proposal For Later Round

No code changes in this round. If a later round is approved, make one focused ownership PR with this sequence:

1. Formally mark `resources/views/admin/users/index.blade.php` and `resources/views/admin/tenants/index.blade.php` as orphan admin artifacts unless a route migration is explicitly approved.
2. Add explicit ownership comments to `resources/views/layouts/universal-frame.blade.php` and its included support components stating that they are debug/demo shell only.
3. Remove or relocate dead debug/demo entrypoints that still imply runtime ownership:
   - `resources/views/test-universal-frame.blade.php`
   - `resources/views/test-smart-tools.blade.php`
4. In a separate runtime round, choose one canonical admin shell only. Current evidence does not justify choosing `universal-frame`.

## Exact Files Touched

- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/tenants/index.blade.php`
- `resources/views/test-universal-frame.blade.php`
- `docs/audits/2026-03-19-universal-frame-ownership-audit.md`
