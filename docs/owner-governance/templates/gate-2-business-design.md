---
work_id: <GAP-NNN or OWN-YYYY-NNN>
gate: 2
gate_status: preparing
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: <path to docs/superpowers/specs/*.md>
  plan: null
  branch: <branch name>
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
  created_at: <ISO-8601>
  updated_at: <ISO-8601>
generated_by: agent
---

## Owner Summary
<1–2 sentences.>

## Trước / Sau
**Trước:** <numbered steps, current state>
**Sau:** <numbered steps, proposed state>

## Vai trò bị ảnh hưởng
<Which roles see a change, and what changes for each.>

## Được phép / Không được phép
<Plain statement of who can now do what, what remains blocked.>

## Trạng thái và bước tiếp theo
<If the workflow has states, name them in plain Vietnamese, say what happens next in each.>

## Ngoại lệ
<Known edge cases in business terms.>

## Hành vi người dùng nhìn thấy
<What changes on screen/in notifications, functionally, not visually.>

## Kịch bản chấp nhận
<"Given X, when Y, the system must Z" — becomes the Gate 3 acceptance checklist.>

## Loại trừ phạm vi
<Carried/refined from Gate 1.>

## Decision Needed
Owner chooses one: Approve to proceed to implementation / Request changes to the design / Decline.

## What the owner is NOT being asked to decide
<e.g. "not being asked to approve class names, database tables, or locking strategy — only whether this workflow, these roles, and these rules match what the business wants.">
