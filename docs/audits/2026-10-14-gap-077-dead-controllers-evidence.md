# GAP-077 — Evidence: unreachable controllers and requests

Base: `9d794197d7a97bcfa2c79852053979ee995f5dd6` (main after GAP-076).

Found during GAP-073 (their PHPStan findings were fixed in place there).

| File | Lines | Why it is dead |
|---|---|---|
| `app/Http/Controllers/InteractionLogController.php` | 410 | No route uses it. All `interaction-logs` routes (`routes/api.php`, `src/InteractionLogs/routes/api.php`) use `Src\InteractionLogs\Controllers\InteractionLogController`. It also references classes that do not exist (`App\Http\Controllers\InteractionLog`, `...\InteractionLogService` — PHPStan baseline). |
| `app/Http/Requests/StoreInteractionLogRequest.php` | 147 | Only used by the dead controller above; the live controller uses `Src\InteractionLogs\Requests\StoreInteractionLogRequest`. |
| `app/Http/Requests/UpdateInteractionLogRequest.php` | 169 | Same, live one is `Src\InteractionLogs\Requests\UpdateInteractionLogRequest`. |
| `app/Http/Controllers/Admin/BasicSidebarController.php` | 95 | No route uses it (`php artisan route:list` shows only `Api\SidebarConfigController` for sidebar). Marked "No auth check for testing". |
| `resources/views/admin/simple-sidebar-builder.blade.php` | — | Only rendered by `BasicSidebarController`. |

Checks: `php artisan route:list` (no route to either controller); repo-wide grep
(excluding vendor, PHPStan cache) finds no other reference except historical
one-off scripts (`scripts/*fix_namespaces.php`, `scripts/restore_from_src.php`)
and `GIT_DIFF_SUMMARY.txt`. `phpstan-baseline.neon` has 51 lines for these files.

Also unrouted but **not** in this Work ID (referenced by an audit test
comment): `Admin/SimpleSidebarBuilderController`, `Admin/SidebarBuilderController`.
