# Task Templates System – Implementation Plan for Cursor (Zenamanage Repo Guidance Only, No Code Yet) 🧩📋

**Owner (Business/Spec):** User  
**Implementer (Code):** Cursor  
**Reviewer:** Codex  
**Status:** 🔁 Planned – Not Implemented (no migrations or code yet)  
**Scope:** Add a WBS-style “Task Template” system, fully tenant-safe, that powers project creation via templates.

> This document is an implementation roadmap for Cursor.  
> Codex and the user aligned on the architecture and diff plan. All actual code/migration/UI work is to be done by **Cursor**, not by Codex.

---

## 1. High-Level Goals

- Build a **Task Template Library** (phases → disciplines → tasks + dependencies) under `/admin/templates` (Blade).
- Let users apply templates to new projects via a wizard step in `/app/projects/new` (React/TS).
- Ensure:
  - Multi-tenant isolation.
  - Safe migrations (no breaking changes).
  - Chunked inserts, transactions, and optional queuing for very large templates.
  - Full test coverage (Unit/Feature/E2E) and clear documentation.

The design is intentionally aligned with existing repo conventions:

- Laravel multi-tenant, `tenant_id` usage and `TenantIsolationTest`.
- Services in `app/Services`, Policies in `app/Policies`, tests in `tests/Feature|Unit|E2E`.
- React SPA under `frontend/src` for `/app/*`, Blade for `/admin/*`.

---

## 2. Data Model – Tables & Eloquent Models

### 2.1. New Tables (migrations)

> All names are agreed at the spec level. Cursor is responsible for exact timestamps, indexes, foreign keys, and `up/down` definitions, following existing migration style.

1. `template_sets`
   - Columns:
     - `id` (ULID/string primary key)
     - `tenant_id` (nullable; `null` = global template)
     - `code` (string)
     - `name` (string)
     - `description` (text, nullable)
     - `version` (string or integer; agreed to treat as simple string like `"2025.1"` in spec)
     - `is_active` (boolean)
     - `is_global` (boolean) – for global vs tenant-specific sets
     - `created_by` (string ULID user id)
     - `metadata` (json, nullable)
     - Timestamps
   - Indexes:
     - Unique: (`tenant_id`, `code`) or (`code` where `tenant_id IS NULL`) for global.
     - `is_active`, `version`, `tenant_id`.

2. `template_phases`
   - Columns:
     - `id`
     - `set_id` FK → `template_sets.id`
     - `code`, `name`
     - `order_index` (int)
     - `metadata` (json, nullable)
   - Indexes: `set_id`, `code`, `order_index`.

3. `template_disciplines`
   - Columns:
     - `id`
     - `set_id` FK → `template_sets.id`
     - `code`, `name`
     - `color_hex` (string, nullable)
     - `order_index` (int)
     - `metadata` (json, nullable)
   - Indexes: `set_id`, `code`, `order_index`.

4. `template_tasks`
   - Columns:
     - `id`
     - `set_id` FK → `template_sets.id`
     - `phase_id` FK → `template_phases.id`
     - `discipline_id` FK → `template_disciplines.id`
     - `code` (string)
     - `name` (string)
     - `description` (text, nullable)
     - `est_duration_days` (int, nullable)
     - `role_key` (string, nullable)
     - `deliverable_type` (string, nullable)
     - `order_index` (int)
     - `is_optional` (boolean)
     - `metadata` (json, nullable)
     - Timestamps
   - Indexes: `set_id`, `code`, `phase_id`, `discipline_id`, `order_index`.

5. `template_task_dependencies`
   - Columns:
     - `id`
     - `set_id` FK → `template_sets.id`
     - `task_id` FK → `template_tasks.id`
     - `depends_on_task_id` FK → `template_tasks.id`
   - Indexes:
     - Unique: (`task_id`, `depends_on_task_id`).
     - `set_id`, `task_id`, `depends_on_task_id`.

6. `template_presets`
   - Columns:
     - `id`
     - `set_id` FK → `template_sets.id`
     - `code`, `name`
     - `description` (text, nullable)
     - `filters` (json) – convention:
       ```json
       {
         "phases": ["CONCEPT"],
         "disciplines": ["ARC"],
         "tasks": ["ARC-C01"],
         "include": [],
         "exclude": []
       }
       ```
   - Indexes: `set_id`, `code`, `name`.

7. `template_apply_logs`
   - Columns:
     - `id`
     - `project_id` (ULID/string → `projects.id`)
     - `tenant_id` (string → `tenants.id`)
     - `set_id` (FK → `template_sets.id`)
     - `preset_code` (string, nullable)
     - `selections` (json) – request selections (phases/disciplines/tasks)
     - `counts` (json) – counts summary (tasks_created, dependencies_created, etc.)
     - `executor_id` (string → `users.id`)
     - `duration_ms` (int)
     - `created_at` timestamp
   - Indexes: `project_id`, `tenant_id`, `set_id`, `created_at`.

> `task_dependencies` table *already exists* in this repo (migration `2025_09_16_082344_create_task_dependencies_table.php` and model `App\Models\TaskDependency`), so **do not** create a new one.

### 2.2. New Eloquent Models

All under `app/Models`, PSR-12, with docblocks matching existing style.

1. `TemplateSet`
   - `$table = 'template_sets'`.
   - Traits: `HasUlids`, `HasFactory`, optional `TenantScope` if appropriate.
   - Relationships:
     - `phases(): HasMany`
     - `disciplines(): HasMany`
     - `tasks(): HasMany`
     - `presets(): HasMany`
     - `applyLogs(): HasMany`
   - Scopes:
     - `active()`
     - `forTenantOrGlobal(string $tenantId)`

2. `TemplatePhase`
   - Relationships: `set()`, `tasks()`, and `scopeOrdered()`.

3. `TemplateDiscipline`
   - Relationships: `set()`, `tasks()`.
   - Holds `color_hex`.

4. `TemplateTask`
   - Relationships:
     - `set()`, `phase()`, `discipline()`
     - `dependencies()` → `hasMany(TemplateTaskDependency::class, 'task_id')`
     - `dependents()` → `hasMany(TemplateTaskDependency::class, 'depends_on_task_id')`
   - Helpers:
     - Accessor or helper for normalized code.
     - Helper to fetch dependency tasks.

5. `TemplateTaskDependency`
   - Relationships: `set()`, `task()`, `dependsOn()`.

6. `TemplatePreset`
   - Relationship: `set()`.
   - Helper: `matches(Project $project, array $selection)` if needed for future logic.

7. `TemplateApplyLog`
   - Relationships: `project()`, `set()`, `executor()`.

> Important: This repo already has `Template`, `TemplateTask`, `TemplateVersion`, `WorkTemplate`, etc.  
> These new models are **for WBS task templates only**. Make sure the docblocks and naming clearly distinguish them from the existing generic template system.

---

## 3. Policies & RBAC

### 3.1. New Policy: `TemplateSetPolicy`

- File: `app/Policies/TemplateSetPolicy.php`
- Abilities:
  - `viewAny`, `view`, `create`, `update`, `delete`, `import`, `apply`, `publish`, `export`.
- Rules:
  - Only system-level admin roles (e.g. `isSuperAdmin()` or similar existing super-admin definition) should manage template sets.
  - Enforce tenant isolation: user `tenant_id` must match when using tenant-scoped sets.
  - `import` and `apply` should also be restricted to proper admin/system roles.

### 3.2. AuthServiceProvider Mapping

- File: `app/Providers/AuthServiceProvider.php`
  - Add mapping:
    ```php
    'App\Models\TemplateSet' => 'App\Policies\TemplateSetPolicy',
    ```
  - Preserve existing `TemplatePolicy` mapping for `App\Models\Template`.

---

## 4. Services – Import & Apply

### 4.1. `TemplateImportService`

- File: `app/Services/TemplateImportService.php`
- Responsibilities:
  - Input: uploaded file (`csv` / `xlsx` / `json`) + current `User` + optional `tenant_id`.
  - Validate:
    - For CSV/XLSX: headers (snake_case, support Vietnamese titles but normalize).
    - For JSON: schema defined in `resources/templates/schema.template.json`.
  - Transform:
    - Normalize codes to uppercase, convert spaces/hyphens to underscores.
    - Ensure `task_code` uniqueness within a set (error or warning).
  - Persist:
    - Create `TemplateSet` + `TemplatePhase` + `TemplateDiscipline` + `TemplateTask` + `TemplateTaskDependency` + `TemplatePreset`.
  - Expose main methods:
    - `importFromFile(UploadedFile $file, User $user, ?string $tenantId): TemplateSet`
    - `importFromJson(array $payload, User $user, ?string $tenantId): TemplateSet`

> Reuse `CsvImportService` / `ImportExportService` if they already exist in repo to avoid duplicated CSV parsing code.

### 4.2. `TemplateApplyService`

- File: `app/Services/TemplateApplyService.php`
- Responsibilities:
  - Input:
    - `Project $project`
    - `TemplateSet $set`
    - Optional `preset_code`
    - `selections` (phases/disciplines/tasks)
    - `options`:
      - conflict behavior: `skip`/`rename`/`merge` (default spec: `skip + warning`)
      - flags: `map_phase_to_kanban`, `auto_assign_by_role`, `create_deliverable_folders`
    - `User $executor`
  - Features:
    1. **Preview**
       - `preview(...)` → return:
         - Total #tasks, #dependencies.
         - Estimated duration (sum or other rule).
         - Breakdown by phase/discipline.
    2. **Apply**
       - `apply(...)`:
         - Resolve selections and presets:
           - Filter by phases/disciplines/tasks + presets filters (`include` / `exclude`).
         - Resolve dependencies:
           - Build dependency graph on `TemplateTask`.
           - Topological sort to apply tasks in correct order.
         - Insert tasks:
           - Create `Task` rows for the project:
             - map `TemplateTask` → `Task` fields (name, description, estimated duration → estimated_hours/dates).
           - Chunk insert (100–500 rows per chunk) using `insert` or `upsert`.
           - Wrap **per-phase** in `DB::transaction()` to limit lock scope.
         - Create `TaskDependency` entries:
           - Map `template_task_dependencies` to `task_dependencies` by matching new task IDs.
         - Mapping:
           - Phase → Kanban column:
             - Use existing `status` / board setup; if board columns model exists, integrate with it.  
             - If not, at minimum: set `Task.status` to a phase-mapped status or create board columns if repo already has `board_columns` (Cursor should inspect models first).
           - Discipline → label:
             - Use `tags` json or existing label system; embed discipline `code` and `color_hex`.
           - Role → assignee:
             - Use existing project assignment logic (`ProjectAssignmentService`, `ProjectTeams`, etc.) if available; fall back gracefully if no mapping.
         - Deliverable folders:
           - Create directories:
             - `/storage/projects/{project_id}/deliverables/{phase_code}/{discipline_code}/`
           - Ensure safe directory creation and idempotency.
         - Logging:
           - Record `TemplateApplyLog` with selections and counts.
           - Emit `TemplateApplied` event (if you add such an event).

---

## 5. API & Routes

### 5.1. Admin (Blade, `routes/web.php`)

- Add routes (inside existing `auth` + `tenant` + admin/system middleware chain):
  - `GET  /admin/templates` → `Admin\TemplateSetController@index`
  - `GET  /admin/templates/{set}` → `Admin\TemplateSetController@show`
  - `POST /admin/templates` → `Admin\TemplateSetController@store`
  - `PUT  /admin/templates/{set}` → `Admin\TemplateSetController@update`
  - `DELETE /admin/templates/{set}` → `Admin\TemplateSetController@destroy`
  - `POST /admin/templates/import` → `Admin\TemplateSetController@import`

### 5.2. App (React/JSON, `routes/api_v1.php`)

- Add API under existing `/api/v1/app` structure:
  - `GET  /api/v1/app/templates` → list template sets (with phases, disciplines, presets).
  - `POST /api/v1/app/templates/preview` → body: selections; returns preview stats.
  - `POST /api/v1/app/projects/{project}/apply-template` → body: `set_code`, optional `preset_code`, `selections`, `options`.
  - `GET  /api/v1/app/projects/{project}/templates/history` → list `TemplateApplyLog` entries.

### 5.3. Controllers

- `app/Http/Controllers/Admin/TemplateSetController.php`
  - Uses `TemplateSetPolicy` and `TemplateImportService`.
  - Renders Blade views in `resources/views/admin/templates`.

- `app/Http/Controllers/Api/TemplateController.php`
  - Methods:
    - `index()` – list template sets (tenant-specific + global).
    - `preview(TemplatePreviewRequest $request)`
    - `apply(TemplateApplyRequest $request, Project $project)`
    - `history(Project $project)`
  - Uses `auth:sanctum`, `tenant`, `security`, `validation` middleware (match patterns of other `/api/v1/app/*` controllers).

---

## 6. UI/UX – Admin Blade & Project Wizard (React)

### 6.1. Admin – `/admin/templates` (Blade + Alpine)

- Views (new):
  - `resources/views/admin/templates/index.blade.php`
    - Lists `TemplateSet`s with filters:
      - version, `is_active`, preset presence.
    - Buttons: “Import”, “Create new”, “View”.
  - `resources/views/admin/templates/show.blade.php`
    - Shows:
      - Phases table.
      - Disciplines table.
      - Tasks table (phase/discipline columns, order, duration, role, deliverable_type).
      - Dependencies view (basic graph or list).
    - Controls:
      - Inline edit for simple fields if desired.
      - Button “Publish new version” → clones set + increments `version`.

### 6.2. App – Project Creation Wizard (React/TS)

- Routing:
  - Update `frontend/src/router.tsx` (or equivalent):
    - Add route `"/app/projects/new"` → `ProjectCreateWizardPage`.
- New React page:
  - `frontend/src/pages/projects/ProjectCreateWizardPage.tsx`
    - Multi-step wizard.
    - Step “Choose Template”:
      - Tabs: Presets | Phases | Disciplines | Tasks.
      - Multi-select + search (code/name).
      - Options:
        - Checkbox: “Map Phase → Kanban columns”
        - Checkbox: “Auto-assign by Role”
        - Checkbox: “Create deliverable folders”
      - Buttons:
        - `Preview` → `POST /api/v1/app/templates/preview`.
        - `Apply` → `POST /api/v1/app/projects/{project}/apply-template` after project is created.
- New front-end helper/API files:
  - `frontend/src/features/templates/api.ts`
    - Functions:
      - `getTemplates()`
      - `previewTemplate(payload)`
      - `applyTemplate(projectId, payload)`
  - `frontend/src/features/templates/components/TemplateSelectionTabs.tsx`
  - `frontend/src/features/templates/components/TemplatePreviewPanel.tsx`

---

## 7. Import/Export – CSV & JSON

### 7.1. CSV / Excel

- Expected header (snake_case, no diacritics for header; data can be localized):
  ```text
  phase_code,phase_name,discipline_code,discipline_name,color_hex,task_code,task_name,description,est_duration_days,role_key,deliverable_type,order_index,is_optional,depends_on_codes
  ```
- Support:
  - Single-sheet “all tasks”.
  - Multi-sheet (each sheet = one discipline) if reasonable in implementation.
  - `depends_on_codes` separated by `|` (e.g. `ARC-C01|ARC-C05`).

### 7.2. JSON schema

- File: `resources/templates/schema.template.json`
- Shape (example):
  ```json
  {
    "set": {"code": "WBS-AEC-INTL", "name": "WBS AEC Intl", "version": "2025.1"},
    "phases": [
      {"code":"CONCEPT", "name":"Concept", "order":1}
    ],
    "disciplines": [
      {"code":"ARC", "name":"Architecture", "color":"#1E88E5"}
    ],
    "tasks": [
      {
        "code":"ARC-C01","name":"Master layout",
        "phase":"CONCEPT","discipline":"ARC",
        "description":"Zoning, flow, program",
        "est_duration_days":3,
        "role_key":"lead_architect",
        "deliverable_type":"layout_dwg",
        "order":1,
        "is_optional":false,
        "depends_on":["ARC-C00"]
      }
    ],
    "presets": [
      {"code":"HOUSE","name":"Townhouse","filters":{"disciplines":["ARC","MEP"],"exclude":["LND-PANO"]}}
    ]
  }
  ```

### 7.3. Sample Files

- Add:
  - `resources/templates/sample.aec-intl.json`
  - `resources/templates/sample.aec-intl.csv`
  - Both should align with the schema and headers above.

---

## 8. Validation, Safety & Performance

- Tenant scope:
  - All `/app/*` calls must respect `tenant_id` as other APIs do.
  - `TemplateSetPolicy` + service methods to enforce tenant isolation.
- Import:
  - Validate required fields and report errors with line numbers and clear messages.
  - Normalize headers (support localized names but map to snake_case).
- Apply:
  - Handle existing `Task` codes in project:
    - Default: `skip` + warning in log/counts.
    - Later: optional `rename`/`merge` behavior.
  - Chunk inserts (100–500).
  - Per-phase transactions to avoid long-running locks.
  - Queue job (optional) when total tasks > ~5000 (use existing queue configuration).
- Logging:
  - `TemplateApplyLog` with selection and counts.
  - Optional event `TemplateApplied`.

---

## 9. Testing Plan

### 9.1. Unit Tests (PHPUnit)

- `tests/Unit/Services/TemplateImportServiceTest.php`
  - Cases: valid CSV, invalid header, duplicate task codes, JSON import, code normalization.
- `tests/Unit/Services/TemplateApplyServiceTest.php`
  - Cases: dependency topo sort, per-phase transaction behavior, chunked inserts, mapping (phase/status, discipline/tags, basic role mapping).

### 9.2. Feature Tests

- `tests/Feature/AdminTemplateImportTest.php`
  - Login as admin/system user.
  - POST `/admin/templates/import` with sample CSV/JSON.
  - Assert DB has `template_set` + phases + disciplines + tasks + dependencies + presets.
- `tests/Feature/ProjectApplyTemplateTest.php`
  - Given project + template set.
  - Call `/api/v1/app/templates/preview` and `/api/v1/app/projects/{project}/apply-template`.
  - Assert tasks created with correct phase/discipline mapping, dependencies, and logging.

### 9.3. E2E – Playwright

- `tests/e2e/template-apply.spec.ts`
  - Flow:
    1. Login as admin.
    2. Go to `/admin/templates` → import CSV sample.
    3. Open wizard `/app/projects/new` → Template step.
    4. Choose preset “High-rise” + phase CONCEPT (or similar).
    5. Preview → Apply.
    6. Navigate to Project board/Kanban.
    7. Verify:
       - Columns per phase present.
       - Task count matches preview.
       - Discipline labels/colors visible.
       - Dependencies (blocked/by) reflected in UI or via API assertions.

---

## 10. Documentation & Feature Flag

- New doc file:
  - `docs/templates.md`
    - Describe:
      - DB schema for `template_*` tables.
      - JSON and CSV schema.
      - API endpoints.
      - Import → Apply → Verify flows.
      - DoD checklist.
- Feature flag:
  - Add `FEATURE_TASK_TEMPLATES` to `.env.example` and appropriate config (`config/features.php` or similar).
  - Controllers/routes/React UI should check this flag and hide/disable feature if `false`.

---

## 11. Implementation Order (for Cursor)

Follow this order (matching the user’s requested roadmap):

1. **Survey** (already done here) – re-validate assumptions if needed.
2. **Migrations & Models** for `template_*` and `TemplateApplyLog`.
3. **Policy & RBAC** – `TemplateSetPolicy` + mapping in `AuthServiceProvider`.
4. **Services** – `TemplateImportService`, `TemplateApplyService` (with unit tests).
5. **API & Controllers** – Web admin controller + API controller, requests/DTOs.
6. **UI Admin (Blade)** – `/admin/templates` index + detail.
7. **Wizard UI (React)** – `/app/projects/new` template step + API wiring.
8. **Seeders & Sample Files** – `AecIntl2025Seeder` + sample CSV/JSON.
9. **E2E Playwright Test** – `tests/e2e/template-apply.spec.ts`.
10. **Docs & Feature Flag** – `docs/templates.md`, `.env.example`, CI config tweaks if necessary.

---

## 12. Definition of Done (DoD)

- Import CSV/JSON successfully creates a `TemplateSet` with phases/disciplines/tasks/dependencies/presets.
- Project creation wizard can:
  - Preview and Apply templates with combinations of preset/phase/discipline/task selections.
  - Resulting tasks show correct:
    - Kanban columns (phase-mapped).
    - Discipline labels/colors.
    - Role-based assignees (as far as current role mapping permits).
    - Task dependencies.
    - Deliverable folder structure.
- All Unit/Feature/E2E tests pass.
- Coverage ≥ 80% for `TemplateImportService` and `TemplateApplyService`.
- Migrations are safe and reversible, and no existing functionality breaks.

> Cursor: please treat this document as the single source of truth for the Task Templates implementation.  
> If you discover conflicts with existing models/services during implementation, prefer **minimal, explicit adaptations** over broad refactors, and keep multi-tenant safety as the top priority.

