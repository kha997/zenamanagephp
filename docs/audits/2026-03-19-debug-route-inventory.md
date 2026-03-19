# Debug Route Inventory Snapshot (2026-03-19)

Scope: docs-only runtime inventory for active `/_debug/*` routes mounted in the current application runtime on 2026-03-19.

Runtime source of truth: `php artisan route:list --json --path=_debug`

Boundary:
- Page-tree docs are not a full runtime manifest. They are high-level structural documentation only.
- This inventory document is the runtime snapshot for the currently mounted `/_debug/*` surface.
- This document does not change runtime behavior, route definitions, or test behavior.

## Top findings

1. Current runtime mounts 21 active `/_debug/*` routes.
2. The mounted surface is concentrated in one `_debug` group in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L606), with 20 closure-owned routes and 1 controller-owned route.
3. The active surface is dominated by debug/demo test pages and auth helpers, not canonical product navigation.
4. Several mounted debug pages still present product-adjacent dashboards or launch/performance flows, but their runtime ownership is explicitly debug-only because they are only mounted under `/_debug/*` behind `DebugGateMiddleware`.
5. No active `/_debug/*` route in this snapshot required a forced `UNKNOWN` purpose classification from the route body itself; ambiguous broader page ownership remains a separate docs concern, not a route-manifest gap here.

## Inventory summary

- Total active routes: 21
- Closure-owned routes: 20
- Controller-owned routes: 1
- By classification:
- `debug test`: 9
- `debug tooling`: 3
- `debug auth`: 6
- `debug diagnostics`: 3
- `UNKNOWN`: 0

## Route inventory

| Method | Path | Route name | Owner | Purpose / intent inferred from code | Classification | Evidence |
| --- | --- | --- | --- | --- | --- | --- |
| `GET|HEAD` | `/_debug/admin-dashboard-test` | `debug.admin-dashboard-test` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L761) | Simple HTML test page for admin dashboard-facing debug checks. | `debug test` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L761) |
| `GET|HEAD` | `/_debug/dashboard-data` | none | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L608) | Returns mock dashboard stats, recent activity, and quick-action JSON for dashboard debugging. | `debug diagnostics` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L608) |
| `GET|HEAD` | `/_debug/final-integration` | `debug.final-integration` | Closure rendering [`resources/views/final-integration.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/final-integration.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L773) | Demo/debug launch-readiness dashboard for final integration checks. | `debug tooling` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L773) |
| `GET|HEAD` | `/_debug/performance-optimization` | `debug.performance-optimization` | Closure rendering [`resources/views/performance-optimization.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/performance-optimization.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L769) | Demo/debug performance dashboard for running performance analysis and viewing optimization metrics. | `debug diagnostics` | [`resources/views/performance-optimization.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/performance-optimization.blade.php#L1) |
| `GET|HEAD` | `/_debug/tenant-dashboard-test` | `debug.tenant-dashboard-test` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L777) | Simple HTML test page for tenant dashboard-facing debug checks. | `debug test` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L777) |
| `GET|HEAD` | `/_debug/test` | `debug.test` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L721) | Minimal server-reachability smoke page. | `debug test` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L721) |
| `GET|HEAD` | `/_debug/test-accessibility` | `debug.test-accessibility` | Closure rendering [`resources/views/test-accessibility.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-accessibility.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L733) | Accessibility demo/test page covering skip links, focus styles, contrast, and reduced-motion behavior. | `debug test` | [`resources/views/test-accessibility.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-accessibility.blade.php#L1) |
| `GET|HEAD` | `/_debug/test-api-admin-stats` | none | [`App\Http\Controllers\Api\Admin\DashboardController@getStats`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/Admin/DashboardController.php#L14) via [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L653) | Returns mock admin dashboard statistics JSON without the normal authenticated app flow. | `debug diagnostics` | [`app/Http/Controllers/Api/Admin/DashboardController.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/app/Http/Controllers/Api/Admin/DashboardController.php#L14) |
| `GET|HEAD` | `/_debug/test-auth` | `debug.test-auth` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L741) | Auth middleware test page verifying a debug route protected by `auth`. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L741) |
| `GET|HEAD` | `/_debug/test-auth-direct` | `debug.test-auth-direct` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L745) | Auth middleware test page for a direct auth path variant. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L745) |
| `GET|HEAD` | `/_debug/test-bypass` | `debug.test-bypass` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L753) | Middleware-bypass experiment page used to compare behavior against more protected debug routes. | `debug tooling` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L753) |
| `POST` | `/_debug/test-login-simple` | none | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L656) | Creates a session-backed demo login from hard-coded credentials without touching the database. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L656) |
| `GET|HEAD` | `/_debug/test-login/{email}` | none | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L782) | Session-based demo login shortcut by email that redirects to `/admin`. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L782) |
| `GET|HEAD` | `/_debug/test-minimal` | `debug.test-minimal` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L749) | Minimal-middleware comparison page for route-behavior debugging. | `debug test` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L749) |
| `GET|HEAD` | `/_debug/test-mobile-optimization` | `debug.test-mobile-optimization` | Closure rendering [`resources/views/test-mobile-optimization.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-mobile-optimization.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L725) | Mobile UX demo/test page for FAB, mobile navigation, responsive layout, and touch interaction behavior. | `debug test` | [`resources/views/test-mobile-optimization.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-mobile-optimization.blade.php#L1) |
| `GET|HEAD` | `/_debug/test-mobile-simple` | `debug.test-mobile-simple` | Closure rendering [`resources/views/test-mobile-simple.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-mobile-simple.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L729) | Simpler mobile optimization demo/test page with basic FAB and mobile navigation patterns. | `debug test` | [`resources/views/test-mobile-simple.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-mobile-simple.blade.php#L1) |
| `GET|HEAD` | `/_debug/test-permissions` | `debug.test-permissions` | Closure rendering [`resources/views/test-permissions.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-permissions.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L648) | Permission-inspection page showing auth state, roles, tenant data, and admin/app access checks. | `debug auth` | [`resources/views/test-permissions.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/test-permissions.blade.php#L1) |
| `GET|HEAD` | `/_debug/test-session-auth` | none | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L694) | Simulates session-auth middleware logic and reports whether a debug login session exists. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L694) |
| `GET|HEAD` | `/_debug/test-simple` | `debug.test-simple` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L737) | Plain HTML smoke page with minimal content for quick route verification. | `debug test` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L737) |
| `GET|HEAD` | `/_debug/test-web-guard` | `debug.test-web-guard` | Closure in [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L757) | Auth-check page specifically exercising the `web` guard path. | `debug auth` | [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L757) |
| `GET|HEAD` | `/_debug/testing-suite` | `debug.testing-suite` | Closure rendering [`resources/views/testing-suite.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/testing-suite.blade.php#L1) from [`routes/web.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/routes/web.php#L765) | Consolidated demo/testing dashboard for running and reviewing test flows. | `debug tooling` | [`resources/views/testing-suite.blade.php`](/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/resources/views/testing-suite.blade.php#L1) |

## UNKNOWN

- Route-level `UNKNOWN` entries in this snapshot: none.
- Broader architectural ownership of some underlying debug/demo Blade assets may still be debated elsewhere, but the mounted route intent for all 21 active entries is inferable from the current route body, view title, or controller method.

## Risk assessment

- Low runtime risk for this round because this is docs-only and does not alter runtime, routing, middleware, or tests.
- Moderate documentation drift risk remains because the inventory is a point-in-time snapshot. Any future add/remove/rename under `/_debug/*` can stale this doc unless regenerated or updated alongside route changes.
- Moderate product-surface confusion risk remains because several debug pages still look like admin, tenant, performance, or launch dashboards despite being mounted only under `/_debug/*`.

## Recommended next action

Add a single docs invariant test or lightweight generator check that compares the active `/_debug/*` runtime manifest against this inventory snapshot so future route drift is surfaced immediately.
