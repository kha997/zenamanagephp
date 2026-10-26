---
work_id: GAP-013
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-013-submittal-notifications-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-013-submittal-notifications.md
  branch: docs/GAP-013-submittal-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/371
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-26T07:50:01+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session, verbatim: 'APPROVE GAP-013 Gate 3'. Given after the packet at PR head a168b9610196b816509e1941bc24aeb860b688b2; bound to implementation subject 4ec6f6dc10769748e75686ccbf23245d76177cab and implementation-tree digest 4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f (recomputed at recording time, zero drift), including Owner amendment 1. Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T22:54:03+07:00"
  updated_at: "2026-10-26T07:50:01+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-013 at subject 4ec6f6dc10769748e75686ccbf23245d76177cab (Gate 2 Option A plus Owner amendment 1). SubmittalLifecycleService: after the transaction, a first submit notifies the project pm_id (read with Project::query()->whereKey()->value()) when set and different from the actor, type submittal_submitted; decide() (approve/reject) notifies submitted_by when set and different from the actor, type submittal_approved or submittal_rejected; a shared private notify() helper creates the Notification row (tenant, user, type, Vietnamese title with the submittal number, body, project) inside try/catch that logs submittal.notification_failed; notifyLastRejector() keeps its query and now calls notify(), so the resubmit notification is unchanged. New tests/Feature/Api/SubmittalNotificationTest.php (first submit -> PM, approve -> submitter, reject -> submitter, no self-notification, refused transition sends nothing): 3 of 5 fail on the base, 5/5 pass after. Owner amendment 1 (AskUserQuestion, 'Cho PM = người nộp trong test (Recommended)'): SubmittalResubmitLifecycleTest::test_resubmit_notification_failure_is_logged_not_swallowed sets the project pm_id to the submitter before submitting so only the resubmit notification is attempted; its assertion is unchanged. Local: tests/Feature/Api --exclude-group=slow 798 tests OK; tests/Feature/Web 50 OK; SSOT test lint passes; PHPStan no errors (one property.notFound found before commit and fixed); php-cs-fixer 0 files; governance and docs lints pass. Exact-head CI at 4ec6f6dc: 35/35 green."
technical_evidence:
  base_sha: "588daf8e1a881d500e12801572933cd025de4d8e"
  subject_sha: "4ec6f6dc10769748e75686ccbf23245d76177cab"
  implementation_tree_digest: "4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f"
  verified_pr_head_sha: "4ec6f6dc10769748e75686ccbf23245d76177cab"
  verified_at: "2026-10-25T22:54:03+07:00"
owner_decision_binding:
  implementation_tree_digest: "4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f"
  decision_recorded_at: "2026-10-26T07:50:01+07:00"
---

# GAP-013 — Gate 3 release decision (submittal notifications)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session ("APPROVE GAP-013 Gate 3"), bound to implementation subject
`4ec6f6dc10769748e75686ccbf23245d76177cab` and implementation-tree digest `4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Submittal nộp lần đầu, được duyệt hay bị từ chối đều không báo cho ai (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — commit `4ec6f6dc`:
- Nộp lần đầu: **PM dự án** nhận thông báo `submittal_submitted`.
- Duyệt / từ chối: **người nộp** nhận `submittal_approved` / `submittal_rejected`.
- Không tự báo cho chính người thao tác. Gửi sau khi commit; lỗi gửi chỉ ghi log.
- Thông báo khi nộp lại giữ nguyên.
- **Owner amendment 1:** test ghi log lỗi khi nộp lại đặt PM = người nộp; assertion giữ nguyên.

**3. Bằng chứng** — base `588daf8e`; subject `4ec6f6dc10769748e75686ccbf23245d76177cab`;
digest `4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f`.
- Test mới hỏng 3/5 trên main, qua 5/5 sau khi sửa.
- Trên máy: 798 test `tests/Feature/Api` và 50 test `tests/Feature/Web` qua; SSOT lint, PHPStan, php-cs-fixer sạch.
- CI chạy đúng trên `4ec6f6dc`: **35/35 xanh**.

**4. Công khai**
- Thông báo mới dùng cùng dạng với thông báo nộp lại hiện có (tiêu đề tiếng Việt, không có channel/event_key riêng).
- Register cập nhật ở đợt đối soát sau.

**5. Ảnh hưởng** — PM biết có submittal chờ duyệt; người nộp biết kết quả ngay.

**6. Rủi ro còn lại** — Thấp: thêm tối đa 1 thông báo mỗi bước.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-013 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm GAP-014b.
