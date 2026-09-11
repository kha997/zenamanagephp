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
3. Mã widget không có provider không làm hỏng toàn bộ dashboard khi có thể trả partial response an toàn; widget đó giữ vị trí với trạng thái degraded, mã `DASHBOARD.WIDGET_UNSUPPORTED`, không có dữ liệu giả hoặc chi tiết nội bộ.
4. Kiểm thử dùng dependency thật, token Bearer thật, đủ bảy vai trò, tenant và RBAC.

## Vai trò bị ảnh hưởng

Áp dụng cho `system_admin`, `project_manager`, `design_lead`, `site_engineer`, `qc_inspector`, `client_rep`, và `subcontractor_lead`. Mỗi role phải có catalog/capability rõ ràng; không role nào được âm thầm rơi vào catalog của role khác.

## Được phép / Không được phép

Người dùng chỉ nhận dữ liệu widget active, cùng tenant, đúng RBAC và đúng project context. Không được truy cập widget hoặc project tenant khác; không được biến mã widget chưa hỗ trợ thành dữ liệu rỗng giả hoặc trả stack trace. Khi còn có thể dựng response an toàn, lỗi được giới hạn ở từng widget; chỉ khi không thể dựng response an toàn mới dùng lỗi 5xx ở cấp request.

## Trạng thái và bước tiếp theo

Catalog rỗng hoặc widget không eligible: trả danh sách hợp lệ, có thể rỗng. Với `include_data=false`, không cần provider và không được fail vì provider vắng mặt. Widget eligible nhưng chưa có provider: giữ entry và trả trạng thái degraded/error `DASHBOARD.WIDGET_UNSUPPORTED` nếu có thể trả partial response an toàn. Provider lỗi tạm thời cũng là lỗi theo từng widget khi có thể; request-level 5xx chỉ dùng khi không thể hoàn tất an toàn. Sau Gate 2 approval mới được lập implementation plan.

## Ngoại lệ

Không coi `DashboardDataAggregationService`, `RealTimeDashboardService`, hay mock test hiện tại là provider widget. `DashboardService` là nguồn tương thích để trích/adapt, nhưng không được promotion mù quáng nếu chưa rà tenant/query/cache.

## Hành vi người dùng nhìn thấy

Dashboard hợp lệ tiếp tục tải như trước. Dashboard có một widget không được hỗ trợ vẫn nhận được các widget hợp lệ; entry lỗi hiển thị trạng thái degraded với mã ổn định `DASHBOARD.WIDGET_UNSUPPORTED`, không có dữ liệu giả. Người dùng không thấy tên class, method hay stack trace; chi tiết được giữ ở log với error ID. Request-level 5xx chỉ xuất hiện khi không thể tạo response an toàn.

## Kịch bản chấp nhận

- Given người dùng có một trong bảy role và token Bearer thật, when gọi root hoặc `/widgets`, then hệ thống dùng dependency thật và trả widget data đúng tenant/RBAC.
- Given tenant không có widget active eligible, when gọi `/widgets`, then hệ thống trả 200 với danh sách rỗng.
- Given một active eligible widget có mã chưa có provider cùng với widget được hỗ trợ, when gọi root hoặc `/widgets` với `include_data=true`, then hệ thống trả partial response an toàn ở outer status thành công; entry không được hỗ trợ có `state: degraded`, `error.code: DASHBOARD.WIDGET_UNSUPPORTED`, không có dữ liệu giả và không có chi tiết nội bộ, còn widget được hỗ trợ vẫn trả dữ liệu thật.
- Given mọi active eligible widget đều chưa có provider, when gọi root hoặc `/widgets` với `include_data=true`, then hệ thống vẫn trả response an toàn gồm các entry degraded; không ép thành dữ liệu rỗng giả và không dùng request-level 5xx nếu response có thể dựng được.
- Given active eligible widget có mã chưa có provider, when gọi root hoặc `/widgets` với `include_data=false`, then hệ thống trả catalog/metadata hợp lệ mà không invoke hoặc require provider và không fail vì provider vắng mặt.
- Given token không có role/permission hoặc project thuộc tenant khác, when gọi API, then hệ thống từ chối trước provider và không lộ dữ liệu.
- Given tenant A và tenant B có widget cùng mã, when actor tenant A gọi, then chỉ dữ liệu/cache của tenant A được đọc.
- Given mỗi role trong bảy role, when chạy bộ tích hợp, then role đó có kết quả catalog/provider được xác định rõ, gồm cả safe partial/degraded semantics; không role nào phụ thuộc mock method vắng mặt.

## Loại trừ phạm vi

Không triển khai code, không sửa test ở Gate 2, không retire route, không đại tu error envelope toàn hệ thống, không sửa toàn bộ role-summary mocks/stubs, không đổi schema hoặc data production, và chưa lập implementation plan.

## Decision Needed

Owner chọn một: Approve to proceed to implementation / Request changes to the design / Decline.

## What the owner is NOT being asked to decide

Owner không được yêu cầu duyệt tên class, schema, cách cache hay câu SQL cụ thể. Owner chỉ được yêu cầu duyệt việc giữ hai surface, quyền sở hữu hợp đồng provider/resolver, quy tắc unsupported widget, bảy role, tenant/RBAC, error scope và tiêu chí kiểm thử.
