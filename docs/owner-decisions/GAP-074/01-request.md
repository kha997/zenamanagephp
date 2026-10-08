---
work_id: GAP-074
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-074-prod-image-nginx-evidence.md
  plan: null
  branch: docs/GAP-074-prod-image-nginx
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T23:45:20+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T23:45:20+07:00"
  updated_at: "2026-10-08T23:45:20+07:00"
generated_by: agent
---

## Owner Summary

Image production (`Dockerfile.prod`) hiện **không phục vụ được HTTP**:

- **nginx không khởi động:** image chép nhầm file cấu hình. `docker/nginx/nginx.conf`
  là phần cấu hình con dành cho docker-compose (thiếu `events {}`/`http {}`),
  lại trỏ tới các cổng 8001–8005 không tồn tại trong image. `nginx -t` báo lỗi.
- **nginx chạy bằng user `nginx`** nên không mở được cổng 80.
- **HEALTHCHECK sai:** gọi HTTP vào cổng 9000 (là php-fpm, không phải HTTP) nên
  luôn báo "unhealthy"; `EXPOSE 9000` cũng sai.

Ảnh hưởng: workflow deploy/release build từ `Dockerfile.prod`; `docker-compose`
và `deploy.sh` dùng `Dockerfile` khác, không bị ảnh hưởng.

Đề xuất: phê duyệt Gate 1 để thiết kế cấu hình nginx riêng cho image
production, sửa user, HEALTHCHECK, cổng, và thêm kiểm tra trong CI.

## Vấn đề vận hành

Nếu triển khai bằng image này, web không chạy và hệ thống giám sát luôn báo
container hỏng.

## Người dùng bị ảnh hưởng

Mọi người dùng nếu image production này được triển khai. Không ảnh hưởng môi
trường dùng docker-compose.

## Bằng chứng

`docs/audits/2026-10-08-gap-074-prod-image-nginx-evidence.md` (kèm kết quả
`nginx -t` thật).

## Phạm vi đề xuất

Gate 2 thiết kế: file cấu hình nginx mới cho image production, sửa
`Dockerfile.prod` (COPY, EXPOSE, HEALTHCHECK), user của nginx trong
supervisord, và bước kiểm tra trong job quét Docker.

## Loại trừ rõ ràng

Không đổi `docker/nginx/nginx.conf`, `nginx.prod.conf`, docker-compose,
`Dockerfile`; không bật HTTPS trong image; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi cấu hình, merge, phát hành hay deploy.
