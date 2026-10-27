# GAP-078 — Evidence: unrouted sidebar builder controllers and their views

Base: `043e182176dbbb60e536a818b4ccafa6d967688d` (main after GAP-077).

| File | Lines | Why it is dead |
|---|---|---|
| `app/Http/Controllers/Admin/SimpleSidebarBuilderController.php` | 195 | No route uses it (`php artisan route:list`). |
| `app/Http/Controllers/Admin/SidebarBuilderController.php` | 311 | No route uses it. |
| `resources/views/admin/sidebar-builder.blade.php` | 608 | Only rendered by the two controllers above. |
| `resources/views/admin/sidebar-builder-edit.blade.php` | 635 | Same. |
| `resources/views/admin/sidebar-preview.blade.php` | 244 | Same. |
| `resources/views/admin/sidebar-builder-content.blade.php` | 34 | Not rendered or included anywhere. |

The live `/admin/sidebar-builder` route (`routes/web.php`) is a closure that
returns a placeholder string; it does not use these controllers or views. The
API `Api\SidebarConfigController` is the real sidebar management backend and
is untouched.

Other references:

- `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php` — a comment
  cites `SimpleSidebarBuilderController.php:126` as the only place the string
  `'user.manage'` appears; the test logic does not depend on the file.
- `app/Services/PermissionService.php` maps route **names**
  `admin.sidebar-builder[.edit|.preview]` to permissions (not class or view
  references).
- `phpstan-baseline.neon`: 16 lines for the two controllers.
