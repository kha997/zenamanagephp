---
work_id: GAP-092
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-23-gap-092-smoke-server-evidence.md
  plan: null
  branch: docs/GAP-092-smoke-server
  pr: https://github.com/kha997/zenamanagephp/pull/365
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-23T00:38:06+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-23, verbatim: 'APPROVE GAP-092 Gate 1'. Bound to reviewed Draft PR #365 head 39b196df366dd4c69a701945d90036f2f5afc393, canonical base 84abd9a52cf7a22941a2435fcaf5dc5f856118c7. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-23T00:36:04+07:00"
  updated_at: "2026-10-23T00:38:06+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-092 Gate 1 in-session on 2026-10-23 against Draft PR #365
head `39b196df366dd4c69a701945d90036f2f5afc393`. This authorizes preparation of Gate 2 only.

## Owner Summary

Owner chọn GAP-092 là "Smoke test bỏ artisan serve".

Mình đọc log 30 lần chạy gần nhất của check **staging-smoke**:

- **8/30 lần (27%)** lần chạy đầu của script hỏng, phải chạy lại mới xanh.
- **1 lần** hỏng cả 3 lần thử nên check đỏ (PR #364), chạy lại mới xanh.
- Mọi lần hỏng đều giống nhau: server `artisan serve` (server PHP dựng sẵn) **chết giữa chừng**.
  Một request nhận phản hồi rỗng, request sau bị từ chối kết nối. Đây cùng loại lỗi `php -S` mà GAP-090
  đã sửa cho Dusk.

## Vấn đề vận hành

Khoảng 1/4 số lần chạy phải chạy lại. Thỉnh thoảng check đỏ dù code không có lỗi. Việc chạy lại
3 lần còn có thể che một lỗi crash thật của app.

## Người dùng bị ảnh hưởng

Người phát triển và Owner: check đỏ giả trên PR.

## Phạm vi đề xuất

Gate 2 thiết kế:
- chạy app trong smoke test bằng **nginx + php-fpm** như GAP-090, thay `artisan serve`;
- quyết định có giữ việc chạy lại 3 lần hay không;
- cách chứng minh: chạy nhiều bản liên tiếp đều xanh ngay lần đầu, và cố ý làm crash php-fpm mà cổng vẫn mở.

## Loại trừ rõ ràng

Không đổi app, không đổi các bước kiểm tra trong smoke test, không đổi workflow khác. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa script hay workflow, merge, phát hành hay deploy.
