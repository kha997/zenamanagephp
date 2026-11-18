---
work_id: GAP-126
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-126-production-web-login-evidence.md
  plan: null
  branch: docs/GAP-126-production-web-login
  pr: https://github.com/kha997/zenamanagephp/pull/417
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-18T15:45:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim: 'Có, làm ở đây'. Answer to the agent's question 'Mở GAP để bật đăng nhập web cho production luôn tại đây?', asked after the first production start showed HTTP 500 'Route [login] not defined'. Canonical base ca7a878b31cf3ef80fa56523b5f7a78a40a01d43. Authorizes opening GAP-126 and preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T15:45:00+07:00"
  updated_at: "2026-11-18T15:45:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved opening GAP-126 in-session on 2026-11-18 ("Có, làm ở đây"). This authorizes preparation of Gate 2 only.

## Owner Summary

Trên máy chủ thật **không ai đăng nhập được** vào ZENA.

- Trang đăng nhập `/login` chỉ được bật khi chạy ở máy dev hoặc lúc chạy test. Trên production, `/login` trả 404.
- Vào `/app/today` khi chưa đăng nhập thì báo lỗi 500 ("Route [login] not defined").
- Vì vậy bước 6 của runbook go-live ("Đăng nhập rồi đổi mật khẩu") không làm được.

Test không phát hiện được, vì mọi test đều chạy ở chế độ `testing`, nơi trang đăng nhập có sẵn.

## Vấn đề vận hành

Không lên chạy thật được: Owner, kế toán, PM và kỹ sư đều không vào được giao diện web.

## Người dùng bị ảnh hưởng

Tất cả nhân viên dùng giao diện web. Cổng khách hàng (link đăng nhập qua email) không bị ảnh hưởng.

## Bằng chứng

`docs/audits/2026-11-18-gap-126-production-web-login-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- bật trang đăng nhập / đăng xuất ở mọi môi trường;
- giữ đường "đăng nhập không cần mật khẩu" của máy dev chỉ ở máy dev;
- tài khoản đã khoá không đăng nhập web được (giống API).

## Loại trừ rõ ràng

- Quên mật khẩu qua email (máy chủ chưa cấu hình mail).
- Giao diện trang đăng nhập.
- Deploy.

## Khả năng hoàn tác

Revert PR. Chỉ route và một điều kiện đăng nhập.

## Decision Needed

Owner chọn một: `APPROVE GAP-126 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Phương án, Gate 2, merge.
