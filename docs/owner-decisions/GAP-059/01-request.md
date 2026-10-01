---
work_id: GAP-059
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-30-gap-059-smtp-password-evidence.md
  plan: null
  branch: docs/GAP-059-smtp-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/327
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T12:44:32+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-30: 'APPROVE GAP-059 Gate 1'. Bound to reviewed Draft PR #327 head 8a61abe242c923ad3c6cedf454623e2ca911e581, canonical base 943577f75e8e519dfb42109a094a2632a670fd35. Authorizes preparing Gate 2 only; not implementation, credential rotation, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T12:08:41+07:00"
  updated_at: "2026-09-30T12:44:32+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-059 Gate 1 in-session on 2026-09-30 against Draft PR #327
head `8a61abe242c923ad3c6cedf454623e2ca911e581`. This authorizes preparation of Gate 2 only.

## Owner Summary

Script cài đặt email (SMTP) không chỉ để lộ mật khẩu email trên dòng lệnh như
sổ đã ghi, mà còn **ghi sai hoặc không ghi được mật khẩu** vào file cấu hình
với một số ký tự thường gặp, và **để lại các bản sao file bí mật** trong thư
mục dự án. Đề nghị cho phép thiết kế cách sửa.

## Vấn đề vận hành

1. Mật khẩu email lộ trên dòng lệnh ở 2 chỗ (lệnh kiểm tra và lệnh `sed` ghi
   file).
2. Mật khẩu có `/` → không được ghi nhưng script vẫn báo "thành công"; có
   `&` hoặc `"` → ghi sai (đã tái hiện với dữ liệu giả).
3. Bước "kiểm tra" thực ra **ghi lại** cấu hình lần hai, không có dấu ngoặc;
   mật khẩu có chuỗi như `$1` bị ghi sai (đã tái hiện).
4. Mỗi lần chạy để lại `.env.backup.<thời điểm>` và `.env.bak` — bản sao
   đầy đủ mọi bí mật, ai trên máy cũng đọc được, không bao giờ bị xoá.

## Người dùng bị ảnh hưởng

Mọi khách hàng khi email hệ thống (mời, thông báo) ngừng gửi vì mật khẩu bị
ghi sai; toàn hệ thống nếu bản sao bí mật bị đọc. Chỉ xảy ra khi có người chạy
script thủ công này.

## Bằng chứng

Đọc mã script và lệnh, tái hiện 4 kiểu ghi sai trong thư mục tạm với dữ liệu
giả. Chi tiết: `docs/audits/2026-09-30-gap-059-smtp-password-evidence.md`.

## Phạm vi đề xuất

Nếu duyệt, Gate 2 thiết kế: không đưa mật khẩu lên dòng lệnh; ghi file cấu
hình an toàn với mọi ký tự; bỏ bước "kiểm tra" ghi đè; không để lại bản sao bí
mật (hoặc giới hạn quyền đọc và tự dọn).

## Loại trừ rõ ràng

- Không đổi mật khẩu email thật; không vào máy chủ.
- Không sửa việc script xoá bộ nhớ đệm (`cache:clear`) — ghi nhận, việc riêng.
- Không deploy.

## Khả năng hoàn tác

Chỉ tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt cách sửa, Gate 2, merge hay phát hành.
