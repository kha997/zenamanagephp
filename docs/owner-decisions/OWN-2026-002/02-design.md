---
work_id: OWN-2026-002
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-06-operational-gap-remediation-program-design.md
  plan: null
  branch: docs/OWN-2026-002-operational-gap-remediation-program-design
  pr: https://github.com/kha997/zenamanagephp/pull/240
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-06T12:27:01+07:00"
  owner_response_reference: "ChatGPT project conversation — explicit Owner Gate 2 approval for OWN-2026-002 on 2026-08-06"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-06T12:04:00+07:00"
  updated_at: "2026-08-06T12:27:01+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

The owner approves the program structure, sequencing and governance boundaries described in the approved design.

This decision does not approve implementation of any individual gap. Each individual work item still requires its own Gate 1, Gate 2 and Gate 3.

## Owner Summary
Đây là thiết kế chương trình (không phải triển khai) cho việc xác minh, xếp ưu tiên và xử lý có kiểm soát các gap vận hành còn tồn đọng trong kho mã. Owner chỉ cần duyệt cấu trúc chương trình và thứ tự các đợt — không phê duyệt trước bất kỳ gap kỹ thuật cụ thể nào.

## Bức tranh gap đã xác minh
Đã xác minh lại 10 mục "chưa xác nhận" trong sổ đăng ký so với mã nguồn hiện tại trên `main`:
- **8 mục đã xác nhận sửa đúng và còn hiệu lực** (không phải chỉ tự khai): GAP-001 (rò rỉ dữ liệu chéo tenant), GAP-003 (nhầm lẫn bảng dữ liệu), GAP-004 (thiếu quyền tạo task), GAP-005 (SSRF qua webhook), GAP-006 (lỗi tạo trùng nhật ký công trường), GAP-007 (webhook thử lại sai số lần), GAP-008 (chèn ký tự đặc biệt vào tìm kiếm), GAP-009 (thiếu giới hạn tần suất tạo token).
- **2 mục chỉ sửa một phần**, phát hiện thêm lỗi thật đang mở khi kiểm tra kỹ:
  - GAP-010 (nhóm lỗi xuất báo cáo): đường xuất báo cáo mới đã sửa, nhưng còn một đường xuất CSV cũ (`ExportController::generateCsv()`, vẫn đang hoạt động) vẫn còn lỗi chèn công thức Excel độc hại và có thể hết bộ nhớ khi xuất file lớn — **lỗi thật, đang mở**. Ngoài ra, khi kiểm tra kỹ hơn, phát hiện một trang thật đang hoạt động (`/schedule`) có cách hiển thị ngày tháng có thể gây ra đúng loại lỗi lệch múi giờ mà audit cũ từng ghi nhận — **cần mở lại để xác nhận**, chưa đóng như dự kiến ban đầu.
  - GAP-014 (NCR/CAPA): phần bảng điều khiển đã hoạt động đúng, nhưng phần gửi thông báo tự động khi có NCR mới hoàn toàn không hoạt động (mã đã viết nhưng chưa từng được kích hoạt), và chưa có cách lưu liên kết lâu dài giữa NCR và công việc khắc phục — **cả hai đang mở**.

## Thứ tự các đợt được đề xuất
1. Chỉnh sửa lại sổ đăng ký gap cho đúng với kết quả xác minh (chỉ tài liệu, không đổi mã nguồn).
2. Sửa lỗi bảo mật/đúng đắn thật đang mở: GAP-010b (đường xuất CSV cũ).
3. Thiết kế GAP-032 (tách trạng thái chung của tài liệu khỏi trạng thái duyệt).
4. Sau khi GAP-032 được owner duyệt thiết kế, mới thiết kế GAP-033 (người duyệt được chỉ định riêng).
5. Các gap vận hành nhỏ, độc lập, ảnh hưởng trực tiếp người dùng (Wave 3): nộp lại hồ sơ vật tư bị từ chối, thông báo vật tư, thông báo khi áp dụng yêu cầu thay đổi, trang lời mời hết hạn, dọn route chết.
6. Các gap về quyền hạn và kiến trúc có phạm vi ảnh hưởng rộng hơn (Wave 4): tách quyền xử lý escalation RFI, dọn route debug, dọn file mồ côi, hợp nhất API tương thích cũ, sửa tài liệu kiến trúc.
7. Các phát hiện còn mở từ Wave 1 (GAP-014b thông báo NCR, GAP-014c liên kết NCR-task) — xử lý sau Wave 3 để dùng chung cách thiết kế thông báo đã quyết định.

Mỗi mục ở trên vẫn cần đi qua đầy đủ Gate 1/Gate 2/Gate 3 riêng — tài liệu này chỉ duyệt cấu trúc và thứ tự chương trình.

## Mục nào chỉ là xác minh (không đổi hành vi)?
Bước 1 (chỉnh sổ đăng ký) — chỉ tài liệu, không đổi mã nguồn, không đổi hành vi hệ thống.

## Mục nào đổi hành vi người dùng nhìn thấy?
GAP-010b (sửa lỗi xuất CSV), tất cả các mục trong Wave 3, GAP-032/033 (nếu được duyệt và triển khai), GAP-030 (ai được xử lý escalation), GAP-014b/c (nếu triển khai).

## Mục nào có thể cần thay đổi cấu trúc dữ liệu (migration)?
GAP-032, GAP-033, GAP-014c — cả ba đều dự kiến cần thêm bảng/cột mới. Mỗi mục phải trình kế hoạch hoàn tác migration riêng ở chính Gate 2 của nó trước khi triển khai.

## Mục nào đang tạm giữ, chưa xếp lịch?
GAP-015 (chưa có màn hình engine WorkTemplate được owner xác nhận — cần quyết định nghiệp vụ riêng trước); GAP-019 (vỏ demo/debug — không hồi sinh trừ khi owner yêu cầu); GAP-026 (bị chặn bởi yếu tố bên ngoài — cần xác nhận kênh Slack đích trước).

## Quyết định nào để dành cho từng hồ sơ gap riêng (không quyết định ở đây)?
- Thiết kế chi tiết trước/sau, quy tắc gán lại người duyệt, lịch sử kiểm toán của GAP-032/033.
- Bảng vai trò nào được xử lý escalation RFI của GAP-030 (đã có đề xuất trong tài liệu thiết kế, owner xác nhận hoặc sửa tại chính Gate 1/2 của GAP-030).
- Xác nhận GAP-010c (lỗi lệch múi giờ) có thật sự tái hiện được trên trang `/schedule` hay không.
- Quyết định nghiệp vụ riêng cho GAP-015.

## Nghiêm cấm gộp thành một PR khổng lồ
Mỗi mục triển khai có Work ID riêng, nhánh/worktree cô lập riêng, review độc lập riêng, gói Gate 3 riêng, PR riêng. Chỉ gộp chung PR khi hai gap chia sẻ đúng cùng một actor và cùng một ranh giới hoàn tác — có nêu rõ lý do trong tài liệu thiết kế, không gộp vì tiện.

## Ranh giới hoàn tác theo từng nhóm
- Các mục không có migration: hoàn tác bằng git revert thông thường.
- GAP-032/033/GAP-014c: cần kế hoạch hoàn tác migration cụ thể, trình tại Gate 2 riêng của từng mục.
- GAP-021 (nếu thay đổi API tương thích cũ): cần đánh giá rủi ro phá vỡ client bên ngoài tại thời điểm thiết kế.

## Decision Needed
Owner đã chọn: **Phê duyệt cấu trúc và thứ tự chương trình.**

Quyết định này CHỈ liên quan đến: cấu trúc chương trình, thứ tự các đợt, và ranh giới quản trị (mỗi gap vẫn cần Gate 1/2/3 riêng). Quyết định này KHÔNG phê duyệt trước bất kỳ việc triển khai gap cụ thể nào.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt trước bất kỳ gap kỹ thuật cụ thể nào trong danh sách — mỗi gap còn phải qua đầy đủ Gate 1/2/3 riêng khi tới lượt. Owner cũng không được yêu cầu quyết định về cách đặt tên lớp, cấu trúc thư mục làm việc, hay chi tiết kỹ thuật của công cụ kiểm tra — chỉ quyết định về cấu trúc chương trình và thứ tự các đợt.
