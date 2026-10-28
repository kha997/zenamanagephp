# GAP-021 — `/api/v1/tasks*` vs `/api/zena/tasks*`: Gate-1 evidence

**Date:** 2026-10-28 (+07:00)

**Canonical base:** `cc07e77dae62352b144134f6f841d62a7489073f`

**Branch:** `docs/GAP-021-tasks-api-parity`

**Scope:** re-verify the three differences the register lists for GAP-021 (status `UNVERIFIED`, sources
`docs/audits/2026-03-19-tasks-contract-parity-audit.md:176-183`,
`docs/audits/2026-03-19-tasks-v1-split-owner-route-inventory-audit.md:150-159`). No code change.

## Finding 1 — Permission drift is real and blocks non-super-admin edits (confirmed by reproduction)

- `src/CoreProject/routes/api.php:96-97`: `PUT|PATCH api/v1/tasks/{task}` require `rbac:task.edit`.
- `routes/api_zena.php:268`: `PUT api/zena/tasks/{id}` requires `rbac:task.update`.
- **No seeder creates `task.edit`.** `task.update` is seeded by `PermissionSeeder`, `RolePermissionSeeder`,
  `ZenaPermissionsSeeder` and `TestDatabaseSeeder`; a repo-wide search finds `task.edit` only in the two route files above,
  two tests that create it themselves (`tests/Feature/TaskTest.php:41`, `tests/Feature/RBACRolesPermissionsTest.php`), and
  the 2026-03-19 audits.
- `RoleBasedAccessControlMiddleware::checkAccess()` lets super admins through; everyone else needs the exact permission
  name (`User::hasPermission()`).
- The existing v1 tests pass because their user carries the `super_admin` role
  (`tests/Traits/TenantUserFactoryTrait.php:24-28`, default roles `['super_admin', 'Admin']`).

**Reproduction** (temporary test, not committed): a user with role `Admin` (not super admin) holding the seeded
`task.view`, `task.update`, `project.view` on one tenant and project:

| Request | Result |
|---|---|
| `PUT /api/v1/tasks/{id}` | **403** `E403.AUTHORIZATION` |
| `PUT /api/zena/tasks/{id}` | 200, task renamed |
| `GET /api/v1/tasks` | 200 |
| `GET /api/zena/tasks` | 200 |

So on any seeded tenant, only super admins can edit a task through `/api/v1/tasks`. The same `rbac:task.edit` gates three
work-template projection routes (`src/WorkTemplate/routes/api.php:79,82,83`: task update, progress, toggle-conditional),
which are therefore also super-admin only.

## Finding 2 — Middleware stack differs (confirmed)

`php artisan route:list --json` on the base:

| Family | Middleware |
|---|---|
| `api/v1/tasks*` | `api`, `auth:api` (Sanctum driver), `TenantIsolationMiddleware`, `rbac:*` |
| `api/zena/tasks*` | `api`, `auth:sanctum`, `TenantIsolationMiddleware`, **`InputSanitizationMiddleware`**, **`ErrorEnvelopeMiddleware`**, `rbac:*` |

Both guards resolve to Sanctum (`config/auth.php` guard `api` uses driver `sanctum`), so authentication is equivalent; the
compatibility family lacks input sanitization and the error envelope.

## Finding 3 — Response envelope differs (confirmed)

Same reproduction: `GET /api/v1/tasks` returns `{"status":"success","data":{"tasks":[…],"pagination":…}}`; `GET
/api/zena/tasks` returns `{"success":true,"status":"success",…,"data":[…],"meta":{"pagination":…}}`. This is the
documented compatibility contract, locked by `tests/Feature/Api/V1TasksCompatibilityCrudTest.php`.

## Who calls `/api/v1/tasks*`

- No mounted web page calls it: `resources/views/tasks/form.blade.php` uses `PUT/DELETE /api/v1/tasks/{id}`, but no route or
  view renders `tasks.form` (orphan view); `resources/views/admin/analytics-content.blade.php:375` is a static sample
  table.
- External clients: UNKNOWN (same as the 2026-03-19 audit).

## Not changed by this evidence

Ownership split (four controllers for task assignment) is unchanged since the 2026-03-19 audit and is technical debt, not a
runtime defect; it is not proposed for this Work ID.
