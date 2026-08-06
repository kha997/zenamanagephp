---
work_id: GAP-031
gate: 2
gate_status: approved
owner_decision: {value: approved, authority: human_owner}
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
---

`owner_decision.value: approved` but `decision_provenance.recorded_by`/`recorded_at` are both null — an "approved" record must carry honest, non-null provenance attribution (who/when it was recorded), even at `trust_level: claimed_repo_record`.
