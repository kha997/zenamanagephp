---
work_id: GAP-015
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-gap-015-worktemplate-ui-owner-evidence.md
  plan: null
  branch: docs/GAP-015-worktemplate-ui-owner
  pr: https://github.com/kha997/zenamanagephp/pull/387
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T18:20:19+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE GAP-015 Gate 1'. Bound to reviewed Draft PR #387 head 206db03d42131931af912c3bb63b7499adb99d91, canonical base 2e3ba4b860f1525aa5712fd477614189c9a101f0. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T17:07:05+07:00"
  updated_at: "2026-10-29T18:20:19+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-015 Gate 1 in-session on 2026-10-29 against Draft PR #387 head
`206db03d42131931af912c3bb63b7499adb99d91`. This authorizes preparation of Gate 2 only.

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
