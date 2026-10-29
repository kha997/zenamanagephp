---
work_id: GAP-030
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-030/02-design.md
---

# GAP-030 — RFI escalation resolve permission: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-030/02-design.md`).

1. **Red:** `RfiApiTest` target test grants `rfi.resolve-escalation` instead of `rfi.escalate` (403 before); new test: a
   user holding only `rfi.escalate` cannot resolve; seeder test: a role named `Project Manager` gets the RFI grants and
   `Project Member` gets `rfi.resolve-escalation`.
2. `ZenaPermissionsSeeder`: add `rfi.resolve-escalation`.
3. `routes/api_zena.php`: `rfis.resolve-escalation` uses `rbac:rfi.resolve-escalation`.
4. `ZenaProjectManagerRolePermissionSeeder`: match `project_manager` or `project manager`; add the new code for PM; grant
   it to `Project Member`.
5. Verify (Gate 3 list in the design), then the Gate 3 packet.
