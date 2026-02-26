# Routes SSOT

This directory uses the following single source of truth (SSOT) rules:

- Canonical API routes are defined in `routes/api.php`.
- Versioned API endpoints (`/api/v1/*`) are also declared in `routes/api.php` via `v1`-prefixed route groups.
- `routes/legacy/api_v1.php` is legacy reference only and is not loaded by `App\Providers\RouteServiceProvider`.
- Do not reintroduce `routes/api_v1.php` at the root `routes/` directory.
