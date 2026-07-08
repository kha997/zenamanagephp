# Canonical RBAC Seeding Fix Lock

Date: 2026-04-15
Type: bounded implementation lock
Status: accepted

## Scope

Fix the local RBAC bootstrap bug that was blocking canonical `/api/zena/*` pilot authorization by repairing `ZenaRbacSeeder` and its input normalization only.

## Root Cause

`ZenaRbacSeeder` was still seeding legacy permission rows with `name` plus optional legacy metadata, but the live `permissions` model/table contract still expects canonical `code`, `module`, and `action`.

That mismatch caused:

- `App\Models\Permission::generateCode()` to receive `null` for `$action`
- `firstOrCreate()` to fail before any permissions were attached to the local bootstrap roles
- `super_admin` authorization bootstrap to remain effectively empty on local pilot environments

After the permission crash was removed, the same seeder also exposed two more pieces of legacy drift inside the same bootstrap path:

- role payloads still carried `display_name`, which the current `roles` table does not have
- legacy sample data writes were still bundled into the same seeder and were targeting stale non-RBAC schema such as `project_user_roles.role_on_project`

## Lock Decision

The bounded fix is:

1. Normalize legacy permission definitions inside `ZenaRbacSeeder` into canonical seeded rows with:
   - `code`
   - `name`
   - `module`
   - `action`
   - `description`
2. Treat the existing permission name/code string such as `material.request` as the canonical identifier for this seeder and derive `module/action` from it.
3. Key permission `firstOrCreate()` on `code`, not on the incomplete legacy payload.
4. Normalize legacy role definitions into the live `roles` schema contract and canonical role names such as `super_admin`.
5. Skip legacy sample data by default unless `ZENA_RBAC_SEED_SAMPLE_DATA=true` is explicitly set, so stale demo fixtures cannot block RBAC bootstrap.

## Verify Evidence

- `php artisan db:seed --class=ZenaRbacSeeder`
  - passes
- `XDG_CONFIG_HOME=/tmp php artisan tinker --execute="echo 'ALL_PERMISSION_COUNT=' . App\\Models\\Permission::count() . PHP_EOL;"`
  - `ALL_PERMISSION_COUNT=50`
- `XDG_CONFIG_HOME=/tmp php artisan tinker --execute="\$r=App\\Models\\Role::where('name','super_admin')->first(); echo json_encode(\$r?->permissions()->pluck('name')->values()->all()) . PHP_EOL;"`
  - returns the full seeded permission list for `super_admin`
- `php ./vendor/bin/phpunit tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
  - passes

## Guardrails

- Do not use this round to widen business semantics or patch Material Request / Receipt / Contract behavior.
- Do not silently allow null `action` in `App\Models\Permission`; the correct boundary is seeded input normalization.
- Do not re-couple RBAC bootstrap success to legacy sample data drift.
