# Task Templates System Documentation

## Overview

The Task Templates System is a WBS-style template system that enables administrators to create reusable task templates (phases → disciplines → tasks + dependencies) and allows users to apply these templates when creating projects. The system is fully tenant-safe, supports global templates, and integrates seamlessly with existing architecture.

## Database Schema

### Tables

#### `template_sets`
Main template set table. Stores template metadata.

- `id` (ULID, primary key)
- `tenant_id` (string, nullable) - null for global templates
- `code` (string) - Unique code for the template set
- `name` (string) - Template set name
- `description` (text, nullable) - Template set description
- `version` (string) - Version string (e.g., "2025.1")
- `is_active` (boolean) - Whether the template set is active
- `is_global` (boolean) - Whether this is a global template
- `created_by` (ULID) - User ID who created this template set
- `metadata` (json, nullable) - Additional metadata
- `created_at`, `updated_at`, `deleted_at` (timestamps)

**Indexes:**
- Unique: `(tenant_id, code)` - Note: For global templates (tenant_id = NULL), uniqueness is enforced at application level
- Indexes: `is_active`, `version`, `tenant_id`

#### `template_phases`
Phases within a template set (e.g., CONCEPT, DESIGN, CONSTRUCTION, QC).

- `id` (ULID, primary key)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `code` (string) - Phase code (e.g., "CONCEPT")
- `name` (string) - Phase name
- `order_index` (integer) - Order index for sorting
- `metadata` (json, nullable) - Additional metadata
- `created_at`, `updated_at` (timestamps)

**Indexes:** `set_id`, `code`, `order_index`

#### `template_disciplines`
Disciplines within a template set (e.g., ARC, MEP, STR, LND).

- `id` (ULID, primary key)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `code` (string) - Discipline code (e.g., "ARC")
- `name` (string) - Discipline name
- `color_hex` (string, nullable) - Color hex code for UI display
- `order_index` (integer) - Order index for sorting
- `metadata` (json, nullable) - Additional metadata
- `created_at`, `updated_at` (timestamps)

**Indexes:** `set_id`, `code`, `order_index`

#### `template_tasks`
Tasks within a template set. **Note:** This is distinct from the existing `App\Models\TemplateTask` which is used for `ProjectTemplate`.

- `id` (ULID, primary key)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `phase_id` (ULID, FK → template_phases.id, cascade delete)
- `discipline_id` (ULID, FK → template_disciplines.id, cascade delete)
- `code` (string) - Task code (e.g., "ARC-C01")
- `name` (string) - Task name
- `description` (text, nullable) - Task description
- `est_duration_days` (integer, nullable) - Estimated duration in days
- `role_key` (string, nullable) - Role key for assignment (e.g., "lead_architect")
- `deliverable_type` (string, nullable) - Deliverable type (e.g., "layout_dwg")
- `order_index` (integer) - Order index for sorting
- `is_optional` (boolean) - Whether this task is optional
- `metadata` (json, nullable) - Additional metadata
- `created_at`, `updated_at` (timestamps)

**Indexes:** `set_id`, `code`, `phase_id`, `discipline_id`, `order_index`

#### `template_task_dependencies`
Dependencies between template tasks.

- `id` (ULID, primary key)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `task_id` (ULID, FK → template_tasks.id, cascade delete)
- `depends_on_task_id` (ULID, FK → template_tasks.id, cascade delete)

**Indexes:**
- Unique: `(task_id, depends_on_task_id)`
- Indexes: `set_id`, `task_id`, `depends_on_task_id`

#### `template_presets`
Preset filter configurations for template sets.

- `id` (ULID, primary key)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `code` (string) - Preset code (e.g., "HOUSE")
- `name` (string) - Preset name
- `description` (text, nullable) - Preset description
- `filters` (json) - Filter configuration
- `created_at`, `updated_at` (timestamps)

**Indexes:** `set_id`, `code`, `name`

**Filter Structure:**
```json
{
  "phases": ["CONCEPT", "DESIGN"],
  "disciplines": ["ARC", "MEP"],
  "tasks": ["ARC-C01"],
  "include": [],
  "exclude": ["LND-PANO"]
}
```

#### `template_apply_logs`
Log of template applications to projects.

- `id` (ULID, primary key)
- `project_id` (ULID, FK → projects.id, cascade delete)
- `tenant_id` (ULID, FK → tenants.id, cascade delete)
- `set_id` (ULID, FK → template_sets.id, cascade delete)
- `preset_code` (string, nullable) - Preset code used (if any)
- `selections` (json) - Selections made (phases, disciplines, tasks)
- `counts` (json) - Summary counts (tasks_created, dependencies_created, etc.)
- `executor_id` (ULID, FK → users.id, restrict delete)
- `duration_ms` (integer) - Duration in milliseconds
- `created_at` (timestamp) - Only created_at, no updated_at

**Indexes:** `project_id`, `tenant_id`, `set_id`, `created_at`

## Models

### TemplateSet
- **Path:** `app/Models/TemplateSet.php`
- **Traits:** `HasUlids`, `HasFactory`, `BelongsToTenant`, `SoftDeletes`
- **Relationships:**
  - `phases()` - HasMany TemplatePhase
  - `disciplines()` - HasMany TemplateDiscipline
  - `tasks()` - HasMany TemplateTask
  - `presets()` - HasMany TemplatePreset
  - `applyLogs()` - HasMany TemplateApplyLog
  - `creator()` - BelongsTo User
- **Scopes:**
  - `active()` - Only active template sets
  - `forTenantOrGlobal(string $tenantId)` - Template sets for tenant or global

### TemplatePhase
- **Path:** `app/Models/TemplatePhase.php`
- **Relationships:**
  - `set()` - BelongsTo TemplateSet
  - `tasks()` - HasMany TemplateTask
- **Scopes:**
  - `ordered()` - Order by order_index

### TemplateDiscipline
- **Path:** `app/Models/TemplateDiscipline.php`
- **Relationships:**
  - `set()` - BelongsTo TemplateSet
  - `tasks()` - HasMany TemplateTask

### TemplateTask
- **Path:** `app/Models/TemplateTask.php`
- **Note:** Distinct from existing `App\Models\TemplateTask` (for ProjectTemplate)
- **Relationships:**
  - `set()` - BelongsTo TemplateSet
  - `phase()` - BelongsTo TemplatePhase
  - `discipline()` - BelongsTo TemplateDiscipline
  - `dependencies()` - HasMany TemplateTaskDependency
  - `dependents()` - HasMany TemplateTaskDependency (reverse)
- **Methods:**
  - `getDependencyTasks()` - Get all tasks this depends on

### TemplateTaskDependency
- **Path:** `app/Models/TemplateTaskDependency.php`
- **Relationships:**
  - `set()` - BelongsTo TemplateSet
  - `task()` - BelongsTo TemplateTask
  - `dependsOn()` - BelongsTo TemplateTask

### TemplatePreset
- **Path:** `app/Models/TemplatePreset.php`
- **Relationships:**
  - `set()` - BelongsTo TemplateSet
- **Methods:**
  - `matches(array $selection)` - Check if preset filters match selection

### TemplateApplyLog
- **Path:** `app/Models/TemplateApplyLog.php`
- **Relationships:**
  - `project()` - BelongsTo Project
  - `set()` - BelongsTo TemplateSet
  - `executor()` - BelongsTo User
  - `tenant()` - BelongsTo Tenant

## Policies

### TemplateSetPolicy
- **Path:** `app/Policies/TemplateSetPolicy.php`
- **Abilities:**
  - `viewAny` - View all template sets (super-admin sees all, others see tenant + global)
  - `view` - View specific template set
  - `create` - Create template sets (super-admin only)
  - `update` - Update template sets (super-admin only)
  - `delete` - Delete template sets (super-admin only)
  - `import` - Import template sets (super-admin only)
  - `apply` - Apply template to project (tenant users can apply)
  - `publish` - Publish new version (super-admin only)
  - `export` - Export template sets

**Rules:**
- Only super-admin/system roles can manage template sets
- Tenant isolation: user `tenant_id` must match when using tenant-scoped sets
- Global templates (tenant_id = null) accessible to all tenants but only manageable by super-admin
- `apply` allowed for tenant users (they can use templates)

## Services

### TemplateImportService
- **Path:** `app/Services/TemplateImportService.php`
- **Responsibilities:**
  - Parse CSV/XLSX/JSON files
  - Validate schema (headers, required fields, data types)
  - Normalize codes (uppercase, spaces/hyphens to underscores)
  - Check uniqueness (task_code within set)
  - Transform to model structure
  - Persist in transaction (TemplateSet → Phases → Disciplines → Tasks → Dependencies → Presets)

**Methods:**
- `importFromFile(UploadedFile $file, User $user, ?string $tenantId): TemplateSet`
- `importFromJson(array $payload, User $user, ?string $tenantId): TemplateSet`
- `validateCsvHeaders(array $headers): bool`
- `normalizeCode(string $code): string`

**Error Handling:**
- Returns structured errors with line numbers for CSV
- Returns field paths for JSON validation errors

### TemplateApplyService
- **Path:** `app/Services/TemplateApplyService.php`
- **Responsibilities:**
  - Preview template application (counts, duration estimates)
  - Apply template to project (create tasks, dependencies, mappings)
  - Handle conflicts (skip/rename/merge)
  - Map phases to Kanban columns
  - Map disciplines to tags/labels
  - Map roles to assignees
  - Create deliverable folders
  - Log application

**Methods:**
- `preview(Project $project, TemplateSet $set, ?string $presetCode, array $selections, array $options): array`
  - Returns: `['total_tasks', 'total_dependencies', 'estimated_duration', 'breakdown' => ['phase' => count, 'discipline' => count]]`
- `apply(Project $project, TemplateSet $set, ?string $presetCode, array $selections, array $options, User $executor): array`
  - Returns: `['tasks_created', 'dependencies_created', 'warnings', 'errors']`

**Implementation Details:**
- Resolve selections: Filter by phases/disciplines/tasks + preset filters (include/exclude)
- Dependency resolution: Build graph, topological sort
- Chunked inserts: 100-500 rows per chunk using `insert()` or `upsert()`
- Per-phase transactions: Wrap each phase's tasks in `DB::transaction()`
- Phase → Kanban: Maps to `Task.status` (can be enhanced with board_columns model)
- Discipline → Tags: Adds to `Task.tags` JSON with discipline code and color_hex
- Role → Assignee: Uses existing `ProjectAssignmentService` if available, else skips gracefully
- Deliverable folders: Creates `/storage/projects/{project_id}/deliverables/{phase_code}/{discipline_code}/` with safe directory creation
- Logging: Creates `TemplateApplyLog` with selections, counts, duration
- Optional: Emits `TemplateApplied` event if event system exists

## API Endpoints

### Admin Routes (Blade)
**Base Path:** `/admin/templates`

- `GET /` - List template sets (`Admin\TemplateSetController@index`)
- `GET /{set}` - Show template set (`Admin\TemplateSetController@show`)
- `POST /` - Create template set (`Admin\TemplateSetController@store`)
- `PUT /{set}` - Update template set (`Admin\TemplateSetController@update`)
- `DELETE /{set}` - Delete template set (`Admin\TemplateSetController@destroy`)
- `POST /import` - Import template from file (`Admin\TemplateSetController@import`)

**Middleware:** `web`, `auth:web`, `AdminOnlyMiddleware`

### App API Routes (JSON)
**Base Path:** `/api/v1/app`

- `GET /template-sets` - List available template sets (`Api\TemplateController@index`)
- `POST /template-sets/preview` - Preview template application (`Api\TemplateController@preview`)
- `POST /projects/{project}/apply-template` - Apply template to project (`Api\TemplateController@apply`)
- `GET /projects/{project}/template-history` - Get template application history (`Api\TemplateController@history`)

**Middleware:** `auth:sanctum`, `ability:tenant`

### Request/Response Examples

#### GET /api/v1/app/template-sets
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "01HZ...",
      "code": "WBS-AEC-INTL",
      "name": "WBS AEC International",
      "description": "Standard WBS template",
      "version": "2025.1",
      "is_global": true,
      "phases": [...],
      "disciplines": [...],
      "presets": [...]
    }
  ],
  "message": "Template sets retrieved successfully"
}
```

#### POST /api/v1/app/template-sets/preview
**Request:**
```json
{
  "set_id": "01HZ...",
  "project_id": "01HZ...",
  "preset_code": "HIGH_RISE",
  "selections": {
    "phases": ["CONCEPT", "DESIGN"],
    "disciplines": ["ARC", "MEP"]
  },
  "options": {
    "map_phase_to_kanban": true,
    "auto_assign_by_role": true,
    "create_deliverable_folders": true
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_tasks": 15,
    "total_dependencies": 8,
    "estimated_duration": 25,
    "breakdown": {
      "phase": {
        "CONCEPT": 5,
        "DESIGN": 10
      },
      "discipline": {
        "ARC": 8,
        "MEP": 7
      }
    }
  }
}
```

#### POST /api/v1/app/projects/{project}/apply-template
**Request:**
```json
{
  "set_id": "01HZ...",
  "preset_code": "HIGH_RISE",
  "selections": {
    "phases": ["CONCEPT", "DESIGN"]
  },
  "options": {
    "conflict_behavior": "skip",
    "map_phase_to_kanban": true,
    "auto_assign_by_role": true,
    "create_deliverable_folders": true
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "tasks_created": 15,
    "dependencies_created": 8,
    "warnings": [],
    "errors": []
  }
}
```

## Import/Export

### JSON Schema
**File:** `resources/templates/schema.template.json`

The JSON schema defines the structure for template sets:
- `set` - Template set metadata
- `phases` - Array of phases
- `disciplines` - Array of disciplines
- `tasks` - Array of tasks with dependencies
- `presets` - Array of preset configurations

### CSV Format
**File:** `resources/templates/sample.aec-intl.csv`

CSV headers:
- `phase_code`, `phase_name`
- `discipline_code`, `discipline_name`, `color_hex`
- `task_code`, `task_name`, `description`
- `est_duration_days`, `role_key`, `deliverable_type`
- `order_index`, `is_optional`
- `depends_on_codes` - Pipe-separated (e.g., `ARC-C01|ARC-C05`)

### Sample Files
- `resources/templates/sample.aec-intl.json` - Sample JSON template
- `resources/templates/sample.aec-intl.csv` - Sample CSV template

## Import Flow

1. Admin uploads CSV/JSON file via `/admin/templates/import`
2. `TemplateImportService` parses and validates file
3. Service normalizes codes and checks uniqueness
4. Service creates TemplateSet with phases, disciplines, tasks, dependencies, presets in transaction
5. Returns created TemplateSet with relationships loaded

## Apply Flow

1. User creates project (via project creation wizard)
2. User optionally selects template set
3. User selects preset/phases/disciplines/tasks
4. User clicks "Preview" → calls `POST /api/v1/app/template-sets/preview`
5. User reviews preview statistics
6. User clicks "Apply" → calls `POST /api/v1/app/projects/{project}/apply-template`
7. `TemplateApplyService`:
   - Resolves selections and preset filters
   - Builds dependency graph and topological sort
   - Creates tasks in chunks (per phase, in transactions)
   - Maps phases to status, disciplines to tags, roles to assignees
   - Creates deliverable folders (if option enabled)
   - Creates dependencies in `task_dependencies` table
   - Logs application in `template_apply_logs`
8. Returns results with counts and any warnings/errors

## Feature Flag

**Configuration:** `config/features.php`
```php
'tasks' => [
    'enable_wbs_templates' => env('FEATURE_TASK_TEMPLATES', false),
],
```

**Environment Variable:** `FEATURE_TASK_TEMPLATES=true`

**Checks:**
- Controllers check feature flag before processing requests
- UI gracefully handles disabled feature (hides template step if feature disabled)

## Seeder

**File:** `database/seeders/AecIntl2025Seeder.php`

Creates sample template set with:
- Phases: CONCEPT, DESIGN, CONSTRUCTION, QC
- Disciplines: ARC, MEP, STR, LND
- Tasks: Sample tasks for each phase/discipline combination
- Dependencies: Sample dependency chains
- Presets: "High-rise", "Townhouse", "Commercial"

**Usage:**
```bash
php artisan db:seed --class=AecIntl2025Seeder
```

## Definition of Done

- [x] Import CSV/JSON successfully creates a `TemplateSet` with phases/disciplines/tasks/dependencies/presets
- [x] Project creation wizard can preview and apply templates with combinations of preset/phase/discipline/task selections
- [x] Resulting tasks show correct: Kanban columns (phase-mapped), Discipline labels/colors, Role-based assignees (as far as current role mapping permits), Task dependencies, Deliverable folder structure
- [ ] All Unit/Feature/E2E tests pass
- [ ] Coverage ≥ 80% for `TemplateImportService` and `TemplateApplyService`
- [x] Migrations are safe and reversible, no existing functionality breaks
- [x] Tenant isolation verified (tenant A cannot access tenant B's templates)
- [x] Global templates accessible to all tenants but only manageable by super-admin
- [x] Feature flag works (feature can be disabled)
- [x] Documentation complete and referenced in `DOCUMENTATION_INDEX.md`

## Risk Mitigation

1. **Naming Conflict**: Clear docblocks and namespace usage distinguish new `TemplateTask` from existing one
2. **Global Template Scope**: `forTenantOrGlobal()` scope handles null tenant_id correctly
3. **Performance**: Chunked inserts and per-phase transactions avoid long-running locks
4. **Integration**: React wizard integration with existing `CreateProjectPage.tsx` follows existing patterns
5. **Tenant Isolation**: Explicit policy checks ensure tenant A cannot read B's templates

