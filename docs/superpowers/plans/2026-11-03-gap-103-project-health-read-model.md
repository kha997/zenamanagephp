---
work_id: GAP-103
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-103/02-design.md
---

# GAP-103 — Shared Project Health Read Model: implementation plan

Executes Gate 2 P-A, C-1, X-1 (`docs/owner-decisions/GAP-103/02-design.md`).

1. **Red:** `tests/Feature/ProjectHealth/ProjectHealthReadServiceTest.php` fails (service missing; project page shows 0%).
2. **Green — service:** `app/Services/ProjectHealth/ProjectHealthReadService::forProject()` with progress (P-A), schedule
   (`ProjectDelayStatus` + milestones), attention, finance; each measure carries availability; aggregate queries only;
   tenant- and project-scoped.
3. **Green — page (C-1):** `Web\ProjectController::show` passes `health`; `projects/show.blade.php` "Tiến độ" field shows
   the read-model value or "Chưa có dữ liệu" with its basis label.
4. **Verify:** query count equal for 1 and 50 tasks; PHPStan, php-cs-fixer, SSOT lint, route guard, deptrac; Unit + Feature +
   Integration and `tests/Feature/Api`.
5. **Gate 3:** release packet with subject and implementation-tree digest.
