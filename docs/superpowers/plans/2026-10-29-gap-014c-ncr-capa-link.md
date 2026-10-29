---
work_id: GAP-014c
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-014c/02-design.md
---

# GAP-014c — Durable NCR ↔ CAPA task link: implementation plan

Executes approved Gate 2 Option B (`docs/owner-decisions/GAP-014c/02-design.md`).

1. **Red:** add `tests/Feature/NcrCapaTaskLinkTest.php` (create CAPA task from an NCR via API and web, `capa_tasks` on the
   NCR, `ncr_id` accepted/validated on `POST /api/zena/tasks`, closed NCR and missing permission refused, migration down).
2. Migration: nullable `tasks.ncr_id` (ULID, index, FK to `ncrs.id` on delete set null); `down()` drops FK, index, column.
3. `Task::ncr()`, `Ncr::capaTasks()`; `ncr_id` fillable.
4. API: `InspectionController::storeNcrCapaTask` (route `inspections.ncrs.capa-tasks.store`, `rbac:inspection.edit` +
   `rbac:task.create`), `capa_tasks` and `task_handoff.payload.ncr_id` in `transformNcr`; `TaskController::store` accepts
   `ncr_id` (same tenant and project) and task payloads return it.
5. Web: `InspectionPageController::storeNcrCapaTask` + route; `ncr-show` lists CAPA tasks and shows the button.
6. Verify (Gate 3 list in the design), then the Gate 3 packet.
