# Single-Origin Routing Architecture

## Overview

ZenaManage implements **single-origin routing** to ensure consistent session/cookie handling, clean architecture, and better security. All requests (Blade admin, React SPA, API) go through one domain, eliminating cross-origin issues.

## Architecture

```
┌─────────────────────────────────────────┐
│  Browser (Single Domain)                 │
│  http://dev.zena.local (dev)             │
│  https://manager.zena.com.vn (prod)      │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Nginx Reverse Proxy / Web Server       │
│  Routes:                                 │
│  - /admin/* → Laravel Blade             │
│  - /api/* → Laravel API                 │
│  - /app/* → React SPA                   │
│  - /login, /register → React SPA        │
└──────┬──────────────────┬───────────────┘
       │                  │
       ▼                  ▼
┌──────────────┐  ┌──────────────┐
│ Laravel      │  │ React/Vite   │
│ Backend      │  │ Frontend      │
└──────────────┘  └──────────────┘
```

## Benefits

### 1. Session Consistency
- **Same origin = shared cookies/session**
- No need to re-authenticate when switching between `/admin/*` and `/app/*`
- Session persists across all routes

### 2. No CORS Issues
- All requests from same domain
- No preflight requests needed
- Simpler API authentication

### 3. Clean RBAC Enforcement
- Laravel middleware enforces `/admin/*` routes
- No client-side redirects or workarounds
- Server-side routing is authoritative

### 4. Better Logging & Observability
- Server logs show correct routing
- Sentry/traceId less noisy
- Easier debugging

### 5. No Client-Side Redirects
- Server handles routing
- No SPA "swallowing" routes
- Better browser history handling

## Implementation

### Frontend Changes

#### 1. React Router
- **Removed** `/admin/users`, `/admin/members` routes from React Router
- **Kept** `/admin/dashboard` as React route (if needed)
- Admin routes are accessed via absolute links, not React navigation

#### 2. AdminNavigator Component
- Uses absolute links (`<a href>`) for Blade routes
- Uses `NavLink` for React routes only
- Supports `VITE_ADMIN_BASE_URL` environment variable

### Backend Changes

#### 1. Session Configuration
```env
# .env
SESSION_DOMAIN=  # Empty for same-origin
SANCTUM_STATEFUL_DOMAINS=dev.zena.local,manager.zena.com.vn  # Only domain, no ports
APP_URL=http://dev.zena.local  # Single origin
```

#### 2. Sanctum Configuration
- Updated `config/sanctum.php` to only include domains (no ports)
- Removed port-based stateful domains

### Server Configuration

#### Development (Nginx Reverse Proxy)
See `docker/nginx/dev-proxy.conf`:
- `/admin/*` → `http://localhost:8000` (Laravel)
- `/api/*` → `http://localhost:8000` (Laravel)
- `/app/*` → `http://localhost:5173` (React/Vite)
- Root routes → `http://localhost:5173` (React/Vite)

#### Production (Nginx)
See `docker/nginx/production.conf`:
- `/admin/*` → Laravel (via `try_files`)
- `/api/*` → Laravel (via `try_files`)
- `/app/*` → Serve from `public/app/` (React build)
- Root routes → Serve from `public/app/` (React build)

## Configuration

### Development

1. **Setup hosts file:**
```bash
echo "127.0.0.1 dev.zena.local" | sudo tee -a /etc/hosts
```

2. **Start services:**
```bash
# Laravel
php artisan serve --port=8000

# React
cd frontend && npm run dev

# Nginx (optional, for single-origin)
sudo nginx -c /path/to/docker/nginx/dev-proxy.conf
```

3. **Access:**
- Admin: http://dev.zena.local/admin/users
- App: http://dev.zena.local/app/dashboard
- API: http://dev.zena.local/api/v1/...

### Production

1. **Build React:**
```bash
cd frontend
npm run build
# Output goes to public/app/
```

2. **Configure Nginx:**
```bash
sudo cp docker/nginx/production.conf /etc/nginx/sites-available/zenamanage
sudo ln -s /etc/nginx/sites-available/zenamanage /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

3. **Environment:**
```env
APP_URL=https://manager.zena.com.vn
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=manager.zena.com.vn
FRONTEND_URL=${APP_URL}
```

## Migration from Multi-Origin

### Before (Multi-Origin)
- React: `http://localhost:5173`
- Laravel: `http://localhost:8000`
- Issues: Session not shared, CORS problems, client redirects

### After (Single-Origin)
- All: `http://dev.zena.local` (dev) or `https://manager.zena.com.vn` (prod)
- Benefits: Shared session, no CORS, server routing

## Troubleshooting

### Session Not Persisting

1. **Check SESSION_DOMAIN:**
```env
SESSION_DOMAIN=  # Should be empty
```

2. **Check cookie settings:**
- `SameSite=Lax` or `Strict`
- `Secure=true` in production (HTTPS)
- `HttpOnly=true`

3. **Verify same origin:**
- All requests should use same domain
- Check browser console for cookie warnings

### CORS Errors

1. **Use single-origin:**
- Setup Nginx reverse proxy
- All requests from same domain

2. **Check SANCTUM_STATEFUL_DOMAINS:**
- Only domain, no ports
- Include your domain

### Routes Not Working

1. **Check Nginx config:**
- Verify `proxy_pass` targets (dev)
- Verify `try_files` and `alias` (prod)

2. **Check services:**
- Laravel running on port 8000
- React/Vite running on port 5173 (dev)
- React build in `public/app/` (prod)

3. **Check hosts file:**
- Verify `dev.zena.local` points to 127.0.0.1

### Admin Links Not Working

1. **Check AdminNavigator:**
- Should use absolute links for Blade routes
- Check `VITE_ADMIN_BASE_URL` environment variable

2. **Verify routes:**
- Ensure `/admin/users` exists in Laravel routes
- Check middleware permissions

## Best Practices

1. **Always use single-origin in production**
2. **Use reverse proxy in development for consistency**
3. **Never include ports in SANCTUM_STATEFUL_DOMAINS**
4. **Keep SESSION_DOMAIN empty for same-origin**
5. **Use absolute links for cross-framework navigation**

## References

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Nginx Reverse Proxy Guide](https://nginx.org/en/docs/http/ngx_http_proxy_module.html)
- [Same-Origin Policy](https://developer.mozilla.org/en-US/docs/Web/Security/Same-origin_policy)

