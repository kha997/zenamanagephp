---
work_id: GAP-082
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-15-gap-082-major-package-upgrades-evidence.md
  plan: null
  branch: docs/GAP-082-major-package-upgrades
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T22:25:22+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T22:25:22+07:00"
  updated_at: "2026-10-15T22:25:22+07:00"
generated_by: agent
---

## Owner Summary

Khảo sát 4 gói anh/chị yêu cầu (chưa đổi gì):

- **doctrine/dbal 3 → 4:** làm được nếu nâng kèm `carbon-doctrine-types`
  2 → 3. Code không dùng DBAL trực tiếp.
- **Guzzle 7 → 8: không làm được** — Laravel 12 bắt buộc Guzzle 7. Chỉ có thể
  sau khi nâng Laravel lên bản lớn mới.
- **laravel/tinker 2 → 3:** làm được, chỉ là công cụ dòng lệnh.
- **l5-swagger 8 → 11:** làm được nhưng nặng nhất: kéo swagger-php 4 → 6, bỏ
  hỗ trợ chú thích `@OA` dạng docblock. 4 file controller có 203 chú thích
  phải chuyển sang PHP attribute, nếu không tài liệu API sẽ mất nội dung.

Đề xuất: phê duyệt Gate 1 để thiết kế (gói nào làm, tách hay gộp PR, cách
kiểm chứng).

## Vấn đề vận hành

Các gói cũ một bản lớn sẽ dần hết được vá lỗi; bot báo danh sách gói cũ.

## Người dùng bị ảnh hưởng

Gián tiếp (ổn định, bảo mật). Tài liệu API (`/api/documentation`) nếu đụng
l5-swagger.

## Phạm vi đề xuất

Gate 2 thiết kế cho dbal, tinker, l5-swagger (Guzzle ghi nhận là bị chặn); thứ
tự và cách chia PR; kiểm chứng (test, PHPStan, sinh lại tài liệu API).

## Loại trừ rõ ràng

Không nâng Laravel; không nâng Guzzle; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, nâng gói, merge, phát hành hay deploy.
