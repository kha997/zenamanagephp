---
work_id: GAP-052
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-09-11-gap052-dashboard-widget-contract-design.md
  plan: docs/superpowers/plans/2026-09-11-gap052-dashboard-widget-provider-implementation.md
  branch: impl/GAP-052-dashboard-widget-provider
  pr: https://github.com/kha997/zenamanagephp/pull/311
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-11T21:55:15+07:00"
  owner_response_reference: "Owner decision: APPROVE GAP-052 Gate 2 Revision v2. Reviewed exact revision-v2 subject/head: 69ae4805eb09c13499dcc67f5394f10ec66bcae2."
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-052/02-design.md
superseded_by: null
timestamps:
  created_at: "2026-09-11"
  updated_at: "2026-09-11T21:55:15+07:00"
generated_by: agent
---

# GAP-052 — Gate 2 Clarification Revision 2

This narrow revision supersedes only the prior Gate-2 packet’s unresolved unknown-role policy and corrects the implementation-plan inventory/acceptance interpretation. The original retained routes, provider/resolver architecture, degraded-widget semantics, tenant/RBAC/project invariants, error-disclosure scope, and Gate-3 boundary remain approved and unchanged.

## Binding clarification

The locally handled provider inventory is exactly these 12 source switch codes:

`project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, `safety_summary`, `inspection_schedule`, `ncr_tracking`, `system_health`, and `user_management`.

Supported configured-code coverage is limited to:

- `system_admin`: `system_health`, `user_management`
- `project_manager`: `project_overview`, `task_progress`, `rfi_status`, `budget_tracking`, `schedule_timeline`, `team_performance`, `quality_metrics`, `safety_summary`
- `qc_inspector`: `inspection_schedule`, `ncr_tracking`, `quality_metrics`

`design_lead`, `site_engineer`, `client_rep`, and `subcontractor_lead` have no locally handled configured code under GAP-052. Their configured eligible widgets must still produce successful metadata-only responses and explicit per-widget degraded results with `DASHBOARD.WIDGET_UNSUPPORTED` when data is requested. No provider may be invented to make those roles appear supported.

## Unknown dashboard-role contract

If an authenticated user’s dashboard role is not exactly one of:

`system_admin`, `project_manager`, `design_lead`, `site_engineer`, `qc_inspector`, `client_rep`, `subcontractor_lead`

the dashboard request fails closed before catalog/provider execution with:

- HTTP status: `403`
- safe stable code: `DASHBOARD.ROLE_UNSUPPORTED`
- no fallback to `client_rep`
- no implicit aliases such as `admin`, `client`, or `designer`

Future role aliases require separate evidence and governance. This revision does not authorize implementation, merge, deployment, or Gate-3 release approval.
