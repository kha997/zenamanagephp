---
work_id: GAP-129
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-129-admin-ui-evidence.md
  plan: docs/superpowers/plans/2026-11-18-gap-129-admin-ui.md
  branch: docs/GAP-129-admin-ui
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-18T22:40:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim answers to the four Gate 2 questions: 'U-1 (đề xuất)', 'R-1 (đề xuất)', 'C-1 (đề xuất)', 'L-1 (đề xuất)'. Approves L-1, U-1, R-1, C-1 as described in this packet, one PR / one Gate 3, with the exact allowlist in this packet; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T22:30:00+07:00"
  updated_at: "2026-11-18T22:40:00+07:00"
generated_by: agent
---

# GAP-129 — Admin UI for companies, roles and users: Gate 2 design

## OWNER GATE 2: APPROVED — L-1, U-1, R-1, C-1

Owner approved L-1, U-1, R-1 and C-1 in-session on 2026-11-18. This authorizes only the bounded implementation defined by
this packet; it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Anh/chị chọn **4 điểm**. Đề xuất:

1. **Vị trí — L-1:** mục mới **"Quản trị"** trong thanh bên của app (cùng giao diện các trang khác), chỉ hiện với Owner.
   Hai trang giả `/admin/users`, `/admin/tenants` chuyển hướng sang trang thật.
2. **Tài khoản — U-1:** xem danh sách; tạo tài khoản (mật khẩu ngẫu nhiên hiện **một lần**); đổi vai trò; khoá / mở khoá
   (người bị khoá bị **đăng xuất ngay**); cấp lại mật khẩu.
3. **Vai trò — R-1:** **chỉ xem** 5 vai trò chuẩn: mô tả, số người, danh sách quyền theo nhóm. Không sửa quyền trên web.
4. **Công ty — C-1:** xem và sửa **tên công ty mình**. Không tạo / quản lý nhiều công ty.

**Phát hành:** một PR, một Gate 3. Không đổi cấu trúc database.

## Điểm 1 — Vị trí và ai được vào

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | Trang mới dưới `/app/admin/...`, layout operator như mọi trang khác, mục "Quản trị" trong thanh bên. Quyền: `user.read` để xem, `user.create` / `user.update` / `user.manage_roles` để sửa (chỉ `super_admin` có đủ). Chỉ thấy dữ liệu **công ty mình**. `/admin/users` và `/admin/tenants` (đang là dữ liệu giả) chuyển hướng 302 sang trang mới; xoá 2 view giả. |
| L-2 | Làm lại ngay trong `/admin/*` (layout admin cũ). Hai layout song song, trái nguyên tắc "toàn app một layout". |

## Điểm 2 — Tài khoản

| Phương án | Nội dung |
|---|---|
| **U-1 (đề xuất)** | Danh sách (tên, email, vai trò, trạng thái, ngày tạo). **Tạo** tài khoản với 1 trong 5 vai trò chuẩn. **Đổi vai trò**. **Khoá / mở khoá**. **Cấp lại mật khẩu**. Mật khẩu: ngẫu nhiên 20 ký tự, hiện **một lần** trên màn hình ngay sau khi tạo / cấp lại, không lưu, không gửi email. |
| U-2 | Như U-1 nhưng không tạo tài khoản trên web (vẫn dùng lệnh `production:user`). |

**Quy tắc chung (U-1, U-2):**
- Mỗi thao tác ghi audit giống lệnh CLI (`production.user.created`, `production.user.password_reset`) và thêm
  `admin.user.role_changed`, `admin.user.deactivated`, `admin.user.reactivated`, với `source = web` và người thực hiện.
- **Không tự khoá hay tự hạ vai trò của chính mình**, và **không khoá / hạ vai trò Owner cuối cùng** còn hoạt động, để
  công ty không bao giờ mất người quản trị.
- Người bị khoá: lần bấm tiếp theo bị **đăng xuất ngay** (middleware kiểm `is_active` mỗi request). Không phải chờ hết
  phiên.
- Email không trùng trong toàn hệ thống (giống CLI).
- Logic tạo / cấp lại mật khẩu tách thành một service dùng chung; lệnh `production:user` gọi service đó, hành vi giữ nguyên.

## Điểm 3 — Vai trò

| Phương án | Nội dung |
|---|---|
| **R-1 (đề xuất)** | **Chỉ xem**: 5 vai trò, mô tả, số người dùng, danh sách quyền nhóm theo phân hệ. |
| R-2 | Cho bật / tắt từng quyền của vai trò. **Rủi ro:** `production:rbac` (runbook mục 6, chạy lại khi cập nhật) dùng `sync()` cho Project Manager và Project Member, nên **ghi đè** phần sửa trên web. Muốn R-2 phải đổi cả `production:rbac`, phạm vi lớn hơn nhiều. |
| R-3 | R-2 cộng thêm tạo vai trò riêng. Ngoài phạm vi GAP này. |

## Điểm 4 — Công ty

| Phương án | Nội dung |
|---|---|
| **C-1 (đề xuất)** | Xem thông tin công ty mình (tên, mã `slug`, ngày tạo, số tài khoản). Sửa **tên**. `slug` không đổi (đang dùng trong link cổng khách hàng). |
| C-2 | Thêm quản lý **nhiều công ty** (tạo công ty mới, xem tất cả). Cần một vai trò "quản trị hệ thống" vượt qua giới hạn công ty, một thay đổi lớn về phân quyền. Hiện chỉ có 1 công ty. |

## Thiết kế chi tiết (theo L-1, U-1, R-1, C-1)

- **Route** (nhóm `/app`, middleware hiện có `auth`, `tenant.isolation`):
  - `GET /app/admin/users` (`rbac:user.read`); `POST /app/admin/users` (`rbac:user.create`);
  - `POST /app/admin/users/{user}/role` (`rbac:user.manage_roles`);
  - `POST /app/admin/users/{user}/deactivate`, `.../reactivate`, `.../reset-password` (`rbac:user.update`);
  - `GET /app/admin/roles` (`rbac:user.read`);
  - `GET /app/admin/company`, `POST /app/admin/company` (`rbac:admin`).
  - Mọi `{user}` phải thuộc công ty người đang đăng nhập, nếu không trả 404.
- **Mật khẩu một lần:** hiện qua session flash đúng một lần, không ghi log, không lưu.
- **Middleware `EnsureUserIsActive`** trong nhóm `web`: người đã đăng nhập mà `is_active = false` thì bị đăng xuất, huỷ
  session và chuyển về `/login`.
- **Thanh bên:** mục "Quản trị" gồm Tài khoản / Vai trò / Công ty, hiện khi có `user.read`.
- **Giao diện:** Blade + form POST (không cần JavaScript), theo các trang operator hiện có; hiển thị dạng thẻ trên điện
  thoại như GAP-118 / GAP-125.

**Test** (đỏ trước, xanh sau):
- Mỗi route: người không có quyền bị chặn; user của công ty khác → 404.
- Tạo tài khoản: đúng vai trò, mật khẩu hiện một lần, đăng nhập được bằng mật khẩu đó, có audit.
- Đổi vai trò, khoá / mở khoá, cấp lại mật khẩu: đúng dữ liệu và audit. Người bị khoá bị đăng xuất ở request kế tiếp.
- Không tự khoá mình; không khoá / hạ Owner cuối cùng.
- Trang vai trò khớp số quyền thật.
- Đổi tên công ty: chỉ công ty mình, `slug` không đổi.
- `production:user` vẫn chạy như cũ (test hiện có).
- `/admin/users`, `/admin/tenants` chuyển hướng.

## Files (allowlist)

- `routes/web.php`
- `app/Http/Controllers/Web/Admin/*` (mới)
- `app/Services/Admin/StaffAccountService.php` (mới); `app/Console/Commands/ProductionUserCommand.php` (gọi service)
- `app/Http/Middleware/EnsureUserIsActive.php` (mới); `app/Http/Kernel.php`
- `app/Support/Navigation/OperatorNavigationDefinition.php`
- `resources/views/app/admin/*` (mới); `resources/css/operator.css`
- xoá `resources/views/admin/users*.blade.php`, `resources/views/admin/tenants*.blade.php`
- `tests/Feature/Admin/*` (mới)
- `docs/owner-decisions/GAP-129/*`, `docs/audits/2026-11-18-gap-129-admin-ui-evidence.md`,
  `docs/superpowers/plans/2026-11-18-gap-129-admin-ui.md`

File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Owner amendment 1 (approved 2026-11-18)

Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-129 amendment 1'.

**Correction:** this packet said only `super_admin` holds `user.read` / `user.create` / `user.update` / `user.manage_roles`.
That is wrong. `PermissionSeeder` gives **Project Manager** `user.read`, `user.manage_roles`, `settings.general.read` and
`settings.general.update`. Gating on those codes would let a PM promote themselves to Owner or rename the company. The red
test `test_every_non_owner_role_is_refused` caught it. The canonical catalogue has no Owner-only code: `system.admin` is not
granted to `super_admin` by `production:rbac`; the Owner only passes through the `isSuperAdmin` bypass, so the sidebar
would hide the section.

**Amendment:**
- A new canonical permission `admin.company.manage` is added to `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS`. It is
  granted to `super_admin` by `ZenaAdminRolePermissionSeeder`; the other four roles get explicit lists and do not receive
  it.
- Every `/app/admin/*` route and the "Quản trị" sidebar section use `rbac:admin.company.manage`.
- Allowlist adds `database/seeders/ZenaPermissionsSeeder.php`.
- After deploy, run `php artisan production:rbac` once so the new permission is loaded on production.

## Owner amendment 2 (approved 2026-11-18)

Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-129 amendment 2'.

The full suite showed `tests/Feature/Architecture/OrphanAdminViewsRemovedTest.php` (GAP-020) requiring the mock views
`admin.users` / `admin.tenants` to exist, which contradicts their approved removal. The test is added to the allowlist. It
keeps the GAP-020 check (the orphan `admin/{users,tenants}/index` views stay removed) and now asserts the two mock views
are removed too.

Implementation note: only the two routed views `admin/users.blade.php` and `admin/tenants.blade.php` are deleted. The
partials `admin/users-content.blade.php` and `admin/tenants-content.blade.php` stay, because the unrouted
`layouts/admin-layout.blade.php` (rendered only by the unrouted `AdminController`) still includes them, and that layout is
outside this allowlist.

## Ngoài phạm vi

- Email mời / quên mật khẩu.
- Sửa quyền của vai trò, vai trò tự tạo (R-2, R-3).
- Nhiều công ty (C-2).
- Các trang giả còn lại trong `/admin` (security, alerts, activities…).
- Nhân sự & lương.
- Deploy.

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau; toàn bộ Unit + Feature + Integration và các lint.
- Chạy thử trên trình duyệt: tạo tài khoản → đăng nhập bằng mật khẩu một lần → khoá → bị đăng xuất.

## Rollback

Revert PR. Không có migration.

## Decision Needed

Owner chọn: `APPROVE GAP-129 Gate 2 L-1 U-1 R-1 C-1` (hoặc phương án khác cho từng điểm) / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
