---
work_id: GAP-013
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
  spec: docs/audits/2026-10-25-gap-013-submittal-notifications-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-013-submittal-notifications.md
  branch: docs/GAP-013-submittal-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/371
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
  created_at: "2026-10-25T22:54:03+07:00"
  updated_at: "2026-10-26T09:52:47+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after Owner amendment 2 (AskUserQuestion, 'Sửa trong PR này (Recommended)'): the Owner Gate-3 approval bound to subject 4ec6f6dc10769748e75686ccbf23245d76177cab / digest 4fc5ea64a69d7e63c52f6d48d9eec63804bb9511c2d64dfec6463de491fae32f is superseded because 0df6a650 changed the tree to answer a Codex P2 finding: notify() now takes a recipient resolver and runs it inside its try/catch with the self-notification check, so the PM, submitter and last-rejector lookups are best-effort; new test test_pm_lookup_failure_is_logged_and_submit_still_succeeds returns 500 on 8a1c34be and 200 after. Exact-head CI at 0df6a650: 34/35 green; Owner Governance Lint red only because the packet was bound to the superseded digest, which this re-presented packet resolves. Local after the fix: tests/Feature/Api 799 OK, tests/Feature/Web 50 OK, PHPStan, php-cs-fixer, SSOT lint clean. GAP-013 at subject 0df6a650ca12f4da535ec7615e090c73c5d1990f (Gate 2 Option A plus Owner amendments 1 and 2); history below describes 4ec6f6dc. SubmittalLifecycleService: after the transaction, a first submit notifies the project pm_id (read with Project::query()->whereKey()->value()) when set and different from the actor, type submittal_submitted; decide() (approve/reject) notifies submitted_by when set and different from the actor, type submittal_approved or submittal_rejected; a shared private notify() helper creates the Notification row (tenant, user, type, Vietnamese title with the submittal number, body, project) inside try/catch that logs submittal.notification_failed; notifyLastRejector() keeps its query and now calls notify(), so the resubmit notification is unchanged. New tests/Feature/Api/SubmittalNotificationTest.php (first submit -> PM, approve -> submitter, reject -> submitter, no self-notification, refused transition sends nothing): 3 of 5 fail on the base, 5/5 pass after. Owner amendment 1 (AskUserQuestion, 'Cho PM = người nộp trong test (Recommended)'): SubmittalResubmitLifecycleTest::test_resubmit_notification_failure_is_logged_not_swallowed sets the project pm_id to the submitter before submitting so only the resubmit notification is attempted; its assertion is unchanged. Local: tests/Feature/Api --exclude-group=slow 798 tests OK; tests/Feature/Web 50 OK; SSOT test lint passes; PHPStan no errors (one property.notFound found before commit and fixed); php-cs-fixer 0 files; governance and docs lints pass. Exact-head CI at 4ec6f6dc: 35/35 green."
technical_evidence:
  base_sha: "588daf8e1a881d500e12801572933cd025de4d8e"
  subject_sha: "0df6a650ca12f4da535ec7615e090c73c5d1990f"
  implementation_tree_digest: "bbf4f70012f277c7c942c5381e6b0047f47eaa41eedac4be08af9dfcd255f8f9"
  verified_pr_head_sha: "0df6a650ca12f4da535ec7615e090c73c5d1990f"
  verified_at: "2026-10-26T09:52:47+07:00"
owner_decision_binding:
  implementation_tree_digest: "bbf4f70012f277c7c942c5381e6b0047f47eaa41eedac4be08af9dfcd255f8f9"
  decision_recorded_at: null
---

# GAP-013 — Gate 3 release decision (submittal notifications)

## OWNER GATE 3: AWAITING OWNER

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại digest `4fc5ea64…` ("APPROVE GAP-013 Gate 3"). Sau đó Codex báo lỗi P2: việc tra PM nằm ngoài
khối bắt lỗi, nên nếu truy vấn lỗi sau khi commit thì API trả 500 dù submittal đã nộp. Owner chọn "Sửa trong PR này
(Recommended)" (Owner amendment 2). Cây file đổi nên lần duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** Submittal nộp lần đầu, được duyệt hay bị từ chối đều không báo cho ai (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1, 2)** — commit `4ec6f6dc`, rồi `0df6a650`:
- Nộp lần đầu: **PM dự án** nhận thông báo `submittal_submitted`.
- Duyệt / từ chối: **người nộp** nhận `submittal_approved` / `submittal_rejected`.
- Không tự báo cho chính người thao tác. Gửi sau khi commit; lỗi gửi chỉ ghi log.
- Thông báo khi nộp lại giữ nguyên.
- **Owner amendment 1:** test ghi log lỗi khi nộp lại đặt PM = người nộp; assertion giữ nguyên.
- **Owner amendment 2** (commit `0df6a650`): việc tìm người nhận (PM, người nộp, người từ chối trước) chạy trong khối bắt
  lỗi; lỗi chỉ ghi log, thao tác vẫn thành công. Thêm test tương ứng (500 trước khi sửa, 200 sau).

**3. Bằng chứng** — base `588daf8e`; subject `0df6a650ca12f4da535ec7615e090c73c5d1990f`;
digest `bbf4f70012f277c7c942c5381e6b0047f47eaa41eedac4be08af9dfcd255f8f9`.
- Test mới (6 test) hỏng trên main, qua 6/6 sau khi sửa.
- Trên máy: 799 test `tests/Feature/Api` và 50 test `tests/Feature/Web` qua; SSOT lint, PHPStan, php-cs-fixer sạch.
- CI chạy đúng trên `0df6a650`: **34/35 xanh**; riêng Owner Governance Lint đỏ vì gói còn gắn digest cũ — gói trình lại
  này xử lý lỗi đó. (Trên `4ec6f6dc` trước amendment 2: 35/35 xanh.)

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
