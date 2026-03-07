# Routes SSOT

This directory uses the following single source of truth (SSOT) rules:

- Canonical API routes are defined in `routes/api.php`.
- Versioned API endpoints (`/api/v1/*`) are also declared in `routes/api.php` via `v1`-prefixed route groups.
- `routes/legacy/api_v1.php` is legacy reference only and is not loaded by `App\Providers\RouteServiceProvider`.
- Do not reintroduce `routes/api_v1.php` at the root `routes/` directory.

## Settings Tabs SSOT

Current UI settings tabs:

- `General` at `/settings/general`
- `Security` at `/settings/security`
- `Notifications` at `/settings/notifications`

Matching API endpoints in `routes/api.php`:

- `GET /api/v1/settings/general`
- `PATCH /api/v1/settings/general`
- `GET /api/v1/settings/security`
- `PATCH /api/v1/settings/security`
- `GET /api/v1/settings/notifications`
- `PATCH /api/v1/settings/notifications`

RBAC tokens:

- `settings.general.read` and `settings.general.update`
- `settings.security.read` and `settings.security.update`
- Notifications use `notification.read` and `notification.manage_rules`

Rule for adding a new settings tab:

- Implement in order: backend contract -> feature tests -> frontend UI wiring.
- Add dedicated RBAC read/update tokens for the new tab and apply route middleware.
- Include evidence in PR (route list, relevant tests, frontend build where applicable).

## API Security Middleware Gate (P2.2)

Mục tiêu: đảm bảo các “business API routes” được bảo vệ bởi middleware **Auth + Tenant + RBAC**. Gate này được enforce ở level test để phát hiện route bị cấu hình thiếu middleware (misconfigured) và tránh regress.

### Public-by-design allowlist

Các endpoint sau **cố ý public** nên được allowlist explicit trong `ApiSecurityMiddlewareGateTest` (không ép auth/tenant/rbac lên runtime chỉ để test pass):

- `POST /api/zena/auth/login`  
  Lý do: endpoint đăng nhập — không thể yêu cầu auth trước khi login.

- `GET /api/zena/health`  
  Lý do: health probe/public check cho hạ tầng.

- `GET /api/v1/public/health`  
- `GET /api/v1/public/health/liveness`  
- `GET /api/v1/public/health/readiness`  
  Lý do: public health endpoints phục vụ ops readiness/liveness.

Nguyên tắc: endpoint nào public-by-design thì **allowlist explicit** trong gate test và ghi rõ rationale tại đây để tránh tranh luận về sau.
