# GAP-020 — Orphan admin views `admin/users/index` and `admin/tenants/index`: Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `8a676dcc9b8dc0f0a5d593afc147b64c70e5d962` (GAP-018 released)

**Branch:** `docs/GAP-020-orphan-admin-views`

**Scope:** re-verify the register row (OPEN, verified). Read-only; no code change.

## Findings

1. Both files still exist: `resources/views/admin/users/index.blade.php` (515 lines) and
   `resources/views/admin/tenants/index.blade.php` (527 lines). Each starts with an "ORPHAN ADMIN ARTIFACT /
   NON-CANONICAL" comment and extends `layouts.universal-frame`.
2. The live routes render other views: `GET /admin/users` (`admin-users`) returns `view('admin.users')` and
   `GET /admin/tenants` (`admin-tenants`) returns `view('admin.tenants')` (`routes/web.php:240,243`), i.e.
   `resources/views/admin/users.blade.php` and `admin/tenants.blade.php`.
3. Nothing references `admin.users.index` / `admin.tenants.index` (or their paths) in `app`, `routes`, `src`,
   `resources` or `tests`. Each file is the only file in its folder.
4. Risk is maintenance confusion: the names follow Laravel's `<resource>/index` convention, so the next editor may
   change the orphan instead of the live page (the register's assessment).

## Conclusion

Confirmed: 1,042 lines of dead Blade that no route renders.
