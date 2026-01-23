# RBAC Permissions Universe (SSOT)

This document captures every permission code that is currently seeded or hard-coded in the Laravel repository, groups them by module, and pinpoints the alias/mismatch surface so that the “single source of truth” stays executable and predictable. No business logic was touched—this is purely an audit and recommendation for naming alignment.

## Canonical permission map (by module)

Each table below collects the canonical codes that are already recorded in the seeders (`database/seeders/PermissionSeeder.php`, `database/seeders/RolePermissionSeeder.php`, `database/seeders/ZenaRbacSeeder.php`) plus the controllers/middleware that currently rely on them.

### Project (`project`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `project.create` | create | `PermissionSeeder` seeds this as “Tạo dự án mới” for CRUD APIs. | `database/seeders/PermissionSeeder.php:23-26` |
| `project.read` | read/view | Core “Xem thông tin dự án” used by RBAC guard, `PermissionService`, sidebar filters. | `database/seeders/PermissionSeeder.php:23-26`, `app/Services/PermissionService.php:92-137` |
| `project.update` | update | “Cập nhật dự án” plus `PermissionService` route map for edit. | `PermissionSeeder` + `app/Services/PermissionService.php:104-131` |
| `project.delete` | delete | Delete APIs/roles rely on the seeded code. | `PermissionSeeder` |
| `project.archive`, `project.restore`, `project.duplicate` | lifecycle | Additional administrative actions in `RolePermissionSeeder`. | `database/seeders/RolePermissionSeeder.php:23-42`, `app/Services/PermissionService.php:92-132` |
| `project.manage_team` | team management | Teams block in `RolePermissionSeeder`. | `database/seeders/RolePermissionSeeder.php:26-30` |
| `project.view_budget`, `project.edit_budget` | budget | Used by finance/pm roles in `RolePermissionSeeder`. | `database/seeders/RolePermissionSeeder.php:27-29` |
| `project.view_files`, `project.upload_files` | documents | Same `RolePermissionSeeder` block (project file view/upload permissions). | `database/seeders/RolePermissionSeeder.php:29-30` |
| `project.assign` | assignment | Defined in `ZenaRbacSeeder` and referenced by API controllers (project assignment). | `database/seeders/ZenaRbacSeeder.php:43-48` |
| `project.write` | composite create/update | Used by API controllers (`ProjectController`, `ProjectTemplateController`, `ProjectMilestoneController`) and seeded for Z.E.N.A roles. Should expand to `project.create` + `project.update`. | `database/seeders/ZenaRbacSeeder.php:43-48`, `app/Http/Controllers/Api/ProjectController.php`, `ProjectTemplateController.php`, `ProjectMilestoneController.php` |

### Task (`task`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `task.create` / `task.read` / `task.update` / `task.delete` | CRUD | Fundamental task permissions from `PermissionSeeder` and reused by services/route guards. | `database/seeders/PermissionSeeder.php:29-34` |
| `task.assign` | assignee | Available in `PermissionSeeder` and `RolePermissionSeeder` for assignment-focused roles. | `PermissionSeeder`, `RolePermissionSeeder` |
| `task.comment` / `task.attach_files` / `task.change_status` | collaboration | Seeded additional capabilities in `RolePermissionSeeder`. | `database/seeders/RolePermissionSeeder.php:38-42` |
| `task.view_time_tracking` / `task.edit_time_tracking` | time tracking | Present in `RolePermissionSeeder`; sits behind manager templates. | `database/seeders/RolePermissionSeeder.php:40-42` |
| `task.write` | combined create/update | Declared in `ZenaRbacSeeder` but only used there; should map to `task.create` + `task.update`. | `database/seeders/ZenaRbacSeeder.php:48-52` |

### Document (`document`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `document.create`, `document.read`, `document.update`, `document.delete` | CRUD | Core document workflow seeded once. | `database/seeders/PermissionSeeder.php:41-46` |
| `document.approve` | approval | Granted to approval-focused roles/notification guards. | `database/seeders/PermissionSeeder.php:45-46` |

### Change Request (`change_request`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `change_request.create`, `change_request.read`, `change_request.update`, `change_request.approve`, `change_request.reject` | CRUD + approvals | Seeded for the new requests API. | `database/seeders/PermissionSeeder.php:48-53` |
| `change_request.view`, `change_request.edit`, `change_request.delete`, `change_request.submit`, `change_request.stats`, `change_request.approve`, `change_request.reject` | middleware aliases | Routes under `src/ChangeRequest/routes/api.php` depend on these RBAC middleware labels. Only a subset is seeded above. | `src/ChangeRequest/routes/api.php:20-76` |

### RFI (`rfi`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `rfi.read`, `rfi.create`, `rfi.answer`, `rfi.assign` | full workflow | Defined for the RFI module in `ZenaRbacSeeder` and consumed by the preset service’s `required_permissions` checks. | `database/seeders/ZenaRbacSeeder.php:60-86`, `app/Services/PresetService.php:188-820` |

### Submittal (`submittal`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `submittal.read`, `submittal.create`, `submittal.approve`, `submittal.review` | submittal workflow | Defined in the same Z.E.N.A seeder and checked by the same preset service. | `database/seeders/ZenaRbacSeeder.php:66-94`, `app/Services/PresetService.php:199-752` |

### Inspection (& QC)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `qc.plan`, `qc.inspect`, `qc.approve`, `qc.read` | QC/inspection | Z.E.N.A legacy permission set for QC inspections, but the new `Api/InspectionController` does not guard these codes yet. | `database/seeders/ZenaRbacSeeder.php:78-110`, `app/Http/Controllers/Api/InspectionController.php` |
| *(no `inspection.*` codes yet)* | placeholder | The inspection controller currently returns stubs without checking any permission code. | `app/Http/Controllers/Api/InspectionController.php` |

### Dashboard (`dashboard`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `dashboard.view` | view dashboards | Frontend nav (`frontend/src/layouts/ZenaLayout.tsx:48`) requires this permission, but no seeder entry exists yet. | `frontend/src/layouts/ZenaLayout.tsx:48` |

### Admin (`admin`)

| Code | Derived action | Description | Source |
| --- | --- | --- | --- |
| `admin.user.manage`, `admin.role.manage`, `admin.system.manage` | system administration | Seeded via `ZenaRbacSeeder` as part of the admin role definitions. | `database/seeders/ZenaRbacSeeder.php:115-160` |
| `admin.sidebar.manage`, `admin.system.manage` | sidebar + system | Hard-coded into `PermissionService` for role-based sidebar filtering even though only the system admin seed provides `admin.system.manage`. | `app/Services/PermissionService.php:92-182` |

## Alias mapping & usage fingerprints

The following aliases are already baked into controllers, middleware or routes; each alias should either become a canonical code or be mapped (in a helper or middleware) to the canonical entry before enforcing.

| Alias | Canonical target(s) | Where seen |
| --- | --- | --- |
| `project.view` | `project.read` | RBAC middleware (`src/RBAC/routes/api.php:34-76`, `src/CoreProject/routes/api.php:15-20`), `task`/`assignment` routes, `app/Services/TaskAccessMiddleware`. |
| `project.edit` | `project.update` | `ChangeRequest` routes (`src/ChangeRequest/routes/api.php:33`), `CoreProject` controllers. |
| `project.write` | `project.create` + `project.update` | `app/Http/Controllers/Api/ProjectController.php`, `ProjectTemplateController.php`, `ProjectMilestoneController.php`. |
| `task.view` | `task.read` | WorkTemplate routes, `CoreProject` routes, `task` middleware. |
| `task.edit` | `task.update` | Same as above, per `src/CoreProject/routes/api.php:86-104`. |
| `task.write` | `task.create`, `task.update` | Defined in `ZenaRbacSeeder` for PM/designer roles. |
| `change_request.view` | `change_request.read` | Change request CRUD routes (`src/ChangeRequest/routes/api.php:20-42`). |
| `change_request.edit` | `change_request.update` | `src/ChangeRequest/routes/api.php:33`. |
| `change_request.delete` | `change_request.reject` / custom deletion? | Delete route uses this alias (`src/ChangeRequest/routes/api.php:38`). |
| `change_request.submit`, `change_request.stats` | (new canonical) | Workflow routes rely on these names even though the permission table only includes `create/read/update/approve/reject`. |
| `manage_projects`, `manage_tasks`, `manage_documents`, `manage_reports`, `view_projects`, `view_settings` | legacy dot notation | `HasRoles::hasPermission` (`app/Traits/HasRoles.php:102-132`) feeds these into the guard. |
| `create_project`, `edit_project`, `view_project`, `create_task`, `edit_task`, `view_task`, `manage_team`, `view_documents`, `view_analytics`, `manage_settings` | (map to `project.*`, `task.*`, `document.*`, `admin.settings`) | `RoleBasedAccessControlMiddleware::isPermission` (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php:109-229`). |
| `dashboard.view` | (new canonical) | Frontend layout (`frontend/src/layouts/ZenaLayout.tsx:48`). |
| `admin.sidebar.manage` | (new canonical or reuse `admin.role.manage`) | Sidebar builder routes and `PermissionService` route map handle this. |

## Mismatches & coverage gaps

- **Middleware/roles require codes that are not seeded**: `src/RBAC/routes/api.php`, `src/ChangeRequest/routes/api.php`, and the legacy RBAC middleware rely on `project.view/edit`, `task.view/edit`, `change_request.view/edit/delete/submit/stats`, but `database/seeders/PermissionSeeder.php` only seeds the more granular `*.read/update/delete` names. This leaves RBAC guards pointing to permissions that either never exist (401s in production) or diverge from the `Permission` model.
- **Legacy helpers duplicate non-canonical names**: `app/Traits/HasRoles::hasPermission` and `app/Http/Middleware/RoleBasedAccessControlMiddleware` both expect snake_case names like `manage_projects`, `create_project`, `view_project`, `manage_team`, etc. They bypass the canonical `project.*` and `task.*` codes, so this part of the stack is not yet aligned to the SSOT.
- **Modules without seed coverage**:
  - **RFI + Submittal**: These rely on `ZenaRbacSeeder` (`rfi.*`, `submittal.*`), but the main `PermissionSeeder` never seeds them, so the Laravel RBAC APIs cannot enforce these codes unless `ZenaPermission` is kept in sync.
  - **Inspection/QC**: Controllers reference inspection flows but there are no `inspection.*` permissions in `PermissionSeeder`; only `qc.*` exists in `ZenaRbac`. The REST endpoints return stubs without permission checks, which leaves this module unprotected under the new RBAC implementation.
  - **Dashboard**: The UI gate uses `dashboard.view`, but nothing in the seeders creates this code.
  - **Admin sidebar/system**: `PermissionService` wants `admin.sidebar.manage` and `admin.system.manage`, yet neither is in `PermissionSeeder`, so runtime access depends on hard-coded role definitions or legacy seeding (`ZenaPermission`).

## Suggested SSOT alignment steps

1. **Consolidate seeds**: Expand `database/seeders/PermissionSeeder.php` to include everyone’s required codes (`project.{archive,restore,duplicate,manage_team,view_budget,edit_budget,view_files,upload_files,assign}`, `task.{comment,attach_files,change_status,view/edit_time_tracking}`, `change_request.{view,edit,delete,submit,stats}`, `rfi.*`, `submittal.*`, `admin.user.manage`, `admin.role.manage`, `admin.system.manage`, `dashboard.view`) so that the `Permission` model becomes the single source of truth for every module.
2. **Normalize middleware/route names**: Update middleware registration (RBAC middleware and `RoleBasedAccessControlMiddleware`) to resolve `project.view/edit`, `task.view/edit`, `change_request.*` to the canonical `project.*`, `task.*`, `change_request.*` codes at runtime rather than using ad-hoc strings. This can be a small helper (e.g., `permissionAliases()` lookup) that maps existing aliases to seeded code.
3. **Map legacy helper arrays**: Rework `HasRoles::hasPermission` and `PermissionService` to either call into the central `Permission` table or reuse the canonical names (`project.*`, `task.*`, `document.*`, `admin.*`). The current snake_case names should be treated as aliases in the mapping helper and not re-seeded again.
4. **Define module gaps**: 
   - Add `inspection`/`qc` permission seeds if the inspection controllers are expected to become guarded (or make `InspectionController` throw `403` until they exist).
   - Seed `dashboard.view` so the frontend nav and any future dashboard APIs can rely on a real RBAC row instead of a frontend-only flag.
   - Keep `ZenaRbac` and `PermissionSeeder` in sync (e.g., migrate `rfi.*`/`submittal.*` into the main seeder) so the SQL table used by `PermissionService` and `RBACManager` matches what the routes expect.

Following the above steps will give the project, task, document, inspection, RFI, submittal, change request, dashboard, and admin modules a single canonical naming pattern and allow middleware/controller checks to stay deterministic.
