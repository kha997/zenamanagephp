---
work_id: GAP-127
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-18-gap-127-dependency-advisories-evidence.md
  plan: null
  branch: docs/GAP-127-dependency-advisories
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T17:10:00+07:00"
  updated_at: "2026-11-18T17:10:00+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Bước kiểm tra bảo mật thư viện trong CI đang **đỏ trên `main`** từ đêm 14/11, nên mọi PR mới đều có 2 dấu đỏ (kiểm tra này và Owner Governance Lint).
Nguyên nhân là 2 cảnh báo mới về thư viện bên ngoài, không phải do code của ZENA thay đổi. Cả hai **hiện không khai thác được trên máy chủ thật**.

## Vấn đề vận hành

- Khi CI luôn đỏ, không ai phân biệt được "đỏ cũ" với một lỗ hổng mới thật sự. Đã phải duyệt merge GAP-126 trong tình trạng đỏ.
- Cảnh báo 1 — Laravel (mức thấp): đã có bản sửa (12.71.0 trở lên). ZENA không dùng chức năng bị lỗi.
- Cảnh báo 2 — picomatch (mức cao): chỉ chạy lúc build giao diện trên máy dev và CI, không có trên máy chủ. **Chưa có bản sửa nào** được phát hành.

## Người dùng bị ảnh hưởng

Không ai trong công ty. Ảnh hưởng là người duyệt PR (Owner) và độ tin cậy của CI.

## Bằng chứng

`docs/audits/2026-11-18-gap-127-dependency-advisories-evidence.md`: danh sách từng cảnh báo, gói nào kéo vào, có chạy trên production không, lần chạy CI nào bắt đầu đỏ.

## Phạm vi đề xuất

Gate 2 sẽ đề xuất cho từng gói:
- Laravel: nâng lên bản 12.x mới (vẫn trong `^12.0`).
- picomatch: ghi ngoại lệ có lý do vào `.npm-audit-ignore` cho đến khi có bản sửa, kèm cách gỡ ngoại lệ sau này.

Xong = kiểm tra bảo mật xanh trên PR, toàn bộ test Unit+Feature+Integration không đổi.

## Loại trừ rõ ràng

- Nâng Laravel lên 13.
- Hạ cấp vite / laravel-vite-plugin.
- Sửa cách CI chấm điểm cảnh báo.
- Deploy (chỉ sau Gate 3, qua `Production Deployment` theo `docs/runbooks/go-live.md`).

## Khả năng hoàn tác

Revert PR: `composer.lock` và một file ngoại lệ.

## Decision Needed

Owner chọn một: `APPROVE GAP-127 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Phương án nâng cấp (Gate 2), merge, deploy.
