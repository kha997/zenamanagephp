---
# === VARIANT A: blocked_technical (read-only, no decision offered) ===
work_id: <GAP-NNN or OWN-YYYY-NNN>
gate: 3
gate_status: blocked_technical
technical_readiness:
  value: blocked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: <path>
  plan: <path>
  branch: <branch name>
  pr: <PR URL or null>
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: <path to the -v2 file once it exists, else null>
timestamps:
  created_at: <ISO-8601>
  updated_at: <ISO-8601>
generated_by: agent
residual_risk_rating: <none|low|medium|high>
mandatory_technical_gate_summary: "<one plain-language line naming which mandatory check has not passed yet — never a CI job name>"
technical_evidence:
  subject_sha: <full 40-char commit SHA of the branch HEAD this packet was drafted against>
  implementation_tree_digest: "not_computed_while_blocked"
  verified_pr_head_sha: null
  verified_at: null
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## BLOCKED — OWNER ACTION NOT REQUIRED

**Mục tiêu nghiệp vụ:** <one sentence>
**Tiến độ:** <plain-language progress>
**Lý do chặn:** <plain-language blocking reason, never a raw CI job name>
**Rủi ro nếu phát hành lúc này:** <business-terms risk>
**Bước tiếp theo:** <one short phrase>
**Cần quyết định từ chủ doanh nghiệp?** Không.

---

<!--
=== VARIANT B: awaiting_owner (decision offered) — separate file, e.g. 03-release-v2.md, `supersedes` the blocked one ===

work_id: <same>
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references: <same shape as Variant A, now with pr populated>
decision_provenance: <same shape, still claimed_repo_record>
supersedes: <path to the blocked_technical file>
superseded_by: null
timestamps: <new created_at/updated_at>
generated_by: agent
residual_risk_rating: <none|low|medium|high>
mandatory_technical_gate_summary: "<one plain-language line confirming what passed>"
technical_evidence:
  subject_sha: <full 40-char commit SHA of the branch state this packet's evidence was computed against — the exact tree, not necessarily "the latest commit">
  implementation_tree_digest: <sha256, see packet-schema.yml's implementation_tree_digest_algorithm — computed by owner_governance_compute_implementation_tree_digest(), a git-tree hash excluding ONLY this exact Gate 3 file>
  verified_pr_head_sha: <the PR head SHA whose live CI was actually inspected — may equal subject_sha, kept separate because verifying CI is a live gh-dependent fact, not part of the structural tree digest>
  verified_at: <ISO-8601, when the digest was computed>
owner_decision_binding:
  implementation_tree_digest: null   # stays null until owner_decision.value moves off "none" — see Task 5's evidence-binding-required-once-decided rule
  decision_recorded_at: null         # once set, implementation_tree_digest above MUST equal technical_evidence.implementation_tree_digest at decision time, or the lint flags staleness (stale-decision-tree-digest-mismatch)
-->

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?** <carried from Gate 1>
**2. Người dùng nào bị ảnh hưởng?** <carried from Gate 1/2>
**3. Bây giờ người dùng có thể làm gì?** <carried/refined from Gate 2 workflow_after>
**4. Rủi ro nào đã được đóng lại?** <owner-level risk closed, plain language>
**5. Đã kiểm thử những gì?** <plain-language test summary — never a CI job name or log>
**6. Điều gì KHÔNG nằm trong phạm vi lần này?** <exclusions>
**7. Vì sao các gap liên quan vẫn để riêng?** <if applicable>
**8. Rủi ro còn lại là gì?** <residual_risks_plain_language>
**9. Có thể hoàn tác không?** <rollback_impact>
**10. Đề xuất của đội kỹ thuật:** <release_recommendation>

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
<e.g. "not being asked to inspect CI logs, source code, or review comments — only whether the demonstrated behavior and residual risk are acceptable to release.">
