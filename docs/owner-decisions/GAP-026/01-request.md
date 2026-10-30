---
work_id: GAP-026
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-30-gap-026-production-slack-evidence.md
  plan: null
  branch: docs/GAP-026-production-slack
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-30T07:31:27+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-30T07:31:27+07:00"
  updated_at: "2026-10-30T07:31:27+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

1. **Chặn bên ngoài đã gỡ:** Owner xác nhận secret `SLACK_WEBHOOK_URL` đang trỏ tới kênh Slack Zena (`C0BBAL5SZM4`), và
   production giữ secret này.
2. **Phát hiện mới, quan trọng hơn:** 2 bước gửi Slack trong `production.yml` (báo trạng thái deploy, và báo động khi
   migration phá vỡ cần khôi phục tay) **không bao giờ chạy**. Điều kiện `env.SLACK_WEBHOOK_URL != ''` đọc một biến chỉ
   được khai báo trong chính bước đó — GitHub chưa nạp biến này khi xét điều kiện, nên điều kiện luôn sai. Hai workflow
   khác khai báo biến ở cấp workflow nên chạy đúng.

Hệ quả: hiện kênh Zena **không nhận** thông báo deploy production nào, kể cả cảnh báo khẩn.

## Vấn đề vận hành

Người vận hành tưởng có cảnh báo Slack cho production nhưng thực tế không có.

## Người dùng bị ảnh hưởng

Owner và người vận hành theo dõi deploy production.

## Bằng chứng

`docs/audits/2026-10-30-gap-026-production-slack-evidence.md`.

## Phạm vi đề xuất

Gate 2: sửa `production.yml` để 2 bước Slack thực sự chạy khi có secret (khai báo biến ở cấp phù hợp, như 2 workflow
kia), giữ nguyên secret `SLACK_WEBHOOK_URL` và nội dung tin; ghi nhận xác nhận kênh và gỡ "đóng băng" trong tài liệu;
cập nhật sổ GAP-026. Kèm test / kiểm tra workflow cụ thể.

## Loại trừ rõ ràng

Không đổi secret hay kênh; không đổi các bước deploy, SSH, migration; không đụng 2 workflow khác; không chạy deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-026 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách sửa, Gate 2, merge hay chạy deploy.
