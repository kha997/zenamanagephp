---
work_id: GAP-076
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-13-gap-076-prod-php-ini-evidence.md
  plan: null
  branch: docs/GAP-076-prod-php-ini
  pr: https://github.com/kha997/zenamanagephp/pull/349
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-13T22:47:34+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-13, verbatim: 'APPROVE GAP-076 Gate 1'. Bound to reviewed Draft PR #349 head 09e46db7e37f3a25e9bdf9d75313d69e70b9150c, canonical base e7358e0a9dd9cc35e00bfef1aa6dad1329c4d5ae. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-13T22:46:23+07:00"
  updated_at: "2026-10-13T22:47:34+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-076 Gate 1 in-session on 2026-10-13 against Draft PR #349
head `09e46db7e37f3a25e9bdf9d75313d69e70b9150c`. This authorizes preparation of Gate 2 only.

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
