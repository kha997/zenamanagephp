# Lịch thanh toán chi tiết trên cổng khách hàng (Goal #6 Slice) — Design Spec

Date: 2026-07-16
Status: chosen by orchestrator — audit xác nhận cổng khách hàng "purely read-only", và ngay cả phần đọc cũng tối giản: `PortalDashboardController` hiện chỉ tính 1 SỐ TỔNG "Số dư còn lại" (`outstandingBalance`), khách không thấy khoản nào đến hạn khi nào, bao nhiêu tiền mỗi đợt.

## Purpose

Hiện danh sách chi tiết từng đợt thanh toán (tên đợt/số tiền/hạn/trạng thái) trên dashboard cổng khách hàng — thay vì chỉ 1 con số tổng, giảm khách phải hỏi qua Zalo "tôi còn nợ bao nhiêu, khi nào tới hạn".

## Verified integration facts

- `App\Http\Controllers\Web\Portal\PortalDashboardController::index()` (dòng ~20-84): đã có sẵn `$contracts` (Collection Contract đã lọc theo `project_id` thuộc khách) và tính `$outstandingBalance` từ `ContractPayment` — CHỈ dùng `sum('amount')`, không lấy danh sách dòng.
- `App\Models\ContractPayment`: fillable `tenant_id`, `contract_id`, `name`, `amount`, `due_date`, `status`, `paid_at`; hằng số `STATUS_PLANNED`/`STATUS_PAID`/`STATUS_OVERDUE`.
- View `resources/views/portal/dashboard.blade.php` dòng ~115: `<x-ui.field-value label="Số dư còn lại" ...>` — nơi cần mở rộng thành bảng chi tiết ngay cạnh/dưới field này.
- Route/controller đã có tenant+account scoping chuẩn qua `$projectIds` (từ `Opportunity::converted_project_id` thuộc `account_id` của khách đang đăng nhập) — TÁI DÙNG nguyên xi, không viết lại logic scoping.
- Pattern hiển thị bảng trên portal: xem `portal/quote.blade.php` hoặc `portal/dashboard.blade.php` phần danh sách dự án/tài liệu hiện có làm mẫu style bảng.
- KHÔNG có hành động ghi mới (chỉ đọc) — không cần permission/route mới, không đụng RBAC.

## Design

Trong `PortalDashboardController::index()`, thay thế việc chỉ tính tổng bằng lấy CẢ danh sách + tổng (không phá `outstandingBalance` đang dùng — giữ nguyên key, thêm key mới):

```php
$paymentSchedule = ContractPayment::query()
    ->where('tenant_id', $tenant->id)
    ->whereIn('contract_id', $contracts->pluck('id'))
    ->where('status', '!=', ContractPayment::STATUS_PAID)
    ->orderBy('due_date')
    ->get(['id', 'contract_id', 'name', 'amount', 'due_date', 'status']);
```

Truyền `paymentSchedule` vào view cạnh `outstandingBalance` hiện có.

## UI

`resources/views/portal/dashboard.blade.php`: ngay dưới field "Số dư còn lại", thêm bảng "Các đợt thanh toán còn lại" (nếu `$paymentSchedule` rỗng thì ẩn khối, không hiện bảng trống) — cột: Đợt (tên `name`)/Số tiền (`number_format`)/Hạn thanh toán (`due_date` format `d/m/Y`)/Trạng thái (badge: `planned`→"Chưa đến hạn" màu vàng nhạt, `overdue`→"Quá hạn" màu đỏ — so sánh `due_date < now()` để tô đỏ ĐÚNG THỜI ĐIỂM XEM chứ không chỉ dựa cột `status` lưu sẵn có thể lỗi thời, dùng logic tương tự `overdue` trong `outstandingDebt()` của `BusinessKpiService` — `where('due_date', '<', now())`).

## Error handling

Không có input mới — không có error case. Nếu khách không có hợp đồng nào (`$contracts` rỗng), `$paymentSchedule` tự động rỗng, khối UI ẩn hoàn toàn (không hiện "Không có dữ liệu" gây rối).

## Testing

Mở rộng test hiện có của `PortalDashboardTest` (tìm bằng grep `PortalDashboardTest` trong `tests/Feature/Portal/`): seed 3 `ContractPayment` (1 `planned` tương lai, 1 `planned` quá hạn theo `due_date` quá khứ, 1 `paid`) → assert response thấy đúng 2 dòng chưa-paid (không thấy dòng đã `paid`), đúng số tiền định dạng, dòng quá hạn hiện badge "Quá hạn"; cross-account (payment thuộc contract của khách khác) không hiện; `outstandingBalance` (key cũ) vẫn đúng như trước — regression trong cùng test file.

## Out of scope

Thanh toán online qua cổng (chỉ xem, không thao tác thanh toán), tải biên lai/hóa đơn, nhắc lịch qua email, lọc/tìm kiếm trong bảng đợt thanh toán (danh sách thường ngắn, không cần phân trang/filter cho slice này).
