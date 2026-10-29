---
work_id: GAP-015
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-015/02-design.md
---

# GAP-015 — WorkInstance execution screens: implementation plan

Executes approved Gate 2 Option A and its supplement (`docs/owner-decisions/GAP-015/02-design.md`).

1. **Red:** `tests/Feature/Zena/WorkInstancePagesTest.php` and the seeder-test expectations fail (routes, pages and grants
   missing).
2. Routes and `WorkInstancePageController` (read-only list/detail; mutations delegate to `WorkInstanceController`).
3. Views `work-instances/index` and `work-instances/show`; link on the project page.
4. Seeder grants for PM and Project Member.
5. Verify (Gate 3 list), then the Gate 3 packet.
