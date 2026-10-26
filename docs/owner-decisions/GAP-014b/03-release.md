---
work_id: GAP-014b
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-10-26-gap-014b-ncr-notifications-evidence.md
  plan: docs/superpowers/plans/2026-10-26-gap-014b-ncr-notifications.md
  branch: docs/GAP-014b-ncr-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/372
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-26T11:21:49+07:00"
  updated_at: "2026-10-26T11:21:49+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-014b at subject 2fde4d3f0e9e75131e6c5fa6eae525ea84a45e5a (Gate 2 Option A). Api\\InspectionController: storeNcr() calls notifyNcr() for assigned_to (type ncr_assigned, event ncr.assigned); updateNcrStatus() calls it for created_by on resolved (ncr_resolved, ncr.resolved) and for assigned_to on closed (ncr_closed, ncr.closed); in_progress sends nothing. notifyNcr() skips an empty recipient or the actor, creates an in-app Notification (tenant, user, type, priority normal, title prefix + NCR title, body = NCR number, channel inapp, event key, project, data with ncr_id, inspection_id, ncr_status) and catches any Throwable, logging ncr.notification_failed. Attributes are read with getAttribute()/getKey() and the actor with Auth::id() so PHPStan baseline counts are unchanged. app/Listeners/NcrEventListener.php (imports four nonexistent App\\Events\\Ncr* classes, never dispatched or registered, writes non-fillable message/channels) is deleted with its 14 phpstan-baseline.neon entries. New tests/Feature/Api/InspectionNcrNotificationTest.php (6 tests: create notifies assignee; resolved notifies creator and in_progress sends nothing; closed notifies assignee; no self-notification; refused transition sends nothing; notification failure logged and create still succeeds): 4 of 6 fail on the base, 6/6 pass after. Local: tests/Feature/Api --exclude-group=slow 805 OK; tests/Feature/InspectionNcrWorkflowTest + tests/Feature/Web 53 OK; SSOT test lint passes; PHPStan no errors (dynamic-property and misplaced-docblock findings fixed before commit); php-cs-fixer 0 files; governance and docs lints pass. Exact-head CI at 2fde4d3f: 35/35 green."
technical_evidence:
  base_sha: "681043875ecda4c0cd19a19908c2dfbb41031194"
  subject_sha: "2fde4d3f0e9e75131e6c5fa6eae525ea84a45e5a"
  implementation_tree_digest: "5a24218c4c44277af5c102b326f7f73f845b6bf1672ed92fd049de84b541e560"
  verified_pr_head_sha: "2fde4d3f0e9e75131e6c5fa6eae525ea84a45e5a"
  verified_at: "2026-10-26T11:21:49+07:00"
owner_decision_binding:
  implementation_tree_digest: "5a24218c4c44277af5c102b326f7f73f845b6bf1672ed92fd049de84b541e560"
  decision_recorded_at: null
---

# GAP-014b — Gate 3 release decision (NCR notifications)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** Tạo và đổi trạng thái NCR không báo cho ai; listener thông báo NCR có sẵn nhưng không bao giờ chạy
được (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — commit `2fde4d3f`:
- Tạo NCR có người được giao: **người được giao** nhận `ncr_assigned`.
- NCR "đã xử lý": **người tạo** nhận `ncr_resolved`.
- NCR "đóng": **người được giao** nhận `ncr_closed`.
- Không tự báo cho người thao tác; lỗi gửi chỉ ghi log. API và trang web đều có.
- Xoá `NcrEventListener` và 14 mục phpstan-baseline của nó.

**3. Bằng chứng** — base `68104387`; subject `2fde4d3f0e9e75131e6c5fa6eae525ea84a45e5a`;
digest `5a24218c4c44277af5c102b326f7f73f845b6bf1672ed92fd049de84b541e560`.
- Test mới hỏng 4/6 trên main, qua 6/6 sau khi sửa.
- Trên máy: 805 test API, 53 test Web + NCR qua; SSOT lint, PHPStan, php-cs-fixer sạch.
- CI chạy đúng trên `2fde4d3f`: **35/35 xanh**.

**4. Công khai**
- PHPStan báo lỗi trước khi commit (đọc trực tiếp thuộc tính model, docblock đặt sai chỗ); đã sửa trong code, **không sửa
  baseline** ngoài việc xoá 14 mục của file listener bị xoá.
- Register cập nhật ở đợt đối soát sau.

**5. Ảnh hưởng** — Người được giao biết ngay khi có NCR; người tạo biết khi NCR đã xử lý xong.

**6. Rủi ro còn lại** — Thấp.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-014b Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm GAP-014c.
