---
work_id: GAP-076
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-13-gap-076-prod-php-ini-evidence.md
  plan: null
  branch: docs/GAP-076-prod-php-ini
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-13T22:46:23+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-13T22:46:23+07:00"
  updated_at: "2026-10-13T22:46:23+07:00"
generated_by: agent
---

## Owner Summary

`docker/php/php.ini` (dùng cho image production và cả docker-compose) đang
**chặn các hàm ứng dụng cần**: `proc_open`, `exec`, `curl_exec`… Hậu quả khi
chạy thật:

- **Tác vụ định kỳ** (`schedule:work`) không chạy được.
- **Backup database** (`mysqldump`) hỏng.
- **Gọi HTTP ra ngoài** (Laravel `Http::`/Guzzle, Sentry) hỏng.

Ngoài ra file còn nạp sai 10 extension (mỗi lần khởi động PHP ghi cảnh báo),
tắt docblock mà Swagger cần (`opcache.save_comments = 0`), trỏ cache opcache
vào thư mục không tồn tại, và trỏ session vào host `redis` không có trong
image.

Đề xuất: phê duyệt Gate 1 để thiết kế lại `php.ini`/`opcache.ini` cho đúng và
thêm kiểm tra vào CI.

## Vấn đề vận hành

Image production khởi động và trả lời health check (GAP-074), nhưng các chức
năng nền (lịch, backup, gọi API ngoài) sẽ lỗi khi deploy.

## Người dùng bị ảnh hưởng

Mọi người dùng nếu image production được triển khai; môi trường docker-compose
cũng dùng file này.

## Phạm vi đề xuất

Gate 2 thiết kế: `disable_functions` (bỏ hoặc chỉ giữ hàm không dùng), bỏ các
dòng `extension=` thừa, gộp cấu hình opcache về `opcache.ini` (bật
`save_comments`, sửa `file_cache`), sửa session, và bước kiểm tra trong job
Docker (không cảnh báo khởi động; `proc_open`/`curl_exec` dùng được).

## Loại trừ rõ ràng

Không đổi code ứng dụng, cấu hình nginx/supervisor, nâng gói; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi cấu hình, merge, phát hành hay deploy.
