# GAP-130 — Login shows "419 Page Expired" after a successful sign-in: evidence

Date: 2026-11-20. Base: `main` at `13a72bf9f0172520abe6b44019ce3a0fe929a125` (after GAP-129). Host: production
`https://app.zena.com.vn`.

## 1. What the Owner saw

The Owner reported "Tôi login vào nhưng đang báo lỗi 419".

## 2. nginx access log (UTC, one Chrome browser on Windows)

| Time | Request | Status |
|---|---|---|
| 18:24:37 | `GET /login` | 200 |
| 18:25:06 | `POST /login` | **302 → `/app/today`** (sign-in succeeded) |
| 18:25:08 | `GET /app/today` | 200 (signed in) |
| 18:25:08 | `POST /login` (second submit, same page) | **419** |
| 18:28:31 | `POST /login` (resubmitted from the 419 page) | 419 |
| 18:28:41 | `POST /login` | 419 |

A `curl` login at 18:28:23, between the two failing attempts, worked (302). So the server and sessions were healthy.

## 3. Cause

1. The login form was **submitted twice** within about two seconds, for example by a double click, Enter plus click, or a
   password manager.
2. The first submit signed the user in. `AuthController::login()` calls `$request->session()->regenerate()`, and Laravel's
   `Session\Store::regenerate()` also regenerates the CSRF token.
3. The second submit still carried the old token. `VerifyCsrfToken` threw `TokenMismatchException`, giving HTTP 419.
4. The browser rendered the last response, the generic "419 Page Expired" page. The user believed sign-in had failed and
   resubmitted from that page with the same stale token, getting 419 again.

Contributing:
- `resources/views/auth/login.blade.php` is a plain `<form method="POST" action="/login">` with nothing that prevents a
  second submit.
- `App\Exceptions\Handler` renders `TokenMismatchException` itself only under `APP_ENV=testing` (line 145). In production
  the framework's generic 419 page is shown, with no way back.
- `GET /login` shows the form even to a signed-in user, so "back" or reopening the login tab invites another stale submit.

The same 419 also happens to any guest whose login page sat open longer than the session lifetime (120 minutes).
