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
  updated_at: "2026-10-26T22:10:46+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "RE-PRESENTED after Owner amendment 1 (AskUserQuestion, 'Sửa trong PR này (Recommended)'): the Owner Gate-3 approval bound to subject 2fde4d3f0e9e75131e6c5fa6eae525ea84a45e5a / digest 5a24218c4c44277af5c102b326f7f73f845b6bf1672ed92fd049de84b541e560 is superseded because 5e635981 changed the tree to answer a Codex P2 finding: an NCR title may be 255 characters, so the prefixed notification title overflowed notifications.title VARCHAR(255) on strict MySQL and the notification was lost; notifyNcr() now cuts the composed title to 255 characters with mb_substr (the NCR keeps its full title). New test test_max_length_ncr_title_is_cut_to_the_notification_title_column fails on 30527f7e and passes after. Local after the fix: InspectionNcrNotificationTest 7/7; tests/Feature/Api --exclude-group=slow 806 OK; tests/Feature/Web + InspectionNcrWorkflowTest 53 OK; PHPStan no errors; php-cs-fixer 0 files; SSOT test lint passes. History (subject 2fde4d3f, Gate 2 Option A): storeNcr() notifies assigned_to (ncr_assigned, ncr.assigned); updateNcrStatus() notifies created_by on resolved (ncr_resolved) and assigned_to on closed (ncr_closed); in_progress sends nothing; no self-notification; failures logged as ncr.notification_failed; NcrEventListener and its 14 phpstan-baseline entries deleted; exact-head CI at 2fde4d3f and 30527f7e: 35/35 green."
technical_evidence:
  base_sha: "681043875ecda4c0cd19a19908c2dfbb41031194"
  subject_sha: "5e635981c93fd0cae549e9f0fa487dfd3cedbc86"
  implementation_tree_digest: "9029eca08ec6848a226eb3794ca6a963ac45cfd0070f83665ae8ffd347d84416"
  verified_pr_head_sha: "5e635981c93fd0cae549e9f0fa487dfd3cedbc86"
  verified_at: "2026-10-26T22:10:46+07:00"
owner_decision_binding:
  implementation_tree_digest: "9029eca08ec6848a226eb3794ca6a963ac45cfd0070f83665ae8ffd347d84416"
  decision_recorded_at: null
---

# GAP-014b — Gate 3 release decision (NCR notifications)

## OWNER GATE 3: AWAITING OWNER (re-presented)

## Gói quyết định phát hành (trình lại)

Owner đã duyệt Gate 3 tại digest `5a24218c…` ("APPROVE GAP-014b Gate 3"). Sau đó Codex báo lỗi P2: title NCR được dài tới
255 ký tự, cộng tiền tố thì vượt cột `notifications.title` (255), nên trên MySQL strict thông báo bị mất. Owner chọn
"Sửa trong PR này (Recommended)" (Owner amendment 1). Cây file đổi nên lần duyệt trên **không còn hiệu lực**.

**1. Vấn đề là gì?** Tạo và đổi trạng thái NCR không báo cho ai; listener thông báo NCR có sẵn nhưng không bao giờ chạy
được (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A + Owner amendment 1)** — commit `2fde4d3f`, rồi `5e635981`:
- Tạo NCR có người được giao: **người được giao** nhận `ncr_assigned`.
- NCR "đã xử lý": **người tạo** nhận `ncr_resolved`.
- NCR "đóng": **người được giao** nhận `ncr_closed`.
- Không tự báo cho người thao tác; lỗi gửi chỉ ghi log. API và trang web đều có.
- Xoá `NcrEventListener` và 14 mục phpstan-baseline của nó.
- Tiêu đề thông báo được cắt còn tối đa 255 ký tự (NCR vẫn giữ title đầy đủ).

**3. Bằng chứng** — base `68104387`; subject `5e635981c93fd0cae549e9f0fa487dfd3cedbc86`;
digest `9029eca08ec6848a226eb3794ca6a963ac45cfd0070f83665ae8ffd347d84416`.
- Test mới hỏng 4/6 trên main, qua 6/6 sau khi sửa (`2fde4d3f`); test title 255 ký tự hỏng trên `30527f7e`, qua sau
  `5e635981`.
- Trên máy sau khi sửa: 806 test API, 53 test Web + NCR qua; SSOT lint, PHPStan, php-cs-fixer sạch.
- CI chạy đúng trên `2fde4d3f` và `30527f7e`: **35/35 xanh**; CI trên head mới sẽ báo trong PR.

**4. Công khai**
- PHPStan báo lỗi trước khi commit (đọc trực tiếp thuộc tính model, docblock đặt sai chỗ); đã sửa trong code, **không sửa
  baseline** ngoài việc xoá 14 mục của file listener bị xoá.
- Codex P2 (tiêu đề thông báo vượt 255 ký tự) sửa theo Owner amendment 1 sau lần duyệt Gate 3 trước.
- Register cập nhật ở đợt đối soát sau.

**5. Ảnh hưởng** — Người được giao biết ngay khi có NCR; người tạo biết khi NCR đã xử lý xong.

**6. Rủi ro còn lại** — Thấp.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-014b Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không làm GAP-014c.
