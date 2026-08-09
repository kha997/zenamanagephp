---
work_id: OWN-2026-006
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation-design.md
  plan: docs/superpowers/plans/2026-08-09-own-2026-006-multi-work-gate3-digest-isolation.md
  branch: fix/OWN-2026-006-multi-work-gate3-digest-isolation
  pr: https://github.com/kha997/zenamanagephp/pull/254
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-09T15:24:00+07:00"
  owner_response_reference: "ChatGPT project conversation — OWNER DECISION — OWN-2026-006 IMPLEMENTATION ACCEPTED / PREPARE FORMAL GATE 3 PACKET; the Owner explicitly re-confirmed Gate 2 APPROVED on 2026-08-09 at 15:24 +07:00."
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-09T12:00:00+07:00"
  updated_at: "2026-08-09T15:24:00+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

For a target work ID, the active Gate 3 packet is selected by the existing version-aware resolver and excluded. Superseded Gate 3 packets for that same work item remain included and digest-sensitive.

All files matching `docs/owner-decisions/<OTHER_WORK_ID>/03-release.md` or `03-release-vN.md` are excluded from the target work item's digest. No other path is newly excluded.

The implementation must be a minimal change to `owner_governance_compute_implementation_tree_digest()`, preceded by a failing temporary-Git-repository regression and followed by the complete matrix in the governed spec. Packet schema, freshness enforcement, Work-ID extraction, and application behavior remain unchanged.

No OWN-2026-006 Gate 3 decision has started or been authorized.
