# GAP-019 — Universal-frame shell with no route owner: Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `66dd66457ee745ddc50c52ecfaf522bdaa77a251`

**Branch:** `docs/GAP-019-universal-frame-shell`

**Scope:** Read-only investigation. No code change.

## Findings

1. `resources/views/layouts/universal-frame.blade.php` (338 lines) is extended only by
   `resources/views/test-universal-frame.blade.php`, whose own header says no registered route renders it. No route,
   controller or other view uses either file.
2. The layout includes 8 components that nothing else includes: `activity-panel`, `alert-bar`, `kpi-strip`,
   `mobile-drawer`, `mobile-fab`, `mobile-navigation`, `universal-header`, `universal-navigation`
   (`resources/views/components/*.blade.php`).
3. The layout loads Alpine from `cdn.jsdelivr.net/npm/alpinejs@3.x.x` (unpinned) and Font Awesome from cdnjs; the live
   `layouts.operator` shell does not depend on it.
4. Coupling: `App\Services\LaunchChecklistService::runSystemIntegrationChecks()` reports
   `universal_page_frame` = `file_exists(views/layouts/universal-frame.blade.php)`, `mobile_optimization` =
   `file_exists(views/components/mobile-fab.blade.php)` and `smart_tools` =
   `file_exists(views/components/smart-search.blade.php)`. These are served by `/api/v1/final-integration/*`
   (`auth`, `tenant.isolation`, `rbac:admin`) through `FinalIntegrationController`. Since GAP-018 deleted
   `smart-search`, `smart_tools` already reports `false` (disclosed in GAP-018 Gate 3). A file existing is not a
   system-integration signal.
5. Out of scope: the `/api/v1/universal-frame/*` API routes (`routes/web.php:88`) are a separate, hardened and tested
   surface (`V1LegacyRouteHardeningContractTest`, `TenantIsolationV1ContractTest`).
6. Register row GAP-019 records the 2026-08 decision "keep as debug/demo shell, do not revive unless the owner asks".
