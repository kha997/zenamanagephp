---
work_id: GAP-052
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-11-gap-052-role-dashboard-widget-contract-evidence.md
  plan: null
  branch: audit/GAP-052-dashboard-contract
  pr: https://github.com/kha997/zenamanagephp/pull/310
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
  created_at: "2026-09-11T17:12:27+07:00"
  updated_at: "2026-09-11T17:45:04+07:00"
generated_by: agent
---

## Owner Summary

Hai API dashboard theo vai trò đang hoạt động có thể trả lỗi 500 thật cho
Client Representative khi tenant có widget phù hợp. Lỗi đã được tái hiện
bằng đăng nhập và token thật trên đúng luồng xác thực, tenant và phân quyền.

## Vấn đề vận hành

Khi người dùng mở dashboard theo vai trò, hệ thống tải các widget mặc định
của vai trò đó. Với một số mã widget, hệ thống chuyển tiếp việc lấy dữ liệu
đến nhầm dịch vụ, nên request dừng bằng lỗi 500 thay vì trả dashboard. Ở dữ
liệu tái hiện hiện tại, Client Representative gặp lỗi trên cả trang tổng hợp
dashboard và danh sách widget.

## Người dùng bị ảnh hưởng

- Client Representative: đã xác nhận bằng chạy thật trên hai API bị ảnh
  hưởng.
- Các vai trò dashboard khác cũng có mã widget có thể đi vào cùng nhánh lỗi
  nếu tenant của họ có đúng widget đang hoạt động và người dùng được phép xem.
- Không đủ quyền truy cập production để biết tenant nào đang có các widget
  đó hoặc tần suất người dùng gặp lỗi.

## Bằng chứng

Đội đã chạy đúng route thật với đăng nhập thật, Bearer token thật, kiểm tra
tenant và RBAC thật. Khi kỳ vọng phản hồi thành công, hệ thống trả 500 và xác
nhận dịch vụ được gọi không có chức năng mà bên gọi yêu cầu. Kiểm tra lịch sử
cũng cho thấy route được bật cùng thời điểm một mock test cho phép chức năng
không tồn tại, nên bộ test có thể xanh trong khi class thật không đáp ứng hợp
đồng. Toàn bộ lệnh chạy, call graph và bằng chứng file nằm trong tài liệu audit
được liên kết ở frontmatter.

## Tác động nếu không xử lý

Người dùng đủ quyền có thể không tải được dashboard hoặc danh sách widget;
lỗi xảy ra lặp lại mỗi khi dữ liệu widget phù hợp tồn tại. Phản hồi 500 hiện
tại còn có thể để lộ tên class và method nội bộ. Không phát hiện truy cập chéo
tenant hay vượt RBAC trong lần tái hiện này.

## Phạm vi đề xuất

Cho phép mở Gate 2 để xác định dashboard theo vai trò có phải surface sản phẩm
cần giữ hay không; nếu giữ, xác định đúng nơi sở hữu hợp đồng lấy dữ liệu
widget, hành vi đối với mã widget không được hỗ trợ, nguồn cấu hình vai trò
chuẩn, và bộ tiêu chí kiểm thử bắt buộc cho xác thực thật, tenant, RBAC và bảy
vai trò. Gate 2 phải so sánh các lựa chọn trước khi chọn thiết kế.

## Loại trừ rõ ràng

- Không thêm method vào bất kỳ service nào ở Gate 1.
- Không đổi dependency injection, route, controller, middleware, cấu hình,
  schema, cache, dữ liệu hay test production.
- Không viết implementation plan và không chọn trước phương án Gate 2.
- Không mở rộng sang sửa toàn bộ error-envelope; chỉ ghi nhận rủi ro lộ thông
  tin gắn trực tiếp với lỗi này.
- Không tự duyệt, merge, deploy hoặc thay đổi dữ liệu production.

## Đề xuất

Đề xuất Owner phê duyệt chuyển sang Gate 2 vì lỗi 500 đã được tái hiện trên
surface production thật, nhưng quyền sở hữu hợp đồng giữa các service cần
được thiết kế có chủ đích trước khi sửa.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn class, method, dependency injection hay cách
viết test cụ thể; cũng không được yêu cầu phê duyệt việc thêm
`getWidgetData()` vào `DashboardDataAggregationService`. Gate 1 chỉ hỏi vấn
đề có thật, quan trọng và đáng mở Gate 2 để thiết kế hay không.
