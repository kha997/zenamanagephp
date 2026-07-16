# Công nợ theo tuổi nợ 30/60/90 ngày (Goal #5 Slice) — Design Spec

Date: 2026-07-16
Status: chosen by orchestrator — audit 12/07 xác nhận: `BusinessKpiService::outstandingDebt()` có tổng công nợ + tổng quá hạn nhưng KHÔNG phân theo mức độ quá hạn (30/60/90+ ngày) — chủ doanh nghiệp không biết khoản nào cấp bách nhất.

## Purpose

Mở rộng KPI công nợ hiện có thêm breakdown theo tuổi nợ (aging buckets): chưa đến hạn / 1-30 ngày / 31-60 ngày / 61-90 ngày / trên 90 ngày — hiện trên trang báo cáo kinh doanh CRM, giúp ưu tiên thu hồi công nợ đúng thứ tự khẩn cấp.

## Verified integration facts

- `App\Services\BusinessKpiService::outstandingDebt(string $tenantId): array` (dòng ~60-77) đã có, cache `Cache::remember(..., 60, ...)`, dựa trên `App\Models\ContractPayment` (`amount`, `due_date`, `status != STATUS_PAID`).
- Tiêu thụ tại `App\Http\Controllers\Web\CrmReportController` (dòng ~18: `'outstandingDebt' => $kpiService->outstandingDebt($tenantId)`) → `resources/views/crm/report.blade.php` (dòng ~34-36, dùng `<x-ui.field-value>`).
- Test hiện có: `tests/Unit/Services/BusinessKpiServiceTest.php::test_outstanding_debt_separates_overdue_from_total` (dòng ~95-140) — pattern tạo `Project`→`Contract`→`ContractPayment` với `due_date` khác nhau để test.
- `ContractPayment::STATUS_PAID` là hằng số loại trừ khỏi "unpaid" — các status khác (`STATUS_PLANNED` và các status chưa-paid khác) đều tính là công nợ treo.

## Design

Mở rộng RETURN của `outstandingDebt()` (KHÔNG đổi chữ ký tham số, KHÔNG xóa key cũ — `total`/`overdue_total`/`overdue_count` giữ nguyên để không phá code đang dùng) thêm key `aging`:

```php
/**
 * @return array{
 *   total: float, overdue_total: float, overdue_count: int,
 *   aging: array{
 *     not_due: float,
 *     due_1_30: float,
 *     due_31_60: float,
 *     due_61_90: float,
 *     due_over_90: float,
 *   }
 * }
 */
public function outstandingDebt(string $tenantId): array
```

**Công thức bucket** (dựa trên `now()->diffInDays($payment->due_date, false)` — số ngày ĐÃ QUÁ hạn, âm nghĩa là chưa đến hạn):
- `not_due`: `due_date >= now()` (chưa đến hạn, kể cả hôm nay).
- `due_1_30`: quá hạn 1-30 ngày.
- `due_31_60`: quá hạn 31-60 ngày.
- `due_61_90`: quá hạn 61-90 ngày.
- `due_over_90`: quá hạn trên 90 ngày.

Tính bằng 1 query `unpaid` đã có sẵn (KHÔNG thêm N+1 query riêng cho từng bucket) — dùng `get()` một lần rồi group trong PHP (số lượng ContractPayment mỗi tenant không lớn, không cần tối ưu SQL CASE WHEN cho slice này).

## UI

`resources/views/crm/report.blade.php`: thêm khối "Công nợ theo tuổi nợ" ngay sau 3 field hiện có (dòng ~36) — 5 `<x-ui.field-value>` cho 5 bucket, định dạng tiền giống các field khác (`number_format(..., 0, ',', '.') . '₫'`).

## Error handling

Không có input mới từ người dùng (thuần tính toán từ dữ liệu có sẵn) — không có error case mới. Cache key giữ nguyên `business_kpi_outstanding_debt_{$tenantId}` (aging nằm trong cùng payload cache, không cần cache key riêng).

## Testing

Mở rộng test hiện có (`test_outstanding_debt_separates_overdue_from_total`) hoặc thêm test mới `test_outstanding_debt_aging_buckets_group_correctly`: tạo 5 `ContractPayment` với `due_date` rơi đúng vào từng bucket (vd: `addDays(5)` → not_due; `subDays(10)` → due_1_30; `subDays(45)` → due_31_60; `subDays(75)` → due_61_90; `subDays(120)` → due_over_90), assert từng `$result['aging'][...]` đúng số tiền; test cache vẫn hoạt động (`test_results_are_cached_for_60_seconds` không được vỡ); render assertion trên `crm/report.blade.php` thấy 5 label bucket; regression: `BusinessKpiServiceTest` nguyên bộ + `CrmReportController` test hiện có (nếu có) vẫn xanh.

## Out of scope

Biểu đồ trực quan (chart), export Excel/PDF báo cáo công nợ, nhắc nợ tự động qua email, cấu hình ngưỡng bucket theo tenant (cố định 30/60/90 cho mọi tenant ở slice này).
