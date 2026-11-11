---
work_id: GAP-123
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-123/02-design.md
---

# GAP-123 — Authoring work templates in the web app: implementation plan

Executes Gate 2 E-1, P-1, S-1 (`docs/owner-decisions/GAP-123/02-design.md`). Only files in the packet's allowlist change.

1. **Red test** `tests/Feature/WorkTemplates/WorkTemplateAuthoringPageTest.php`:
   - create → draft → publish → listed for apply → apply creates the tasks (names, order, dependencies, deadlines,
     checklist, docs);
   - editing a published template opens a new draft;
   - duplicate keeps steps and custom fields;
   - validation (name, duplicate name, cycle, days);
   - PM 403 on edit but sees the list;
   - tenant isolation;
   - Vietnamese seeder idempotent and complete.
2. **`WorkTemplateEditorService`:**
   - form rows → API `steps[]` (keys, order, depends_on, sla_hours = days × 24, config, preserved fields);
   - validation;
   - preview rows;
   - duplicate.
3. **`WorkTemplatePageController`** and routes `app.work-templates.*`. It reuses `WorkTemplateCrudService` and the audit
   logger.
4. **Views:** index, create, edit (steps table + 3 blank rows, preview).
5. **Menu item** "Mẫu công việc".
6. **`WorkTemplateVietnameseBaselineSeeder`,** wired into `production:rbac` and `DatabaseSeeder`.
7. **Green;** existing template tests; full suite; lints.
8. **Demo:** create → publish → apply on the demo app.
9. **Gate 3 packet.**
