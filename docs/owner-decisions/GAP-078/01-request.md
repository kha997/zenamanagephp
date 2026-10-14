---
work_id: GAP-078
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-14-gap-078-unrouted-sidebar-builders-evidence.md
  plan: null
  branch: docs/GAP-078-unrouted-sidebar-builders
  pr: https://github.com/kha997/zenamanagephp/pull/351
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T20:36:20+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-14, verbatim: 'APPROVE GAP-078 Gate 1'. Bound to reviewed Draft PR #351 head 4abb6d6aa809debca1567c3be0552ba1fd50a11f, canonical base 043e182176dbbb60e536a818b4ccafa6d967688d. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T20:35:06+07:00"
  updated_at: "2026-10-14T20:36:20+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-078 Gate 1 in-session on 2026-10-14 against Draft PR #351
head `4abb6d6aa809debca1567c3be0552ba1fd50a11f`. This authorizes preparation of Gate 2 only.

## Owner Summary

Hai controller `Admin/SimpleSidebarBuilderController` và
`Admin/SidebarBuilderController` **không có route nào dùng**, kéo theo 4 view
chỉ chúng dùng (`sidebar-builder`, `sidebar-builder-edit`,
`sidebar-preview`, `sidebar-builder-content`) — tổng khoảng 2.000 dòng.
Đường dẫn `/admin/sidebar-builder` hiện chỉ trả về một dòng chữ tạm, không
dùng các file này. API quản lý sidebar thật (`Api\SidebarConfigController`)
không liên quan.

Đề xuất: phê duyệt Gate 1 để thiết kế việc xoá 6 file này, các dòng PHPStan
baseline tương ứng, và sửa một dòng chú thích trong test đang nhắc tới file.

## Vấn đề vận hành

Code chết gây nhầm (3 nơi cùng tên "sidebar builder"), tốn công bảo trì và
review.

## Người dùng bị ảnh hưởng

Không ai — không route nào gọi tới.

## Phạm vi đề xuất

Gate 2 thiết kế: xoá 2 controller + 4 view, bỏ dòng baseline, cập nhật chú
thích trong `AudChangeRequestPermissionSeedingTest` (chỉ chú thích).

## Loại trừ rõ ràng

Route `/admin/sidebar-builder` (giữ nguyên), `PermissionService`,
`SidebarConfigController`, `SidebarConfig`, `src/`, deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, xoá file, merge, phát hành hay deploy.
