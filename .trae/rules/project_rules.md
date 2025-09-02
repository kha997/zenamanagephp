# Z.E.N.A Project - Project Rules (v2.1) for TRAE.AI

This document outlines the core architectural decisions, coding standards, and business logic for the Z.E.N.A Project Management Web Application. TRAE must follow these rules strictly when generating any code, database schema, or architectural suggestions. This version supersedes all previous rule sets.

---

## 1. Core Technology Stack & Coding Standards

- **Language**: **PHP 8.0**. Every PHP file **must** start with `<?php declare(strict_types=1);`.
- **Framework**: **Laravel 10.x**. All development should leverage Laravel's features (Eloquent ORM, Artisan, Blade, Event System, etc.).
- **Database**: **MySQL 8.0+**.
    - **Engine**: `InnoDB` for all tables.
    - **Charset**: `utf8mb4_unicode_ci` for full Unicode support.
- **Coding Style**: Adhere strictly to the **PSR-12** (Extended Coding Style) standard.
- **Dependency Management**: Use **Composer** for all PHP dependencies.
- **Documentation**: All classes, methods, and functions **must** have **PHPDoc blocks** detailing their purpose, parameters (`@param`), and return values (`@return`).
- **Naming Conventions**:
    - **Classes**: `PascalCase` (e.g., `ProjectController`, `UserService`).
    - **Methods, Functions, Variables**: `camelCase` (e.g., `calculateProjectProgress`).
    - **Database Tables**: `snake_case`, plural (e.g., `projects`, `interaction_logs`).
    - **Database Columns**: `snake_case`, singular (e.g., `project_id`, `created_at`).

---

## 2. Database Schema & Architecture

When generating migrations and models, adhere precisely to the following structure. Primary keys are `id` (`BIGINT UNSIGNED AUTO_INCREMENT`). All tables must include `created_at` and `updated_at` timestamps.

### 2.1. Roles & Permissions (RBAC)
- **`roles`**: `id`, `name`, `scope` (ENUM: 'system', 'custom', 'project'), `description`.
- **`permissions`**: `id`, `code` (e.g., `task.create`), `module`, `action`, `description`.
- **`role_permissions`**: `id`, `role_id`, `permission_id`, `allow_override` (BOOLEAN, default 0).
- **`users`**: `id`, `name`, `email`, `password`, `tenant_id` (FK).
- **`system_user_roles`**: `user_id`, `role_id` (Many-to-many pivot).
- **`project_user_roles`**: `project_id`, `user_id`, `role_id` (Many-to-many pivot).
- **Logic**:
    - **Priority**: `Project-Specific` > `Custom` > `System-Wide`.
    - **Conflict Resolution**: Apply the **least privilege principle**. If conflicting permissions exist, `deny` takes precedence unless a specific `allow_override=true` is set on the `role_permissions` link.

### 2.2. Core Project Structure
- **`projects`**: `id`, `tenant_id`, `name`, `description`, `start_date`, `end_date`, `status`, `progress`, `actual_cost`.
- **`components`**: `id`, `project_id`, `parent_component_id` (NULLABLE, self-referencing for nesting), `name`, `progress_percent`, `planned_cost`, `actual_cost`.
- **`work_templates`**: `id`, `name`, `category` (ENUM: 'design','construction','qc','inspection'), `template_data` (JSON), `version`.
- **`tasks`**: `id`, `project_id`, `component_id` (NULLABLE), `phase_id` (NULLABLE), `name`, `start_date`, `end_date`, `status`, `dependencies` (JSON array of task_ids), `conditional_tag` (VARCHAR), `is_hidden` (BOOLEAN, default 0).
- **`task_assignments`**: `id`, `task_id`, `user_id`, `split_percentage` (DECIMAL).

### 2.3. Feature-Specific Schemas
- **`interaction_logs`**: `id`, `project_id`, `linked_task_id` (NULLABLE), `type` (ENUM: 'call','email','meeting','note','feedback'), `description` (TEXT), `tag_path` (VARCHAR, e.g., "Material/Flooring/Granite"), `visibility` (ENUM: 'internal','client'), `client_approved` (BOOLEAN, default 0), `created_by`, `created_at`.
- **`baselines`**: `id`, `project_id`, `type` (ENUM: 'contract','execution'), `start_date`, `end_date`, `cost` (DECIMAL), `version` (INT, default 1), `note` (TEXT), `created_by`.
- **`documents`**: `id`, `project_id`, `title`, `linked_entity_type` (ENUM: 'task','diary','cr'), `linked_entity_id`, `current_version_id`.
- **`document_versions`**: `id`, `document_id`, `version_number`, `file_path`, `storage_driver` (ENUM: 'local','s3','gdrive'), `comment` (TEXT), `created_by`, `reverted_from_version_number` (INT, NULLABLE).
- **`change_requests`**: `id`, `project_id`, `code`, `title`, `description`, `status` (ENUM: 'draft','awaiting_approval','approved','rejected'), `impact_days` (INT), `impact_cost` (DECIMAL), `impact_kpi` (JSON), `created_by`, `decided_by` (NULLABLE), `decided_at` (NULLABLE), `decision_note`.
- **`notifications`**: `id`, `user_id`, `priority` (ENUM: 'critical','normal','low'), `title`, `body`, `link_url`, `channel` (ENUM: 'inapp','email','webhook'), `read_at` (TIMESTAMP, NULLABLE).
- **`notification_rules`**: `id`, `user_id`, `project_id` (NULLABLE, for project-specific rules), `event_key` (VARCHAR), `min_priority`, `channels` (JSON), `is_enabled` (BOOLEAN).

---

## 3. Business Logic & Implementation Rules

### 3.1. Work Templates & Tasks
- When a project is created from a template, tasks from `template_data` are deep-cloned into the `tasks` table.
- **Conditional Tags**: If a task's `conditional_tag` is not active for the project, its `is_hidden` flag must be set to `1`. This task is excluded from calculations and views but not deleted.

### 3.2. Project Progress & Cost Calculation
- **Primary Mechanism: Event-driven**. When a component's `actual_cost` or `progress_percent` is updated, an event (e.g., `ComponentProgressUpdated`) **must** be dispatched. A listener will then re-calculate and update the parent `project`'s aggregated values.
- The `project.progress` is a weighted average of its components' progress, using `planned_cost` as the weight.
- `project.actual_cost` is the SUM of all its root `components.actual_cost`.

### 3.3. Document Versioning
- Reverting to an old version **must not** delete data. It **must** create a *new* version that is a copy of the target old version, and the `reverted_from_version_number` field should be populated.

### 3.4. Change Request (CR) Workflow
- The CR module is a state machine: `Draft` → `Awaiting Approval` → `Approved`/`Rejected`.
- Upon approval, the CR module **MUST NOT** directly modify other modules' data. It **MUST** dispatch a `ChangeRequestApproved` event containing the impact data (`impact_days`, `impact_cost`, etc.).
- Other modules (e.g., Scheduling, Costing) **must** have listeners for this event to apply the changes. This ensures loose coupling.

### 3.5. Interaction Log Visibility
- An `interaction_log` with `visibility = 'client'` **must** have `client_approved = 1` to be visible to clients. This requires an explicit approval step in the UI/backend.

---

## 4. API & Event Bus Architecture

### 4.1. API Standards
- **Style**: Strictly RESTful. All endpoints must be prefixed with `/api/v1/`.
- **Authentication**: **JWT (JSON Web Tokens)**. The JWT payload **must** include `user_id`, `tenant_id`, and a list of system-level role codes.
- **Responses**: Use the **JSend** specification for all JSON responses.
    - Success: `{ "status": "success", "data": { ... } }`
    - Error: `{ "status": "error", "message": "Descriptive error message." }`
- **HTTP Status Codes**: Use standard codes correctly (400, 401, 403, 404, 500).

### 4.2. Event Bus (Laravel Events)
This is a critical part of the architecture for inter-module communication.
- **Implementation**: Use Laravel's built-in Event/Listener system.
- **Naming Convention**: `Domain.Entity.Action` (e.g., `Project.Component.ProgressUpdated`, `Document.Version.Reverted`, `ChangeRequest.Approved`).
- **Payload**: Event payloads should be Data Transfer Objects (DTOs) and must contain at a minimum: `{ entityId, projectId, actorId, changedFields, timestamp }`.
- **Auditing**: An `EventLog` listener **must** subscribe to all events (`*`) and persist them to an `event_logs` table for auditing and debugging.
- **Notifications**: The Notification module will have listeners for various business events to trigger notifications based on user-defined `notification_rules`.
