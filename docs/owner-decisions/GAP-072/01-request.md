---
work_id: GAP-072
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-072-composer-semver-safe-updates-evidence.md
  plan: null
  branch: docs/GAP-072-composer-semver-safe-updates
  pr: https://github.com/kha997/zenamanagephp/pull/345
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T20:15:43+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T20:15:43+07:00"
  updated_at: "2026-10-08T20:15:43+07:00"
generated_by: agent
---

## Owner Summary

Bước 3 của kế hoạch xử lý cảnh báo bot. Bot "Dependency Scan Report" báo **88
gói Composer cũ**. Không gói nào còn lỗ hổng đã biết (sau GAP-070).

- **72** gói có bản mới **nằm trong giới hạn phiên bản hiện tại** (chạy thử:
  77 thay đổi trong `composer.lock`, không sửa `composer.json`). Chủ yếu là bản
  vá Symfony 7.4, các gói Laravel phụ, monolog, carbon, sentry, aws-sdk, công cụ
  test.
- **15** gói cần nâng **phiên bản chính** (doctrine/dbal 4, Guzzle 8, tinker 3,
  l5-swagger 11 và các gói đi kèm): cần sửa code, làm riêng từng việc sau.
- 1 gói (`doctrine/annotations`) chỉ bị đánh dấu ngừng phát triển.

Đề xuất: phê duyệt Gate 1 để thiết kế việc cập nhật nhóm 72 gói an toàn.

## Vấn đề vận hành

Thư viện tụt bản càng lâu thì lần nâng sau càng lớn và rủi ro; bot báo con số
này trên mọi PR.

## Người dùng bị ảnh hưởng

Không đổi hành vi mong đợi. Thay đổi là thư viện dùng chung, nên kiểm chứng
bằng toàn bộ test và CI.

## Bằng chứng

`docs/audits/2026-10-08-gap-072-composer-semver-safe-updates-evidence.md`:
phân loại `composer outdated` và kết quả chạy thử `composer update --dry-run`.

## Phạm vi đề xuất

Gate 2 thiết kế: cập nhật lockfile trong giới hạn phiên bản hiện có, chạy đủ
test, PHPStan và CI.

## Loại trừ rõ ràng

Không nâng phiên bản chính (15 gói); không sửa `composer.json` hay code; không
đổi CI; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi lockfile, merge, phát hành hay deploy.
