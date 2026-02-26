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
