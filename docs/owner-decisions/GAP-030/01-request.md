---
work_id: GAP-030
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-030-rfi-resolve-escalation-evidence.md
  plan: null
  branch: docs/GAP-030-rfi-resolve-escalation
  pr: https://github.com/kha997/zenamanagephp/pull/386
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T14:47:40+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-030 Gate 1'. Bound to reviewed Draft PR #386 head f2c1d5f6966c9e8930a453edb99547107476e82c, canonical base 23a909459b8161c613bdceabc6d52029b33d440c. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T14:45:06+07:00"
  updated_at: "2026-10-29T14:47:40+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-030 Gate 1 in-session on 2026-10-29 against Draft PR #386 head
`f2c1d5f6966c9e8930a453edb99547107476e82c`. This authorizes preparation of Gate 2 only.

## Owner Summary

Khi một RFI được leo thang (escalate) cho một người, **người nhận leo thang không xử lý (resolve) được** nếu họ không có
quyền "leo thang" — vì API resolve đang dùng nhầm quyền `rfi.escalate`. Phần kiểm tra bên trong (chỉ người nhận, PM dự án
hoặc admin được resolve) đã đúng, nhưng bị chặn trước khi tới đó.

Phát hiện thêm: với dữ liệu mặc định, **role "Project Manager" không có quyền RFI nào** (seeder tìm tên
`project_manager`, role thật tên `Project Manager`), nên hiện chỉ System Admin leo thang / xử lý được.

## Vấn đề vận hành

Luồng leo thang RFI không khép được: người được giao xử lý không bấm được "đã xử lý"; PM cũng không.

## Người dùng bị ảnh hưởng

Người nhận leo thang RFI, PM dự án; gián tiếp người tạo RFI chờ phản hồi.

## Bằng chứng

`docs/audits/2026-10-29-gap-030-rfi-resolve-escalation-evidence.md`: route và controller, test hiện phải cấp quyền thừa
cho người nhận, bảng quyền RFI theo role sau khi seed mặc định.

## Phạm vi đề xuất

Gate 2: quyền riêng `rfi.resolve-escalation` cho API resolve; **anh/chị chọn role nào được giữ quyền này**; có sửa luôn
lỗi tên role PM trong seeder không.

## Loại trừ rõ ràng

Không đổi luật bên trong (người nhận / PM / admin); không thêm giao diện web; không deploy, không seed môi trường thật.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-030 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn role, chưa duyệt Gate 2, merge hay phát hành.
