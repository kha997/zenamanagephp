---
work_id: GAP-083
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-19-gap-083-security-scan-gating-evidence.md
  plan: null
  branch: docs/GAP-083-security-scan-gating
  pr: null
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
  created_at: "2026-10-19T22:11:37+07:00"
  updated_at: "2026-10-19T22:11:37+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Các bước quét bảo mật trong CI chỉ **báo** lỗ hổng (bình luận trên PR, tab
Security) chứ **không bao giờ làm đỏ** kiểm tra. Một PR thêm gói hoặc image có
lỗ hổng đã biết vẫn merge được với toàn bộ CI xanh. Chi tiết:
`docs/audits/2026-10-19-gap-083-security-scan-gating-evidence.md`.

- `composer audit` (2 workflow): có `|| true`.
- Trivy quét Docker image (2 workflow): không đặt `exit-code`, nên luôn qua.
- Bước "PHPStan Security Rules" cài một gói không tồn tại nên lần nào cũng
  lỗi, và lỗi bị che bởi `continue-on-error`.
- `npm audit` không chạy ở đâu cả; hiện có 17 lỗ hổng npm (12 high, không có
  critical), một số cần nâng bản lớn (vite 8, tailwindcss 4).

Hiện tại composer audit và Trivy đều sạch, nên bật chặn cho hai bước này sẽ
không làm đỏ PR nào đang có.

## Vấn đề vận hành

Lỗ hổng mới lọt vào main mà không có kiểm tra nào chặn.

## Người dùng bị ảnh hưởng

Gián tiếp (bảo mật). Người phát triển: PR sẽ đỏ khi đưa vào gói/image có lỗ
hổng đã biết.

## Phạm vi đề xuất

Gate 2 thiết kế: bước nào chặn (composer audit, Trivy), ngưỡng mức độ (ví dụ
HIGH/CRITICAL có bản vá), cách xử lý ngoại lệ có ghi lý do, xử lý bước PHPStan
Security Rules hỏng, và npm audit (chặn ngay, chỉ báo, hay tách việc sửa 17 lỗ
hổng npm ra trước).

## Loại trừ rõ ràng

Không nâng gói hay sửa lỗ hổng npm trong Gate 1; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa workflow, merge, phát hành hay deploy.
