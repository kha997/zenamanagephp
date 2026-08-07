---
work_id: GAP-010b
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md
  plan: null
  branch: docs/GAP-010b-legacy-csv-export-safety
  pr: https://github.com/kha997/zenamanagephp/pull/243
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: "ChatGPT project conversation — Owner Gate 2 review round 1 (CHANGES REQUESTED) for GAP-010b on 2026-08-07; this packet is the re-presentation closing all round-1 findings"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T07:12:43+07:00"
  updated_at: "2026-08-07T16:15:33+07:00"
generated_by: agent
---

## OWNER GATE 2: RE-PRESENTED AFTER CHANGES REQUESTED (round 1)

Đội kỹ thuật trình lại thiết kế Gate 2 cho GAP-010b sau khi owner yêu cầu chỉnh sửa (review round 1, 2026-08-07). Chưa có mã nguồn nào được sửa, chưa có implementation plan nào được tạo. Bản thiết kế đầy đủ nằm tại `docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md`.

## Owner Gate 2 review round 1 — CHANGES REQUESTED — cách đóng từng phát hiện

1. **Bounded-memory cho project/tasks:** Thiết kế cũ (`Project::with(['tasks'])` theo chunk) không thật sự giới hạn bộ nhớ — một project có rất nhiều task vẫn tải toàn bộ task đó vào RAM. **Đã sửa:** dùng `withCount()` (aggregate ở tầng database), lấy đúng số liệu "Tổng task"/"Task hoàn thành" mà **không bao giờ** tải bất kỳ model `Task` nào — dùng đúng mẫu đã có sẵn và đang chạy thật trong `AnalyticsController.php:152-154`.
2. **Serialization của `tags`:** Đã xác minh `tags` cast thành `array` trên model. **Đã chốt:** dùng đúng quy ước có sẵn trong hệ thống (`Document::getTagsAsString()`: `implode(', ', $tags)`), không phát minh định dạng mới. Đã phát hiện thêm: mã hiện tại đang có lỗi thật (PHP tự chuyển mảng thành chuỗi `"Array"`) — thiết kế mới đóng lỗi này.
3. **Phạm vi sửa import `Illuminate\Http\Request` còn thiếu:** Owner đã quyết định ngay tại Gate 2 này: **có**, thuộc phạm vi implementation của GAP-010b. Việc này KHÔNG có nghĩa route được phép hoạt động trên production trước khi GAP-034 hoàn tất — phạm vi implementation và phạm vi phát hành (release) là hai quyết định tách biệt.
4. **Formula-neutralization phải phân biệt loại dữ liệu:** Quy tắc cũ vô tình biến mọi số âm (ví dụ `progress_percent = -5`) thành text chỉ vì bắt đầu bằng dấu `-`. **Đã sửa:** chỉ áp dụng vô hiệu hoá công thức cho cột dạng văn bản/người dùng nhập; cột số, null, ngày tháng giữ nguyên ý nghĩa gốc, dựa trên loại dữ liệu đã biết trước của cột, không dựa vào ký tự đầu chuỗi sau khi convert.
5. **CSV compatibility (EOL, BOM, cách so sánh):** Đã chốt dứt điểm, không để ngỏ cho implementation tự chọn: giữ nguyên `\n` (không đổi CRLF), không thêm BOM, hợp đồng tương thích là "đúng giá trị logic + đúng thứ tự cột/tiêu đề" (không phải giống byte-từng-byte, trừ đúng dòng tiêu đề).
6. **Số dòng trả về + tránh file dở dang:** Đã chốt: đếm số dòng thực sự đã ghi thành công (không đếm trên collection đầy đủ cũ); ghi vào file tạm trước, chỉ công bố file cuối sau khi xong hoàn toàn, xoá file tạm nếu lỗi — không bao giờ trả về "thành công" cho file dở dang.

Đồng thời: bỏ hẳn việc eager-load quan hệ `assignments` ở đường xuất task (đã xác minh không được dùng ở đâu trong việc tạo file CSV) — không mang theo dữ liệu thừa vào bộ nhớ.

## Trước / Sau (cập nhật)

**Trước:** `POST /tasks/bulk/export` và `POST /projects/bulk/export` không tách bạch escaping CSV với vô hiệu hoá công thức, dựng toàn bộ dữ liệu trong bộ nhớ ở nhiều tầng (kể cả tầng project→tasks nghiêm trọng hơn đã phát hiện), có lỗi thật khi xuất cột `tags` (mảng bị ép thành chữ "Array"), thiếu import khiến route không gọi được, không lọc theo tenant (GAP-034).

**Sau (thiết kế đã chốt, chờ owner quyết định phát hành ở Gate 3):** công thức bảng tính bị vô hiệu hoá đúng loại dữ liệu (không đụng vào số/null/ngày); bộ nhớ có giới hạn thật sự ở mọi tầng cho cả hai đường xuất, kể cả trường hợp một project có rất nhiều task; `tags` xuất đúng theo quy ước có sẵn của hệ thống; import được thêm vào (thuộc phạm vi implementation) nhưng route vẫn **không được khôi phục hoạt động trên production cho tới khi GAP-034 hoàn tất và xác minh xong**.

## Owner cần quyết định (không phải kỹ thuật)

- **Chấp nhận toàn bộ thiết kế đã chốt ở các mục 1–6 trên không?**
- **Xác nhận: import fix thuộc phạm vi implementation của GAP-010b, nhưng release vẫn bị chặn bởi GAP-034 — đúng như owner đã quyết định.**

Xem đầy đủ phân tích kỹ thuật, quyết định thiết kế, tiêu chí chấp nhận, và kế hoạch kiểm thử tại bản thiết kế đính kèm.

## Trạng thái và bước tiếp theo

Gate 1 đã duyệt → **Gate 2 đang chờ owner (bước này, sau khi đóng round 1 changes-requested)** → Gate 3 (chưa bắt đầu, chưa được phép). PR #243 vẫn là Draft, chưa merge, chưa có mã nguồn nào thay đổi, chưa có implementation plan nào được tạo.

## Ngoại lệ

GAP-010c, GAP-034 (work item riêng, hard blocker — xem thiết kế đính kèm) và các gap khác không thuộc phạm vi quyết định này.
