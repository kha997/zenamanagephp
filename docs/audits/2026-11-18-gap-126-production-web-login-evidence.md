# GAP-126 — Production has no web login: evidence

Date: 2026-11-18. Base: `main` at `ca7a878b31cf3ef80fa56523b5f7a78a40a01d43` (after GAP-125).

## What happened

On 2026-11-18 the app was started on a real host (`APP_ENV=production`, code at `main` `ca7a878b`).

- `GET /` redirects to `/app/today`.
- `GET /app/today` (not signed in) returned **HTTP 500**. `storage/logs/laravel.log`:
  `production.ERROR: Route [login] not defined.` (`Illuminate/Routing/UrlGenerator.php:536`).
- `GET /login` returned **HTTP 404**.
- `php artisan route:list --path=login` under `APP_ENV=production` listed only API logins
  (`api/auth/login`, `api/v1/auth/login`, `api/zena/auth/login`) and the client-portal magic link
  (`portal/{tenantSlug}/login`). There is no route named `login`, `login.post`, `logout` or `logout.post`.

So in production no staff member can sign in to the web app. `docs/runbooks/go-live.md` §6 ("Đăng nhập rồi đổi mật khẩu")
cannot be done.

## Cause

`routes/web.php:56-83` wraps the web sign-in routes in `if (app()->environment(['local', 'testing']))`:

| Name | Route |
|---|---|
| `local.dev-login.operator` | `GET /local/dev-login/operator?email=` — logs in as any tenant user, no password |
| `login` | `GET /login` → `view('auth.login')` |
| `login.post` | `POST /login` → `AuthController@login` |
| `password.reset` | `GET /password/reset` → redirect to `/login` |
| `logout` | `GET /logout` → `AuthController@logout` |
| `logout.post` | `POST /logout` → `AuthController@logout` |

The guard dates from `f91abec97` (2026-02-01). Every test runs with `APP_ENV=testing`, where the routes exist, so no test saw
the gap. GAP-049 and GAP-115 hardened `AuthController::login()` for production (the demo-account fallback only runs in
local/testing), but the routes themselves were never opened.

Only `local.dev-login.operator` must stay local-only: it signs in as anyone without a password.

## Related findings

- `AuthController::login()` uses `Auth::attempt(['email','password'])`. It does not check `users.is_active`. The API login
  (`Api\AuthController`, line ~75) refuses deactivated accounts with "Account is deactivated". The web login would let a
  deactivated account in.
- `layouts/operator.blade.php` uses `route('logout.post')` (POST form). `layouts/simple.blade.php:477` links to
  `route('logout')` (GET).
- `App\Http\Middleware\Authenticate::redirectTo()` returns `null` for web requests ("chưa có route 'login'"), so Laravel falls
  back to `route('login')`. Once `login` exists the fallback works; no change is needed there.
- `AuthController::login()` already rate-limits: 5 failed attempts per email + IP per minute.

## Scope

Routes only, plus the `is_active` check. No new screens, no password-reset email flow (mail is not configured on the host).
