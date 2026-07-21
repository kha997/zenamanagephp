# Submittal Vendor Select — Design Spec

**Date:** 2026-07-22
**Status:** Approved for planning

## Context

Hạng mục #3 trong bảng ưu tiên data-entry optimization (nghiên cứu 2026-07-20). Note gốc ghi "RFI/Submittal" nhưng khám phá code xác nhận **RFI không có field contractor/manufacturer** — chỉ form Submittal có. Phạm vi slice: form tạo Submittal.

Hiện trạng: `resources/views/submittals/create.blade.php:66-73` có 2 input text tự do `contractor` ("Nhà thầu") và `manufacturer` ("Nhà sản xuất") — cột DB `string` nullable (`create_submittals_table.php:27-28`). Text tự do làm data bẩn (cùng một vendor gõ nhiều kiểu) và chậm.

## Goal

Đổi 2 field text thành **select thuần** chọn từ danh sách Vendor của tenant, kèm link "+ Thêm nhà cung cấp" — quyết định đã chốt với user (không dùng combobox gõ tự do).

## Design

- 2 `<select>` liệt kê Vendor **active** của tenant (`is_active = true`), sắp theo `name`, option hiển thị dạng `Tên (MÃ)` nhưng **value = `name`** (tên thuần). Option đầu `— Chọn nhà cung cấp —` value rỗng (field nullable). Vendor model không phân loại nhà thầu/nhà sản xuất → cả 2 select dùng chung danh sách.
- **Submit TÊN vendor (string), không phải id** — giữ nguyên schema (zero migration), bản ghi cũ chứa text tự do vẫn hiển thị bình thường, `submittals/show.blade.php` không đổi. Đây là trade-off có chủ đích: nếu sau này cần FK `vendor_id`, dữ liệu lúc đó đã sạch tên nên migrate dễ.
- **Validation server-side** trong `SubmittalPageController::store()`: `contractor`/`manufacturer` phải khớp `name` của một Vendor thuộc tenant hiện tại (tenant-scoped exists check) — chặn giá trị ngoài danh sách kể cả khi bypass form. Vẫn `nullable`.
- Link **"+ Thêm nhà cung cấp"** cạnh mỗi select, `href="{{ route('operator.vendors.create') }}"` `target="_blank"`, chỉ render khi `auth()->user()?->hasPermission('vendor.create')` (route đó gate `rbac:vendor.create` — không render link dẫn vào 403).
- **Không JS** — select thuần HTML, `old()` giữ nguyên qua `@selected(old('contractor') === $vendor->name)`.

## Out of Scope

- RFI (không có field nào tương đương).
- Migration/FK `vendor_id`.
- Form edit Submittal (không tồn tại — chỉ có create/index/show).
- Phân loại vendor theo vai trò (nhà thầu vs nhà sản xuất).

## Testing

Mở rộng `tests/Feature/Zena/OperatorSubmittalUiTest.php`:
1. Trang create hiện options vendor active của tenant (kèm format `Tên (MÃ)`); vendor inactive và vendor tenant khác KHÔNG xuất hiện.
2. Store với tên vendor hợp lệ → submittal lưu đúng giá trị.
3. Store với tên KHÔNG có trong danh sách vendor tenant → validation error (redirect back có error, không tạo record, không 500).
4. Store bỏ trống 2 field → vẫn tạo được (nullable giữ nguyên).
