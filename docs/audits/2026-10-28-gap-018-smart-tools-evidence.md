# GAP-018 — Smart-tools Blade components call a non-existent API prefix: Gate-1 evidence

**Date:** 2026-10-28 (+07:00)

**Canonical base:** `0207062843857e7e974a77137261da2e29e6a549`

**Branch:** `docs/GAP-018-smart-tools-api-prefix`

**Scope:** re-verify the register row (OPEN, verified 2026-07). Read-only; no code change.

## Findings

1. The five files still exist (1,374 lines in total):
   `resources/views/components/smart-search.blade.php` (334), `smart-filters.blade.php` (419),
   `analysis-drawer.blade.php` (236), `export-component.blade.php` (335), `resources/views/test-smart-tools.blade.php` (50).
   Each already carries an "archived / non-canonical, do not revive" header comment.
2. They call `/api/universal-frame/*` (search, filters, analysis, export, user/role). `php artisan route:list` has **0**
   routes under that prefix; the real family is `/api/v1/universal-frame/*` (gated by `rbac:admin`, see GAP-019), and some
   called paths (`/search*`, `/export*`, `/user/role`) have no equivalent there either.
3. **Nothing renders them**: no `@include`, `<x-…>`, `view()` or route references any of the five (including
   `layouts/universal-frame.blade.php`, whose includes are other components); `test-smart-tools` has no route.
4. One coupling: `App\Services\LaunchChecklistService::checkSmartTools()` (line 442) reports the launch-checklist item
   `smart_tools` as `file_exists('views/components/smart-search.blade.php')`. That service backs
   `/api/v1/final-integration/*` (legacy, `rbac:admin`). Deleting `smart-search` would flip that item to false.
5. No test references the five files; `scripts/phase2_cleanup_files.php` lists an unrelated `public/test-smart-search.html`.

## Conclusion

Confirmed: dead UI code calling an API that does not exist. No user can reach it; the risk is confusion for whoever edits
it next (the register's original assessment).
