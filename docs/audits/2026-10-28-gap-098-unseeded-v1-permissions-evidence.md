# GAP-098 — Unseeded RBAC permission codes on `/api/v1/*`: Gate-1 evidence

**Date:** 2026-10-28 (+07:00)

**Canonical base:** `c1420a3e9b41c94c55fd57465db3ede105dfb660` (GAP-021 released)

**Branch:** `docs/GAP-098-unseeded-v1-permissions`

**Scope:** read-only. No code change.

## Method

Every `rbac:<code>` middleware in `php artisan route:list --json` was compared with every quoted permission code in
`database/seeders/*.php`. `RoleBasedAccessControlMiddleware` lets super admins through (`checkAccess()`); for everyone
else `isPermission()` first requires a `permissions` row with that code or name, then `User::hasPermission()` requires
the exact name on one of the user's roles. A code no seeder creates therefore denies every non-super-admin. GAP-021
reproduced this for `task.edit` (403 for a `task.update` holder) and fixed it.

## Result: 32 codes, 69 routes, all under `/api/v1`

| Family | Unseeded codes (routes) | Seeded codes in the same module |
|---|---|---|
| Baselines (`BaselineController`) | `baseline.view` (7), `baseline.create` (2), `baseline.edit` (3), `baseline.delete` (1) | none |
| Change requests v1 (`Src\ChangeRequest`) | `change_request.view` (3), `change_request.edit` (1), `change_request.submit` (1), `change_request.delete` (1), `change_request.stats` (1) | `change_request.read`, `.create`, `.update`, `.approve`, `.reject` |
| Compensation (`CompensationController`) | `compensation.view` (3), `.edit`, `.apply`, `.preview`, `.stats`, `.sync` (1 each) | none |
| RBAC admin (`/api/v1/rbac/*`) | `role.view` (3), `role.create` (1), `role.edit` (2), `role.delete` (1), `role.assign` (7), `permission.view` (4), `permission.create`, `.edit`, `.delete`, `.export` (1 each), `permission.import` (2), `user.view` (3), `audit.view` (1) | `role.manage`; `user.read`, `user.manage`, `user.manage_roles` |
| Projects / components v1 | `project.edit` (2: PUT/PATCH `/api/v1/projects/{project}`), `component.edit` (1: PATCH `/api/v1/components/{id}`) | `project.update`, `component.update` |
| Work templates v1 | `template.create` (4), `template.edit` (6) | `template.view`, `.apply`, `.edit_draft`, `.publish`, `.delete` |

Total: 32 codes, 69 routes. No `/api/zena/*` or `/operator/*` route is affected (all canonical families use seeded
codes).

## Who calls these routes

- No mounted page: the views that call `/api/v1/rbac/*`, `/api/v1/change-requests`, `/api/v1/projects/{id}` and
  `/api/v1/work-templates` (`resources/views/rbac/roles|permissions|user-roles.blade.php`,
  `change-requests/form.blade.php`, `projects/form.blade.php`) are not rendered by any route or controller.
- Canonical equivalents exist for change requests (`/api/zena/change-requests`, 12 routes), projects (68) and templates
  (15); none for baselines, compensation, or RBAC administration.
- External API clients: UNKNOWN.

## Related, not in this count

`rbac:admin` (a role name, not a permission) gates the universal-frame, performance and final-integration groups in
`routes/web.php:90,156,167`; `admin` is not in the middleware's role list, so the same deny-unless-super-admin outcome
applies. Those surfaces are already tracked as dead/legacy (GAP-018, GAP-019) and are not counted here.
