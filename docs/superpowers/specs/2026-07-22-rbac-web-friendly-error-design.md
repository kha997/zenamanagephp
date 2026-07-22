# RBAC Middleware Friendly Web Error — Design Spec

**Date:** 2026-07-22
**Status:** Approved for planning

## Context

Kiểm tra thủ công 3 trang (`material-requests/create`, `materials/create`, `vendors/create`) với tài khoản thiếu quyền phát hiện: `materials/create` và `vendors/create` đổ **JSON thô ra thẳng trình duyệt** khi bị từ chối quyền, trong khi `material-requests/create` hiện trang 403 đẹp (Blade styled page).

**Nguyên nhân**: `material-requests/create` gate bằng `$this->authorize()` (Laravel Policy) trong controller — Laravel exception handler tự render trang 403 đẹp. `materials/create`/`vendors/create` gate bằng route middleware `->middleware('rbac:material.create')`/`->middleware('rbac:vendor.create')` — `RoleBasedAccessControlMiddleware` (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php`) trả `response()->json(...)` **vô điều kiện** ở mọi nhánh từ chối, không phân biệt request là điều hướng trang web hay gọi API.

**Phạm vi thật**: middleware này gate **167 route trong `routes/web.php`** (bare `rbac:xxx` không bọc qua `$this->authorize()`) và một số lượng lớn route trong `routes/api.php`. Đây là nợ kỹ thuật đã ghi nhận từ trước (PR#213) nhưng chưa xử lý.

**4 nhánh trả JSON vô điều kiện** (xác nhận qua code, dòng cụ thể trong `RoleBasedAccessControlMiddleware.php`):
1. Dòng 32-37: `!$user` → `ErrorEnvelopeService::authenticationError()` (401) — **thực tế không thể xảy ra trên web route** vì middleware `auth` (chạy trước `rbac:` trong mọi route group liên quan) đã redirect người dùng chưa đăng nhập tới `/login` trước khi tới middleware này. Vẫn sửa để phòng thủ.
2. Dòng 46-53: thiếu `tenant_id` → `ErrorEnvelopeService::error('TENANT_REQUIRED', ..., 400)` — **thực tế cũng khó xảy ra trên web** vì `tenant.isolation` (chạy trước) đã set `tenant_id` vào request attributes từ session người dùng. Vẫn sửa để phòng thủ.
3. Dòng 84-87 (trong `handle()`): `$roleOrPermission` cụ thể (vd `rbac:vendor.create`) không đủ quyền → `ErrorEnvelopeService::authorizationError()` (403) — **đây là bug đang tái hiện thật** ở `materials/create`/`vendors/create`.
4. Dòng 271-277 (trong `handleGeneralAccess()`, khi middleware dùng bare `rbac` không tham số): thiếu vai trò hợp lệ → `ErrorEnvelopeService::error('RBAC_ACCESS_DENIED', ..., 403)` — cùng bug, route khác.

## Tiền lệ đã có sẵn trong chính codebase

Nhiều middleware khác **đã** dùng đúng pattern content-negotiation, không cần phát minh gì mới:

```php
// app/Http/Middleware/RolePermission.php (và AdminOnlyMiddleware, Authenticate, TenantScope, InvitationAuth — cùng pattern)
if ($request->expectsJson()) {
    return response()->json([...], 403);
}
return redirect('/dashboard')->with('error', 'You do not have permission to access this page');
```

`resources/views/components/ui/toast.blade.php` (đã include trong `layouts/operator.blade.php`) **đã render sẵn** `session('error')` — không cần thêm UI gì.

## Design

Thêm 1 private helper `deny()` trong `RoleBasedAccessControlMiddleware`, dùng ở cả 4 nhánh:

```php
private function deny(Request $request, string $code, string $jsonMessage, int $statusCode, string $webMessage, ?string $requestId = null, array $details = []): Response
{
    if ($request->expectsJson()) {
        return ErrorEnvelopeService::error($code, $jsonMessage, $details, $statusCode, $requestId);
    }

    return redirect()->back()->with('error', $webMessage);
}
```

- **JSON message giữ nguyên y hệt hiện tại** (không đổi 1 ký tự) — zero rủi ro cho API consumer/test đang assert theo `assertJsonPath('error.message', ...)`.
- **Web message** dùng tiếng Việt, đúng convention đã dùng khắp session này ("Bạn không có quyền thực hiện thao tác này." cho lỗi 403).
- Nhánh `!$user` (401) là ngoại lệ: dùng `redirect()->guest(route('login'))->with('error', ...)` thay vì `back()` — đúng convention Laravel cho lỗi authentication (khớp cách Laravel's `Authenticate` middleware xử lý), dù thực tế không thể tới được nhánh này qua web route.
- 3 nhánh còn lại dùng `redirect()->back()` — quay lại trang trước đó (nơi user vừa click), hiện toast lỗi ở đó. Nếu không có referer, Laravel tự fallback về APP_URL.

## Testing

- Test mới xác nhận **web route** (dùng `operator.vendors.create` — route thật đã tái hiện bug) gọi bằng `$this->actingAs($user)->get(...)` (KHÔNG set Accept JSON, mô phỏng browser thật) khi thiếu quyền: response là **redirect (302)**, `session('error')` có nội dung, KHÔNG PHẢI JSON body.
- Test mới xác nhận **API route** cùng middleware (dùng 1 route `api.php` bất kỳ gate bởi `rbac:` với cùng permission code) gọi bằng `->getJson(...)` khi thiếu quyền: response JSON **giữ nguyên y hệt hiện tại** — cùng mã lỗi, cùng cấu trúc envelope (regression guard, không đổi behavior API).
- Test mới cho nhánh bare `rbac` (không tham số, `handleGeneralAccess()`): web route thiếu vai trò hợp lệ → redirect + flash, không phải JSON.
- **Bắt buộc chạy toàn bộ test suite** (không phải mẫu) làm bước verify cuối, vì middleware này gate ~300 route — diff nhỏ nhưng blast radius cực lớn. Đây là gate chính của kế hoạch, không phải optional.

## Out of Scope

- Không đổi cấu trúc JSON envelope hay message text phía API.
- Không đổi middleware `auth`, `tenant.isolation`, hay các middleware RBAC khác (`AdminOnlyMiddleware` v.v. đã đúng sẵn).
- Không đụng route nào gate bằng `$this->authorize()` (đã hoạt động đúng, có trang 403 đẹp sẵn).
