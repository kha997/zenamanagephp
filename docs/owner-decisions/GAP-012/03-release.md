---
work_id: GAP-012
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
  spec: docs/audits/2026-10-25-gap-012-cr-apply-notification-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-012-cr-apply-notification.md
  branch: docs/GAP-012-cr-apply-notification
  pr: https://github.com/kha997/zenamanagephp/pull/370
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
  created_at: "2026-10-25T21:34:18+07:00"
  updated_at: "2026-10-25T21:34:18+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-012 at subject 6b70d927578966809d5bd739abb39ff397299899 (Gate 2 Option A plus Owner amendment 1). ChangeRequestController::apply(), after the delta task, link-back and baseline snapshot and before DB::commit(), calls createWorkflowNotification() for requested_by (type change_request_implemented, title 'Change request implemented') and for the project pm_id when set and different (type change_request_implemented_pm), event key change_request.implemented; the notification data carries the CR id and status implemented. requested_by is read with getAttribute() and the project with Project::query()->find() so the PHPStan baseline counts for approve() are unchanged. ChangeRequestStakeholderNotificationTest: test_apply_does_not_send_any_notification replaced by test_apply_notifies_requester_and_project_pm, test_apply_notifies_only_requester_when_pm_is_requester and test_refused_apply_sends_no_notification (helper makeApprovedCr); class comment updated. Owner amendment 1 (AskUserQuestion, 'Sửa test trong PR này (Recommended)'): ChangeRequestApiTest::test_can_implement_change_request asserted no change_request_implemented notification; that assertion now expects the requester's notification. History on the branch: 0765bfa9 failed PHPStan (ignore.count exceeded by the copied block) and API Tests (the old ChangeRequestApiTest assertion); cbbd832c fixed PHPStan; 6b70d927 applied amendment 1. Local: new tests fail on the base (2 of 7) and pass after; tests/Feature/Api --exclude-group=slow 793 tests OK; SSOT test lint passes; PHPStan no errors; php-cs-fixer 0 files; governance and docs lints pass. Exact-head CI at 6b70d927: 35/35 green."
technical_evidence:
  base_sha: "b810ed921b82edf2c83d222287f4d55826826950"
  subject_sha: "6b70d927578966809d5bd739abb39ff397299899"
  implementation_tree_digest: "122adcbe77d7e400f27ce410f1aec9a4c0cf79973df33bdc2f27ea5f63e8eee4"
  verified_pr_head_sha: "6b70d927578966809d5bd739abb39ff397299899"
  verified_at: "2026-10-25T21:34:18+07:00"
owner_decision_binding:
  implementation_tree_digest: "122adcbe77d7e400f27ce410f1aec9a4c0cf79973df33bdc2f27ea5f63e8eee4"
  decision_recorded_at: null
---

# GAP-012 — Gate 3 release decision (change request apply notification)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành

**1. Vấn đề là gì?** Apply CR (chuyển sang "đã thực hiện", tạo task bù, cập nhật baseline) không báo cho ai (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — commit `6b70d927`:
- Apply thành công gửi thông báo trong app cho **người yêu cầu** và **PM dự án** (nếu khác), loại
  `change_request_implemented` / `change_request_implemented_pm`, trong cùng transaction.
- Thay test cũ "apply không gửi thông báo" bằng test người nhận và test apply bị từ chối.
- **Owner amendment 1:** sửa assertion cũ trong `ChangeRequestApiTest` (đang khẳng định không có thông báo).

**3. Bằng chứng** — base `b810ed92`; subject `6b70d927578966809d5bd739abb39ff397299899`;
digest `122adcbe77d7e400f27ce410f1aec9a4c0cf79973df33bdc2f27ea5f63e8eee4`.
- Test mới hỏng trên main (2/7), qua sau khi sửa.
- Trên máy: 793 test `tests/Feature/Api` qua; SSOT lint, PHPStan, php-cs-fixer sạch.
- CI chạy đúng trên `6b70d927`: **35/35 xanh**.

**4. Công khai**
- Commit đầu `0765bfa9` đỏ PHPStan (đoạn mới làm vượt số lần baseline cho phép) và API Tests (assertion cũ). PHPStan sửa
  ở `cbbd832c` bằng cách viết lại đoạn mới, **không sửa baseline**; API Tests sửa ở `6b70d927` theo amendment 1.
- Ghi chú S3.2a trong `docs/roadmap/backlog.yaml` (hoãn thông báo apply) giữ nguyên làm lịch sử; register cập nhật ở đợt
  đối soát sau.

**5. Ảnh hưởng** — Người yêu cầu và PM biết ngay khi CR được thực hiện.

**6. Rủi ro còn lại** — Thấp: thêm 1–2 dòng thông báo mỗi lần apply.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-012 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm GAP-013 hay GAP-014b.
