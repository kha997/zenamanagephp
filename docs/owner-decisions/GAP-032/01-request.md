---
work_id: GAP-032
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-08-10-gap-032-status-semantics-evidence.md
  plan: null
  branch: docs/GAP-032-document-status-semantics
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-10T18:26:10+07:00"
  updated_at: "2026-08-10T18:26:10+07:00"
generated_by: agent
---

## Owner Summary

A single field, `Document.status`, is currently overloaded to carry two unrelated concepts at once: the generic status of a document (e.g. `active`, `review`) and the state of the approval workflow (`draft → submitted → approved/rejected`). Because both concepts share one column, the system has no single, agreed business definition of what "a document's status" actually means, and it is impossible to look at a stored value and know whether it represents a document state or a workflow state.

## Operational Problem

`submit()` only accepts documents whose status is `draft`, but new documents are created with the status `active` by the API (and `draft` by the web form), and any other status such as `review` or `published` can be set freely. So a document sitting at `active` or `review` has no path into the approval workflow — there is no transition that moves it in. Likewise, once a document reaches a workflow state (`submitted`/`approved`/`rejected`), a generic update silently refuses to change its status at all, while a document still in a legacy state can be rewritten freely. The result is one field with two lifecycles bolted together:

- It is unclear whether a document is "in the workflow" or just carrying a free-text status.
- Valid transitions cannot be stated uniformly, because the same column holds two different state sets.
- API clients and integrations that depend on current legacy values (`active`/`review`) would break if the contract changes.
- Adding a second approval flow later risks creating further collisions, since all flows would have to fight over the same single column.

Concrete grounding (file-level detail lives in the evidence file): the column is a plain string with default `active`, the model does not type it as an enum, the workflow service is the only writer of the three workflow-only values, and the legacy generic values are still accepted by the create/update paths. The acute bypass (writing workflow values directly without the service) was already closed by GAP-031; what remains unresolved is exactly the meaning and coexistence of the legacy values.

## Who is affected

Only groups with repository evidence of interaction with documents:

- **Document creators / editors** — anyone with `document.create`/`document.update` can set `active`/`review`/any free-text status on a document, with no workflow meaning.
- **Document submitters** — submitter cannot move an `active`/`review` document into approval (no transition exists; `submit()` requires `draft`).
- **Approvers** — can decide only documents at `submitted`; cannot reason about documents stuck at legacy statuses.
- **Document list viewers** — the list and approvals views and the API both read `status` for filtering and rendering, so the overloaded column is surfaced directly to readers.
- **API / integration clients** — receive `data.status` and `data.metadata.status` as an unconstrained string; changing the contract is a breaking change for them.
- **Today Workspace** — is currently blocked from including documents in "Action Required" (per the Today Workspace spec §7); resolving GAP-032 is a precondition for that surface, not a current dependency.

## Evidence

Engineering evidence file: `docs/audits/2026-08-10-gap-032-status-semantics-evidence.md` (persisted on this same branch; not queried from production). It records the persistence contract, every write path, every read path, the concrete status values present in the repo, the GAP-031 boundary, and the GAP-033 dependency.

## Business decisions required later (Gate 2 — not decided here)

Gate 1 does **not** choose a solution. It only asks permission to proceed to Gate 2, where the Owner must answer:

1. Does `Document.status` represent a single lifecycle, or multiple dimensions (generic + workflow)?
2. Are legacy values such as `active`/`review` still valid business statuses, or should they be normalized into the workflow?
3. Must a legacy-status document pass through a re-entry step (e.g. `active → draft`) before it can enter approval?
4. Which backward-compatibility behaviors for API clients must be preserved?
5. How must legacy data be handled — preserved, migrated, or rejected?

## Explicit exclusions

This Gate 1 request covers discovery and the Owner decision only. The following are explicitly **out of scope** for this work item and are not produced here:

- Any implementation, migration, or data migration.
- Any new status enum, database column, or database constraint.
- Any workflow transition, compatibility adapter, or state-machine redesign.
- Any API contract, controller, service, route, test, or seed change.
- Any change to `OPERATIONAL_GAP_REGISTER.md` (the register stays: GAP-032 = OPEN, GAP-033 = OPEN).
- GAP-033 (designated per-document approver) — separate work item; not started here.
- Today Workspace implementation — blocked by both GAP-032 and GAP-033.
- Any inspection of production database contents (not queried).

## What the owner is NOT being asked to decide

The Owner is **not** being asked to approve any implementation approach, code change, migration, enum, column, route, controller, service, or test. Not being asked to pick "two fields vs. one field" or "rename legacy values" or "migrate legacy data" — those are solution choices reserved for Gate 2 and beyond. Not being asked to decide anything about GAP-033 or Today Workspace. This step only asks: is this split of one overloaded field into a resolved business semantics worth designing (Gate 2).

## Decision Needed

The Owner chooses one: **Approve** to proceed to Gate 2 design / **Request changes** (with clarification) / **Decline**.

This request does not authorize implementation, merge, or release.
