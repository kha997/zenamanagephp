# Development Setup Guide

## Single-Origin Routing Architecture

ZenaManage uses **single-origin routing** to ensure consistent session/cookie handling and clean architecture. All requests (Blade admin, React SPA, API) go through one domain.

### Architecture Overview

```
┌─────────────────────────────────────────┐
│  Browser (http://dev.zena.local)        │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Nginx Reverse Proxy (Port 80)           │
│  - Routes /admin/* → Laravel (8000)     │
│  - Routes /api/* → Laravel (8000)      │
│  - Routes /app/* → React (5173)         │
└──────┬──────────────────┬───────────────┘
       │                  │
       ▼                  ▼
┌──────────────┐  ┌──────────────┐
│ Laravel      │  │ React/Vite   │
│ (Port 8000)  │  │ (Port 5173)  │
└──────────────┘  └──────────────┘
```

### Benefits

1. **Session Consistency**: Same origin = shared cookies/session
2. **No CORS Issues**: All requests from same domain
3. **Clean RBAC**: Laravel middleware enforces `/admin/*` routes
4. **Better Logging**: Server logs show correct routing
5. **No Client Redirects**: Server-side routing, no SPA "swallowing" routes

## Development Setup

### Option 1: Using Nginx Reverse Proxy (Recommended)

#### Step 1: Install Nginx (if not installed)

**macOS:**
```bash
brew install nginx
```

**Linux:**
```bash
sudo apt-get install nginx  # Ubuntu/Debian
sudo yum install nginx       # CentOS/RHEL
```

#### Step 2: Configure Hosts File

Add to `/etc/hosts`:
```
127.0.0.1 dev.zena.local
```

#### Step 3: Setup Nginx Config

Copy the dev proxy config:
```bash
sudo cp docker/nginx/dev-proxy.conf /etc/nginx/sites-available/dev.zena.local
sudo ln -s /etc/nginx/sites-available/dev.zena.local /etc/nginx/sites-enabled/
```

Or use the config directly:
```bash
sudo nginx -t -c /path/to/zenamanage/docker/nginx/dev-proxy.conf
```

#### Step 4: Start Services

1. **Start Laravel backend:**
```bash
php artisan serve --port=8000
```

2. **Start React frontend:**
```bash
cd frontend
npm run dev
```

3. **Start Nginx:**
```bash
sudo nginx -c /path/to/zenamanage/docker/nginx/dev-proxy.conf
# Or if using system nginx:
sudo systemctl start nginx
```

#### Step 5: Access Application

- **Admin (Blade)**: http://dev.zena.local/admin/users
- **App (React)**: http://dev.zena.local/app/dashboard
- **API**: http://dev.zena.local/api/v1/...

### Option 2: Direct Port Access (Fallback)

If you don't want to setup Nginx, you can still access directly:

- **Admin (Blade)**: http://localhost:8000/admin/users
- **App (React)**: http://localhost:5173/app/dashboard
- **API**: http://localhost:8000/api/v1/...

**Note**: With this setup, you may encounter session/cookie issues between ports. Use Option 1 for best experience.

## Environment Configuration

### Laravel (.env)

```env
# Single origin configuration
APP_URL=http://dev.zena.local
# Or for direct port access: APP_URL=http://localhost:8000

# Session domain - leave empty for same-origin
SESSION_DOMAIN=

# Sanctum stateful domains - only domain, no port
SANCTUM_STATEFUL_DOMAINS=dev.zena.local,localhost

# Frontend URL - same domain
FRONTEND_URL=${APP_URL}
```

### React (frontend/.env)

```env
# Admin base URL - same origin in production, can be different in dev
VITE_ADMIN_BASE_URL=http://dev.zena.local
# Or for direct port access: VITE_ADMIN_BASE_URL=http://localhost:8000

# API base URL
VITE_API_BASE_URL=http://dev.zena.local/api/v1
```

## Troubleshooting

### Session Not Persisting

1. **Check SESSION_DOMAIN**: Should be empty or match your domain
2. **Check cookie settings**: Ensure `SameSite=Lax` or `Strict`
3. **Check browser console**: Look for cookie warnings
4. **Verify same origin**: All requests should use same domain

### CORS Errors

1. **Use single-origin**: Setup Nginx reverse proxy
2. **Check SANCTUM_STATEFUL_DOMAINS**: Should include your domain
3. **Verify headers**: Check `Access-Control-Allow-Origin` in response

### Routes Not Working

1. **Check Nginx config**: Verify proxy_pass targets
2. **Check services**: Ensure Laravel (8000) and React (5173) are running
3. **Check hosts file**: Verify `dev.zena.local` points to 127.0.0.1
4. **Check browser**: Clear cache and cookies

### Admin Links Not Working

1. **Check AdminNavigator**: Should use absolute links for Blade routes
2. **Check VITE_ADMIN_BASE_URL**: Should match your setup
3. **Verify routes**: Ensure `/admin/users` exists in Laravel routes

## Production Setup

See `docs/DEPLOYMENT.md` for production Nginx configuration.

## Additional Resources

- [Nginx Documentation](https://nginx.org/en/docs/)
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Vite Documentation](https://vitejs.dev/)

