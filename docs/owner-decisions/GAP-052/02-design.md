---
work_id: GAP-052
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-09-11-gap052-dashboard-widget-contract-design.md
  plan: null
  branch: design/GAP-052-dashboard-widget-contract
  pr: https://github.com/kha997/zenamanagephp/pull/311
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
  created_at: "2026-09-11T00:00:00+07:00"
  updated_at: "2026-09-11T00:00:00+07:00"
generated_by: agent
---

## Owner Summary

Giữ hai API dashboard theo vai trò vì đây là surface đang được bật và đã được tài liệu hóa. Hợp đồng lấy dữ liệu widget sẽ thuộc một lớp provider/resolver rõ ràng; không thêm tùy tiện `getWidgetData()` vào service chỉ tổng hợp dữ liệu theo vai trò.

## Trước / Sau

**Trước:**

1. API root và `/widgets` chọn mã widget theo role rồi gọi service tổng hợp role.
2. Các mã widget không nằm trong switch cục bộ rơi vào một method không tồn tại và trả 500.
3. Test double cho phép method không có thật nên có thể xanh giả.

**Sau:**

1. Hai API tiếp tục được hỗ trợ với cùng đường dẫn và outer response contract.
2. Role service điều phối; provider/resolver widget là nơi duy nhất quyết định mã widget nào lấy dữ liệu thế nào.
3. Mã widget không có provider bị từ chối bằng lỗi ổn định, không lộ chi tiết nội bộ.
4. Kiểm thử dùng dependency thật, token Bearer thật, đủ bảy vai trò, tenant và RBAC.

## Vai trò bị ảnh hưởng

Áp dụng cho `system_admin`, `project_manager`, `design_lead`, `site_engineer`, `qc_inspector`, `client_rep`, và `subcontractor_lead`. Mỗi role phải có catalog/capability rõ ràng; không role nào được âm thầm rơi vào catalog của role khác.

## Được phép / Không được phép

Người dùng chỉ nhận dữ liệu widget active, cùng tenant, đúng RBAC và đúng project context. Không được truy cập widget hoặc project tenant khác; không được biến mã widget chưa hỗ trợ thành dữ liệu rỗng giả hoặc trả stack trace.

## Trạng thái và bước tiếp theo

Catalog rỗng hoặc widget không eligible: trả danh sách hợp lệ, có thể rỗng. Widget eligible nhưng chưa có provider: trả lỗi domain ổn định để đội vận hành sửa cấu hình. Provider lỗi tạm thời: trả lỗi retryable chỉ khi dependency thực sự retryable. Sau Gate 2 approval mới được lập implementation plan.

## Ngoại lệ

Không coi `DashboardDataAggregationService`, `RealTimeDashboardService`, hay mock test hiện tại là provider widget. `DashboardService` là nguồn tương thích để trích/adapt, nhưng không được promotion mù quáng nếu chưa rà tenant/query/cache.

## Hành vi người dùng nhìn thấy

Dashboard hợp lệ tiếp tục tải như trước. Dashboard có cấu hình widget không được hỗ trợ sẽ nhận thông báo lỗi chung có mã ổn định; người dùng không thấy tên class, method hay stack trace. Chi tiết được giữ ở log với error ID.

## Kịch bản chấp nhận

- Given người dùng có một trong bảy role và token Bearer thật, when gọi root hoặc `/widgets`, then hệ thống dùng dependency thật và trả widget data đúng tenant/RBAC.
- Given tenant không có widget active eligible, when gọi `/widgets`, then hệ thống trả 200 với danh sách rỗng.
- Given active eligible widget có mã chưa có provider, when gọi root hoặc `/widgets`, then hệ thống trả `DASHBOARD.WIDGET_UNSUPPORTED` theo error envelope an toàn, không trả 500 detail nội bộ tùy ý và không trả dữ liệu giả.
- Given token không có role/permission hoặc project thuộc tenant khác, when gọi API, then hệ thống từ chối trước provider và không lộ dữ liệu.
- Given tenant A và tenant B có widget cùng mã, when actor tenant A gọi, then chỉ dữ liệu/cache của tenant A được đọc.
- Given mỗi role trong bảy role, when chạy bộ tích hợp, then role đó có kết quả catalog/provider được xác định rõ; không role nào phụ thuộc mock method vắng mặt.

## Loại trừ phạm vi

Không triển khai code, không sửa test ở Gate 2, không retire route, không đại tu error envelope toàn hệ thống, không sửa toàn bộ role-summary mocks/stubs, không đổi schema hoặc data production, và chưa lập implementation plan.

## Decision Needed

Owner chọn một: Approve to proceed to implementation / Request changes to the design / Decline.

## What the owner is NOT being asked to decide

Owner không được yêu cầu duyệt tên class, schema, cách cache hay câu SQL cụ thể. Owner chỉ được yêu cầu duyệt việc giữ hai surface, quyền sở hữu hợp đồng provider/resolver, quy tắc unsupported widget, bảy role, tenant/RBAC, error scope và tiêu chí kiểm thử.
