---
work_id: GAP-080
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-15-gap-080-code-style-evidence.md
  plan: null
  branch: docs/GAP-080-code-style
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-15T09:11:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-15T09:11:00+07:00"
  updated_at: "2026-10-15T09:11:00+07:00"
generated_by: agent
---

## Owner Summary

Theo cấu hình `.php-cs-fixer.php` của dự án, **1696/1776** file PHP chưa đúng
code style. Phần lớn chỉ là khoảng trắng, dòng trống, thứ tự `use`, dấu phẩy
cuối. Một số ít luật "risky" có thể đổi nội dung: xoá khoảng trắng cuối dòng
bên trong chuỗi (33 file, chủ yếu câu SQL), đổi docblock thành comment (15
file), `self` thay tên lớp (7 file)...

Hậu quả: gần như PR nào sửa PHP cũng bị bot báo lỗi style có sẵn (GAP-078,
GAP-079 đều bị), và không có check nào giữ cho style không xấu thêm.

Đề xuất: phê duyệt Gate 1 để thiết kế cách định dạng lại an toàn.

## Vấn đề vận hành

Báo cáo style trên PR lẫn lỗi cũ, khó thấy lỗi mới; review khó hơn.

## Người dùng bị ảnh hưởng

Người review và agent làm PR. Không ảnh hưởng người dùng ứng dụng nếu chỉ đổi
định dạng.

## Phạm vi đề xuất

Gate 2 thiết kế: định dạng lại một lần hay theo từng thư mục; có loại các
luật risky ra không; thêm `.git-blame-ignore-revs`; có thêm check CI chặn
style mới hay không; cách kiểm chứng không đổi hành vi.

## Loại trừ rõ ràng

Không đổi logic, không nâng gói, không sửa cảnh báo PHPStan; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, định dạng code, merge, phát hành hay deploy.
