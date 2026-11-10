# GAP-123 — Authoring work templates in the web app: discovery evidence

Date: 2026-11-11. Base: `main` at `9f98b2082e2f229c7d7f82761ae6b4538369e9b1` (after GAP-122).

## 1. What a work template is today

- **Model.**
  - `WorkTemplate` (code, name, description, status) holds versions in `WorkTemplateVersion`.
  - Each version has a semver, `schema_version`, `content_json`, `is_immutable` and `published_at`.
  - It is structured into `WorkTemplateStep` with fields; v2 adds `WorkTemplatePhase`, tasks, checklist items, required
    documents, assignments and triggers.
- **Applying a template** (`Api\WorkTemplateController::applyToProject`, used by the project page "Áp dụng mẫu công
  việc", GAP-015 / GAP-021) creates one `WorkInstance` with:
  - one `WorkInstanceStep` per step;
  - one project **task per step**, carrying:
    - name = step name;
    - description and priority from `config`;
    - start and end dates from the SLA hours;
    - dependencies from `depends_on`;
    - assignee from `assignee_rule` (only `role: project_manager` is resolved);
  - checklist items and required documents per task, shown on the work instance.
- **Versioning already works** (`WorkTemplateCrudService`):
  - an update writes to the open draft, or creates a draft from the latest published version;
  - publish freezes the draft as an immutable version;
  - instances already created keep their version.

## 2. How templates are authored today

### F-1 — There is no web page to create or edit a template

- **Web routes** (`routes/web.php`): only the project-side list / preview / apply
  (`app.projects.work-templates.*`).
- **The old `/templates*` web pages were removed** on 22/07: a dead demo, per the comment in `routes/web.php`.
- **Authoring exists only as JSON API** in `routes/api_zena.php` (`work-templates` index / store / show / preview /
  update / publish / destroy).
  - The API expects a `steps[]` array with keys, types, order, `depends_on`, `assignee_rule`, SLA, fields and `config`
    (description, priority, phase, checklist items, required docs).
  - No one uses this API from a screen.
- **Permissions:** `template.edit_draft`, `template.publish` and `template.delete` are admin-only
  (`ZenaPermissionsSeeder`, `ZenaAdminRolePermissionSeeder`). PMs have `template.view` and `template.apply`.

### F-2 — The only templates are three English baselines

`production:rbac` / `DatabaseSeeder` runs `WorkTemplateBaselineSeeder`. Each company gets three templates:

| Template | Example steps |
|---|---|
| `WT-BL-DESIGN` "Design" | "Capture Design Inputs", "Internal Design Review" |
| `WT-BL-CONSTRUCTION` "Construction" | similar English steps |
| `WT-BL-INSPECTION` "Inspection" | similar English steps |

- Step names, checklist items and required documents are in English.
- Applying one to a project creates tasks with English names, e.g. "Develop and Coordinate Design Package".

### F-3 — A company cannot capture its own way of working

- A company that repeats the same sequence on every house cannot save it as a template without a developer. Example
  sequence: khảo sát → thiết kế sơ bộ → báo giá → thi công móng → …
- It also cannot rename or translate the baseline steps.

## 3. What an authoring screen would need to cover

The minimal subset that drives what "apply" creates, per step:
- name;
- type (task / approval / deliverable);
- order;
- "after which step" (`depends_on`);
- duration (SLA hours or days);
- description;
- priority;
- phase name;
- checklist items;
- required documents.

The remaining v1 / v2 structures could stay API-only:
- custom fields and enum options;
- visibility rules;
- triggers;
- reviewer / watcher rules.

## 4. Open points for Gate 2

- **Scope of the editor:** the minimal step subset above, or more (custom fields).
- **Who may author:**
  - keep admin-only permissions;
  - or grant `template.edit_draft` / `template.publish` to the company Owner role.
- **Starting point:**
  - blank template;
  - duplicate an existing one;
  - translate the three baselines to Vietnamese.
- **Draft / publish flow on screen:** edit draft, preview, publish, archive.

## 5. Exclusions

- Changing what "apply" creates, work instances, the project-side apply page.
- Deliverable (document) templates.
- Deploy.
