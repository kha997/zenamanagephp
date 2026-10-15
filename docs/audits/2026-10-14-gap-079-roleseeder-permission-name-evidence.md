# GAP-079 — Evidence: RoleSeeder leaves two permissions with a NULL name (AUD-28)

Base: `c7775a13ebabd22674815cc7fa4c9450fa020847` (main after GAP-078).

## Cause

`database/seeders/DatabaseSeeder.php` runs `RoleSeeder` before
`PermissionSeeder`. `RoleSeeder::run()` creates five permissions with
`Permission::firstOrCreate(['code' => ...], [module, action, description,
is_active])` and never sets `name` (`permissions.name` is nullable).
`PermissionSeeder` (fixed for AUD-22) passes `name => code`, but its
`firstOrCreate` is keyed by `code`, so for rows that already exist it changes
nothing. Three of the five codes are later given a name by another seeder;
`project.read` and `user.manage` are not.

## Reproduction (SQLite, full `db:seed`, throw-away probe test, not committed)

```
NULLNAME=["project.read","user.manage"]
PM_has_project.read_by_code=true by_name=false
```

The `Project Manager` role holds `project.read` (assigned by
`PermissionSeeder`), but a lookup by `name` does not find it.

## Why it matters

- `App\Models\User::hasPermission()` / `hasAnyPermission()` match
  `permissions.name`, so they return false for `project.read` even when the
  user's role holds it. Code checking by `code` (RBAC middleware,
  `Src\RBAC`) is not affected.
- GAP-044 hit the same row in tests: a lookup by `name = 'project.read'`
  missed the NULL-name row and the insert then broke the unique `code`.
- `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php` allowlists
  these two codes as a known exception (AUD-28) instead of asserting no
  NULL names.
- Databases that were already seeded keep the NULL rows; fixing the seeder
  alone does not repair them.

## Related facts

- `user.manage`: no RBAC check uses it (its last reference was removed with
  `SimpleSidebarBuilderController` in GAP-078).
- `project.read`: used by sidebar presets (`required_permissions`),
  `PermissionService` role maps, and `RolePermissionSeeder`.
