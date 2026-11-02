---
work_id: GAP-102
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-02-gap-102-follow-redirects-cve-evidence.md
  plan: null
  branch: docs/GAP-102-follow-redirects-cve
  pr: https://github.com/kha997/zenamanagephp/pull/391
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-02T22:40:13+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-02T22:40:13+07:00"
  updated_at: "2026-11-02T22:40:13+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Thư viện JavaScript `follow-redirects` (do `axios` kéo vào) vừa có lỗ hổng **GHSA-mrwp-f3q8-3g9p, mức cao**: khi chuyển
hướng (redirect), header nhạy cảm / thông tin đăng nhập có thể bị gửi theo. Bản đang dùng 1.16.1 bị ảnh hưởng; bản vá
1.16.2 đã có. Kiểm tra bảo mật CI (`npm audit`) vì vậy **đỏ trên mọi PR**, kể cả PR #390 (GAP-026).

Owner đã chọn mở Work ID riêng (AskUserQuestion, 2026-11-02, nguyên văn: "Mở GAP riêng (Recommended)").

## Vấn đề vận hành

Lỗ hổng đã biết trong thư viện gọi HTTP phía front-end; CI đỏ chặn merge mọi PR.

## Người dùng bị ảnh hưởng

Gián tiếp: người dùng giao diện web. Trực tiếp: mọi PR đang chờ merge.

## Bằng chứng

`docs/audits/2026-11-02-gap-102-follow-redirects-cve-evidence.md`.

## Phạm vi đề xuất

Gate 2: nâng riêng `follow-redirects` 1.16.1 → 1.16.2 trong `package-lock.json` (không đổi `package.json`).

## Loại trừ rõ ràng

Không nâng gói nào khác; không đổi mã; không deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-102 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 2, merge hay phát hành.
