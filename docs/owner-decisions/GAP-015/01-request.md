---
work_id: GAP-015
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: null
  branch: docs/GAP-015-worktemplate-ui-owner
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
  created_at: "2026-10-29T17:07:05+07:00"
  updated_at: "2026-10-29T17:07:05+07:00"
generated_by: agent
---

## Owner Summary

Mẫu công việc (WorkTemplate) và phiên làm việc theo mẫu (WorkInstance) **đã có đủ API** (soạn, sửa nháp, phát hành, xoá,
nhập/xuất gói; xem / cập nhật / duyệt bước, đính kèm, xuất). Nhưng **giao diện web chỉ có 1 việc: chọn – xem trước – áp
dụng mẫu vào dự án.** Không có màn hình soạn mẫu, cũng không có màn hình làm việc theo các bước của mẫu.

Phát hiện thêm (seed mặc định): PM có quyền "tạo / sửa mẫu" nhưng API soạn mẫu đòi quyền khác (`template.edit_draft`) nên
PM không soạn được; PM và thành viên không có quyền `work.*` nên không xem / làm được phiên làm việc. Chỉ System Admin
dùng được toàn bộ.

## Vấn đề vận hành

Chức năng mẫu công việc chỉ dùng được qua API; người dùng web không soạn mẫu và không thực hiện được các bước công việc.

## Người dùng bị ảnh hưởng

Người soạn quy trình mẫu (QA/QC, kỹ thuật), PM áp dụng và theo dõi, kỹ sư thực hiện các bước.

## Bằng chứng

`docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md`: bảng API theo vòng đời, phần web hiện có, bảng quyền
theo role sau khi seed.

## Phạm vi đề xuất

Gate 2: anh/chị chọn xây màn hình nào (soạn mẫu / làm việc theo bước / cả hai / không xây — ghi nhận chỉ dùng API và đóng
GAP), role nào dùng, và làm phần nào trước.

## Loại trừ rõ ràng

Không đổi API hiện có; không deploy, không seed môi trường thật.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2 để chọn phạm vi.

## Decision Needed

Owner chọn một: `APPROVE GAP-015 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn màn hình, chưa duyệt Gate 2, merge hay phát hành.
