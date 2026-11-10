---
work_id: GAP-123
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-11-gap-123-work-template-authoring-evidence.md
  plan: null
  branch: docs/GAP-123-work-template-authoring
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-11T01:41:51+07:00"
  updated_at: "2026-11-11T01:41:51+07:00"
generated_by: agent
---

## Owner Summary

Thêm **trang soạn mẫu công việc** trong phần mềm, để công ty tự lưu quy trình quen làm thành mẫu rồi áp dụng cho dự án mới.

**Hiện trạng:**
- Áp dụng mẫu vào dự án đã chạy tốt (trang dự án → "Áp dụng mẫu công việc"). Mỗi bước trong mẫu thành một việc của dự
  án, có hạn, thứ tự, checklist và tài liệu cần nộp.
- **Không có trang nào để tạo hay sửa mẫu.** Chỉ tạo được qua API (cần lập trình viên).
- Mỗi công ty chỉ có **3 mẫu chuẩn bằng tiếng Anh**: "Design", "Construction", "Inspection". Áp dụng thì việc cũng ra tên
  tiếng Anh, ví dụ "Develop and Coordinate Design Package".
- Phần lưu nháp, phát hành và giữ phiên bản cũ đã có sẵn bên dưới, chỉ thiếu màn hình.

**Hướng làm (Gate 2 sẽ chọn):**
- Trang danh sách mẫu.
- Trang soạn một mẫu: danh sách các bước, mỗi bước có tên, loại, làm sau bước nào, số ngày, mô tả, mức ưu tiên, nhóm
  (giai đoạn), checklist, tài liệu cần nộp.
- Lưu nháp, xem trước, phát hành.
- Có thể nhân bản mẫu có sẵn, và dịch 3 mẫu chuẩn sang tiếng Việt.
- Chọn ai được soạn và phát hành mẫu.

## Vấn đề vận hành

Công ty làm nhiều nhà có cùng trình tự, ví dụ khảo sát → thiết kế sơ bộ → báo giá → thi công móng. Hiện mỗi dự án mới phải
tạo lại từng việc bằng tay, hoặc dùng mẫu tiếng Anh không đúng cách làm của công ty.

## Người dùng bị ảnh hưởng

- **Owner / quản trị:** soạn và phát hành mẫu.
- **PM:** áp dụng mẫu cho dự án; trang áp dụng không đổi.

## Bằng chứng

`docs/audits/2026-11-11-gap-123-work-template-authoring-evidence.md`.

## Phạm vi đề xuất

Gate 2:
- trang web soạn mẫu, dùng phần lưu và phiên bản sẵn có;
- quyền soạn / phát hành;
- điểm xuất phát (mẫu trống, nhân bản, bản tiếng Việt của 3 mẫu chuẩn).

## Loại trừ rõ ràng

- Đổi cách áp dụng mẫu vào dự án.
- Mẫu tài liệu.
- Deploy.

## Khả năng hoàn tác

Revert PR. Mẫu đã tạo vẫn nằm trong cơ sở dữ liệu, dự án đã áp dụng không bị ảnh hưởng.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-123 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn phương án, Gate 2, merge.
