---
work_id: GAP-117
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-117/02-design.md
---

# GAP-117 — Project milestones (mốc tiến độ): implementation plan

Executes Gate 2 L-1, E-1, A-1, T-1, O-1, C-2 (`docs/owner-decisions/GAP-117/02-design.md`). Only files in the packet's
allowlist change.

1. **Red:**
   - Add `tests/Feature/ProjectMilestones/ProjectMilestoneServiceTest.php` and `ProjectMilestonePageTest.php`.
   - Add `ProjectMilestone` to `AudTenantScopeCoverageTest`.
   - Confirm they fail: no service, no routes, no `tenant_id`.
2. **T-1 / O-1:**
   - Migration `2026_11_10_100000_add_tenant_id_to_project_milestones.php`:
     - nullable `tenant_id` and an index;
     - backfill from the project;
     - change `overdue` to `pending`.
   - Add an `expand` entry to `classifications.json`.
   - Model: add `TenantScope`, fill `tenant_id` on create, drop the stored-overdue hook, make `isOverdue()` live, make
     `markCompleted()` store a date.
3. **A-1 / E-1:** add `ProjectMilestoneService`.
   - Actions: create, update, complete, reopen, cancel, list.
   - Edit check: PM or `project.update` (403); the milestone must belong to this project and tenant (404).
   - Each action locks the row and writes an `AuditLog` entry with before / after values.
4. **L-1:**
   - `ProjectMilestonePageController` and the routes in `routes/web.php`.
   - View `projects/milestones.blade.php`.
   - A "Mốc tiến độ" button on the project page and a link in the OPPM milestone block.
5. **Green:**
   - The new tests pass.
   - Existing milestone readers stay green: OPPM / health, Today, analytics, PM dashboard.
   - Full Unit + Feature + Integration and Feature/Api suites.
   - The migration runs up / down / up on MySQL.
   - PHPStan, php-cs-fixer, `ssot:lint`, deptrac and governance lint are clean.
