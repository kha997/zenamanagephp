# GAP-058 — Sidebar badge API: Gate-1 evidence

**Date:** 2026-09-29 (+07:00)

**Canonical base:** `93fd0d7ab58b8e281affbfab9aef0fc3c6e09389`

**Branch:** `docs/GAP-058-badge-api-500`

**Scope:** Read-only investigation and Gate-1 documentation. No code, route,
view, config or deployment change.

## Registered claim (OWN-2026-014)

"`/api/badges/*` always returns 500 because `BadgeController` lacks
`use Illuminate\Http\Request` and `BadgeService` lacks `use App\Models\User`."
Re-verified: true. The investigation below shows the 500 is the smallest part
of the problem.

## Layer 1 — the API fails before any action runs

- `app/Http/Controllers/Api/BadgeController.php:5-6` imports only
  `Controller` and `JsonResponse`; every action (`:13, 29, 48, 64, 83, 96, 109,
  127`) type-hints `Request`, which resolves to
  `App\Http\Controllers\Api\Request` → `ReflectionException` → HTTP 500 (GAP-055
  probe, surface C).
- `app/Services/BadgeService.php:5-7` imports only `Auth`, `Cache`, `Http`;
  `?User`/`User` type-hints resolve to `App\Services\User` → `TypeError` for any
  caller passing an `App\Models\User` (GAP-055 implementation hit this).

Routes (`routes/api.php:978-987`, `auth:sanctum` + generic RBAC): `GET
/api/badges/{itemId}`, `POST /counts`, `PUT /{itemId}`, `POST /update`,
`DELETE /{itemId}/cache`, `DELETE /cache`, `POST /config`, `POST
/batch-update`.

## Layer 2 — even with the imports fixed, every badge is 0

- `BadgeService::getBadgeCount()` (`:36-50`) caches and returns the literal
  `0`; it never calls `fetchBadgeCount()`.
- `fetchBadgeCount()` (`:74-101`) — the only code that would compute a number —
  has **no caller**, targets `/api/metrics/*` endpoints that **do not exist**
  (runtime route table, 1171 routes: zero `api/metrics*` routes), and would send
  `Authorization: Bearer user_token_<id>`, a placeholder
  (`getUserToken()`, `:115-120`).
- The 15 endpoint URLs also appear as `show_badge_from` in
  `app/Services/PresetService.php:159,189,200`.

## Layer 3 — nothing in the live UI calls the API

- The only caller is the JavaScript in `resources/views/components/sidebar.blade.php:198-230`
  (`fetch('/api/badges/' + itemId)`), rendered by
  `app/View/Components/Sidebar.php:35`.
- `git grep` over `resources`, `app`, `routes`, `config` for `<x-sidebar`,
  `components.sidebar`, `Sidebar::class`, `Blade::component`: **no usage** of
  that component in any layout or view.
- The canonical layout (`resources/views/layouts/operator.blade.php`, "Operator
  First" design) has its own `.operator-sidebar` and no badge/count indicator.

**User impact today: none observable** — no rendered page calls the badge API,
so no user sees the 500.

## Surrounding system (context, not proposed scope)

The badge API belongs to an older configurable-sidebar system that the Operator
layout does not use: `sidebar-builder` admin view, `SidebarConfigRequest`
(`show_badge_from` fields), `UserSidebarPreference::show_badges` and
`POST /api/user-preferences/toggle-badges`, `PresetService` presets, and a
legacy `GET /sidebar/badges` route in `routes/legacy/api_v1.php:76`
(`Api\App\SidebarController::getBadges`). Deciding the fate of that whole
system is larger than GAP-058.

## Risk if left as is

Low runtime risk (no caller). Maintenance risk: a live, authenticated,
8-route API surface that always errors, a service returning fabricated zeros,
and placeholder bearer tokens — misleading for anyone who later wires a sidebar
to it. Since GAP-055 the badge cache clear is targeted, so fixing the imports
alone no longer opens a global cache flush.

## The decision this needs (business, not technical)

Whether menu count badges (e.g. "Approvals (3)", "RFIs (2)") are wanted in the
current Operator navigation:

- **Not wanted (now):** retire the dead badge API surface (routes, controller,
  service, unused component) — a technical cleanup.
- **Wanted:** a new feature, not a bug fix — it needs product definitions
  (which menu items, what each count means per role, e.g. "approvals waiting for
  me" vs "all pending in the tenant") before any design; the current code is
  not a usable base (no metrics endpoints, fake token, hard-coded 0).

## Out of scope for this Gate 1

Any fix; the wider legacy sidebar system; the Operator navigation design.
