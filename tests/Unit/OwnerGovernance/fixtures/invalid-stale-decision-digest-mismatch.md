---
work_id: GAP-031
gate: 3
gate_status: approved
technical_readiness: {value: ready, generated_by: engineering_evidence}
owner_decision: {value: approved, authority: human_owner}
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: "owner (session X)", recorded_at: "2026-08-04T11:00:00+07:00", owner_response_reference: "conversation X", reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T12:00:00+07:00"}
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "30/30 checks passed at decision time"
technical_evidence:
  subject_sha: "cccc111111111111111111111111111111cccc"
  implementation_tree_digest: "digest-after-a-later-commit-changed-the-implementation"
  verified_pr_head_sha: "cccc111111111111111111111111111111cccc"
  verified_at: "2026-08-04T12:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "digest-at-the-moment-the-owner-actually-decided"
  decision_recorded_at: "2026-08-04T11:00:00+07:00"
---

The owner approved this packet when the implementation tree digest was `digest-at-the-moment-the-owner-actually-decided`. A later, non-packet-only commit changed the real implementation, so `technical_evidence.implementation_tree_digest` moved on — but `owner_decision_binding.implementation_tree_digest` still points at the old value. This is exactly the "implementation changed after the decision was recorded" scenario Correction 2 requires the lint to catch, without any notification event.
