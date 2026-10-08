---
work_id: GAP-071
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-08-gap-071-docker-image-vulnerabilities-evidence.md
  plan: null
  branch: docs/GAP-071-docker-image-vulnerabilities
  pr: https://github.com/kha997/zenamanagephp/pull/344
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T17:41:40+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-08, verbatim: 'APPROVE GAP-071 Gate 1'. Bound to reviewed Draft PR #344 head 5089552233c6e763565ddc220bcabd8c553acced, canonical base e1bd5de0d13113d2572f951d61bb873a38a3d146. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T17:37:12+07:00"
  updated_at: "2026-10-08T17:41:40+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-071 Gate 1 in-session on 2026-10-08 against Draft PR #344
head `5089552233c6e763565ddc220bcabd8c553acced`. This authorizes preparation of Gate 2 only.

## Owner Summary

Bước 2 của kế hoạch xử lý cảnh báo bot. Bot "Docker Security Scan" báo **32
lỗ hổng**, tất cả nằm trong gói hệ điều hành (Alpine) của image production
(`Dockerfile.prod`), không còn lỗ hổng thư viện PHP sau GAP-070.

- Image gốc `php:8.2-fpm-alpine` chỉ góp **2** lỗ hổng (nghttp2, zlib), cả hai
  đã có bản vá.
- Khoảng **30** còn lại đến từ các gói chúng ta tự cài thêm và để lại trong
  image: `supervisor` kéo theo **python3**, `git` kéo theo **pcre2**, các gói
  `-dev` chỉ cần lúc build, và vài gói không thấy ứng dụng dùng (`git`, `zip`,
  `unzip`, Redis server, `imagemagick`).
- Không có bước `apk upgrade`, và CI dùng bộ nhớ đệm build nên các bản vá mới
  của Alpine có thể không được lấy.

Đề xuất: phê duyệt Gate 1 để thiết kế cách cập nhật gói hệ thống và bỏ các gói
không cần trong image production.

## Vấn đề vận hành

Image production chứa phần mềm có lỗ hổng đã công bố (trong đó có mức HIGH ở
python và pcre2) và nhiều gói không cần khi chạy, làm tăng bề mặt tấn công.

## Người dùng bị ảnh hưởng

Không có thay đổi hành vi với người dùng. Ảnh hưởng là an toàn của máy chủ chạy
image production.

## Bằng chứng

`docs/audits/2026-10-08-gap-071-docker-image-vulnerabilities-evidence.md`:
báo cáo bot, quét Trivy image gốc, phân tích nguồn gốc gói trong
`Dockerfile.prod`. Danh sách đầy đủ 32 mục sẽ được ghi ở Gate 2 (file kết quả
của CI không tải được từ phiên này).

## Phạm vi đề xuất

Gate 2 thiết kế thay đổi `Dockerfile.prod`: cập nhật gói hệ thống khi build,
gỡ gói chỉ dùng lúc build và gói không dùng; kiểm chứng bằng Trivy trước/sau và
toàn bộ CI.

## Loại trừ rõ ràng

Không đổi PHP 8.2 hay Alpine major; không đổi code ứng dụng; không đổi
`Dockerfile` (dev) và `Dockerfile.websocket`; không đổi CI chặn (bước 5);
không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi Dockerfile, merge, phát hành hay deploy.
