---
work_id: GAP-089
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-21-gap-089-dockerfiles-build-evidence.md
  plan: null
  branch: docs/GAP-089-dockerfiles-build
  pr: https://github.com/kha997/zenamanagephp/pull/362
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T19:37:04+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-089 Gate 1'. Bound to reviewed Draft PR #362 head c59343c90d6f2ac2d66e24fa0c357fc345594718, canonical base 8e6523e2ea31b7d9139ede916af11d7eacefa17a. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T19:35:01+07:00"
  updated_at: "2026-10-21T19:37:04+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-089 Gate 1 in-session on 2026-10-21 against Draft PR #362
head `c59343c90d6f2ac2d66e24fa0c357fc345594718`. This authorizes preparation of Gate 2 only.

## Owner Summary

Lúc kiểm chứng GAP-088, mình phát hiện 2 Dockerfile **vốn không build được** trên main.
Đọc kỹ thêm thì thấy còn vài lỗi nữa:

**`Dockerfile.websocket`** (workflow release dùng file này, nên release hiện sẽ hỏng ở bước build image websocket):
- thiếu gói `linux-headers` và `$PHPIZE_DEPS`, nên không build được;
- lệnh khởi động gọi `websockets:serve`, lệnh **không tồn tại**. Lệnh thật là `websocket:serve`,
  cổng mặc định 8080;
- `HEALTHCHECK` và `EXPOSE` dùng cổng 6001, còn `docker-compose.yml` dùng 8080.

**`Dockerfile`** (các file `docker-compose*.yml`, kể cả bản prod, đều dùng file này):
- copy `docker/php/php-fpm.conf`, nhưng file thật nằm ở `docker/php-fpm.conf`;
- extension `intl` không biên dịch được vì thiếu `libicu-dev`;
- `composer install` chạy trước khi copy mã nguồn, nên sẽ lỗi ở bước `artisan package:discover`.

**Lỗi liên quan:** `docker-compose.loadbalancer.yml` dùng `Dockerfile.monitoring`, file này không tồn tại.

## Vấn đề vận hành

Image websocket và image dùng cho docker-compose không build được. Release
sẽ hỏng ở bước build image websocket. Docker-compose prod hay local cũng không dựng
được.

## Người dùng bị ảnh hưởng

- Người phát hành: workflow release.
- Người chạy docker-compose: local, prod, loadbalancer.

## Phạm vi đề xuất

Gate 2 sẽ đề xuất, cho từng file:
- **sửa** cho build và khởi động được, hay **bỏ hẳn**;
- có thêm bước build các file này vào CI của PR hay không, để chúng không hỏng lại.

## Loại trừ rõ ràng

Không đổi `Dockerfile.prod`. Không đổi logic ứng dụng. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa file, merge, phát hành hay deploy.
