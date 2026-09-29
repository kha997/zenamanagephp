---
work_id: GAP-057
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-29-gap-057-http-launch-actions-evidence.md
  plan: null
  branch: docs/GAP-057-http-launch-actions
  pr: https://github.com/kha997/zenamanagephp/pull/324
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T14:14:14+07:00"
  updated_at: "2026-09-29T14:15:08+07:00"
generated_by: agent
---

## Owner Summary

Chỉ cần quản trị viên hệ thống **mở trang "báo cáo launch"** (một thao tác
tưởng như chỉ để xem) là hệ thống tự **cập nhật cấu trúc cơ sở dữ liệu
production** và dựng lại cấu hình ngay trong lúc đang phục vụ khách hàng — bỏ
qua toàn bộ quy trình an toàn mà đường triển khai chính thức bắt buộc. Đội kỹ
thuật đã tái hiện (với lệnh giả, không chạy thật). Đề nghị cho phép thiết kế
cách sửa.

## Vấn đề vận hành

1. `GET /api/v1/final-integration/launch-report` và `POST
   …/pre-launch-actions` chạy `migrate --force`, `optimize`, `config:cache`,
   `route:cache` từ yêu cầu web.
2. Đường triển khai chính thức (GAP-049) chỉ cho migrate qua `deploy:migrate`:
   bắt buộc phân loại migration, migration phá vỡ phải bật chế độ bảo trì
   trước, và dựng cấu hình trong bản phát hành mới trước khi chuyển. Đường web
   bỏ qua tất cả.
3. Lỗi bị giấu: mọi bước lỗi vẫn trả về "thành công" (HTTP 200).

## Người dùng bị ảnh hưởng

Mọi khách hàng: một migration chạy dở hoặc sai thời điểm có thể làm hỏng dữ
liệu hoặc làm hệ thống ngừng hoạt động. Chỉ quản trị viên hệ thống kích hoạt
được; không có nút giao diện nào gọi tới.

## Bằng chứng

Đối chiếu 1171 đường truy cập đang hoạt động, đọc mã, chạy thử bằng tài khoản
quản trị hệ thống và quản trị khách hàng với lệnh giả. Chi tiết:
`docs/audits/2026-09-29-gap-057-http-launch-actions-evidence.md`.

## Tác động nếu không xử lý

Schema production có thể bị thay đổi ngoài quy trình, không sao lưu, không bảo
trì, chỉ vì mở một trang báo cáo.

## Phạm vi đề xuất

Nếu duyệt, Gate 2 thiết kế để không yêu cầu web nào chạy migrate hay dựng lại
cache cấu hình/route nữa (việc đó thuộc bước triển khai), có kiểm thử chứng
minh.

## Loại trừ rõ ràng

- Không sửa các mục "checklist launch" mô phỏng luôn báo thành công (ghi nhận,
  không phải lỗi gây hại).
- Không xoá tính năng final-integration.
- Không deploy.

## Khả năng hoàn tác

Chỉ là tài liệu ở Gate này.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách sửa, Gate 2, merge hay phát hành.
