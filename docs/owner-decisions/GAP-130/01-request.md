---
work_id: GAP-130
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-20-gap-130-login-419-evidence.md
  plan: null
  branch: docs/GAP-130-login-419
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T01:50:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'Có, mở GAP sửa lỗi 419 luôn'. Given after the agent traced the Owner's '419' report to a double-submitted login form (nginx log) and proposed a code fix through a new GAP. Canonical base 13a72bf9f0172520abe6b44019ce3a0fe929a125. Authorizes opening GAP-130 and preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T01:50:00+07:00"
  updated_at: "2026-11-20T01:50:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved opening GAP-130 in-session on 2026-11-20 ("Có, mở GAP sửa lỗi 419 luôn"). This authorizes preparation of
Gate 2 only.

## Owner Summary

Owner đăng nhập **thành công** nhưng màn hình lại hiện **"419 Page Expired"**.

- Form đăng nhập bị gửi **hai lần** liền nhau (bấm hai lần, hoặc Enter cộng bấm nút).
- Lần 1 đăng nhập thành công và đổi mã bảo vệ (CSRF). Lần 2 mang mã cũ nên bị từ chối 419.
- Trang 419 che mất việc đã đăng nhập. Gửi lại từ trang đó vẫn 419.
- Cùng lỗi xảy ra khi để trang đăng nhập mở quá 2 giờ rồi mới bấm.

## Vấn đề vận hành

Người dùng tưởng không vào được hệ thống.

## Người dùng bị ảnh hưởng

Mọi người đăng nhập web.

## Bằng chứng

`docs/audits/2026-11-20-gap-130-login-419-evidence.md` (log nginx production).

## Phạm vi đề xuất

Gate 2:
- chống gửi form đăng nhập hai lần;
- 419 ở trang đăng nhập không còn hiện trang lỗi;
- người đã đăng nhập mở `/login` thì vào thẳng app.

## Loại trừ rõ ràng

- 419 ở các form khác (ngoài trang đăng nhập).
- Thời gian phiên đăng nhập.
- Deploy.

## Khả năng hoàn tác

Revert PR.

## Decision Needed

Owner chọn một: `APPROVE GAP-130 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Phương án, merge, deploy.
