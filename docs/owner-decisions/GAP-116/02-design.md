---
work_id: GAP-116
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-09-gap-116-project-factory-dates-evidence.md
  plan: null
  branch: docs/GAP-116-project-factory-dates
  pr: https://github.com/kha997/zenamanagephp/pull/406
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
  created_at: "2026-11-09T23:50:00+07:00"
  updated_at: "2026-11-09T23:50:00+07:00"
generated_by: agent
---

# GAP-116 — Flaky ProjectApiTest (project factory dates): Gate 2 design

## Owner Summary

Anh/chị cần chọn **2 điểm**:

1. **Phạm vi sửa** — đề xuất **F-1**: sửa cả 3 chỗ trong dữ liệu mẫu dự án.
   - **Mặc định:** ngày bắt đầu từ 1 tháng trước đến 3 tuần sau; ngày kết thúc từ 5 tuần đến 6 tháng sau. Hai khoảng cách
     nhau 2 tuần nên không bao giờ trùng.
   - **"Đã xong":** bắt đầu từ 6 đến 2 tháng trước; kết thúc trong tháng vừa qua.
   - **"Quá hạn":** bắt đầu từ 6 đến 2 tháng trước; kết thúc từ 1 tháng trước đến hôm qua.
2. **Test canh** — đề xuất **G-1**: thêm 1 test tạo 200 dự án mẫu cho mỗi kiểu và kiểm tra ngày kết thúc luôn **sau** ngày
   bắt đầu. Lỗi này không thể quay lại mà không bị phát hiện.

Ngoài ra: **phát hành** một PR, một Gate 3.

## Điểm 1 — Phạm vi sửa

| Phương án | Nội dung |
|---|---|
| **F-1 (đề xuất)** | Sửa khoảng ngày mặc định và cả hai kiểu "đã xong", "quá hạn" như trên. Mọi dự án mẫu đều có ngày kết thúc sau ngày bắt đầu. |
| F-2 | Chỉ sửa khoảng ngày mặc định (đủ chữa test đang đỏ). Hai kiểu kia vẫn có thể sinh dữ liệu sai. |

## Điểm 2 — Test canh

| Phương án | Nội dung |
|---|---|
| **G-1 (đề xuất)** | `tests/Unit/Factories/ProjectFactoryDatesTest.php`: dùng `make()` (không ghi database) tạo 200 dự án cho mỗi kiểu (mặc định, đã xong, quá hạn). Kiểm tra ngày kết thúc (theo ngày lịch) sau ngày bắt đầu; với "quá hạn" kiểm tra thêm ngày kết thúc trước hôm nay. |
| G-2 | Không thêm test. |

## Test (đỏ trước / xanh sau)

- Test canh G-1 hỏng trên factory hiện tại. Gieo seed cố định để chắc chắn gặp trường hợp trùng ngày / ngược ngày.
- Sửa xong thì xanh.
- `ProjectApiTest` chạy lặp 50 lần đều xanh.

## Files (allowlist)

- Sửa: `database/factories/ProjectFactory.php`.
- Mới: `tests/Unit/Factories/ProjectFactoryDatesTest.php`.
- Packet và evidence GAP-116.

Không đổi mã chạy thật, không migration, không thêm thư viện.

## Ngoài phạm vi

Quy tắc kiểm tra ngày, controller, test API, các factory khác, deploy.

## Verification required at Gate 3

- Đỏ trước / xanh sau.
- Unit + Feature + Integration và `tests/Feature/Api` không lỗi mới.
- php-cs-fixer, SSOT lint sạch.
- CI trên đúng head xanh.

## Rollback

Revert squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-116 Gate 2 F-1 G-1` (hoặc phương án khác) / Changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge.
