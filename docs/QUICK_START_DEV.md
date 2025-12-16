# Quick Start: Development Setup

## Single-Origin Routing Development Setup

This guide helps you quickly set up single-origin routing for development.

## Prerequisites

- Laravel backend running on port 8000
- React frontend running on port 5173
- Nginx or Docker (for reverse proxy)

## Step 1: Add Host Entry

Add `dev.zena.local` to your `/etc/hosts` file:

```bash
# Option 1: Use the setup script
./scripts/setup-dev-hosts.sh

# Option 2: Manual
sudo echo "127.0.0.1 dev.zena.local" >> /etc/hosts
```

Verify:
```bash
grep "dev.zena.local" /etc/hosts
```

## Step 2: Start Services

### Start Laravel Backend

```bash
php artisan serve --port=8000 --host=0.0.0.0
```

### Start React Frontend

```bash
cd frontend
npm run dev
```

## Step 3: Start Nginx Dev Proxy

### Option A: Using Docker (Recommended)

```bash
# Start Nginx container (uses Docker-specific config with host.docker.internal)
docker run -d \
  --name zenamanage-dev-proxy \
  -p 80:80 \
  -v $(pwd)/docker/nginx/dev-proxy.docker.conf:/etc/nginx/conf.d/default.conf:ro \
  --add-host=host.docker.internal:host-gateway \
  nginx:alpine

# Check status
docker ps | grep zenamanage-dev-proxy

# View logs
docker logs zenamanage-dev-proxy
```

### Option B: Using System Nginx

```bash
# Install Nginx (if not installed)
# macOS: brew install nginx
# Ubuntu: sudo apt-get install nginx

# Test config
sudo nginx -t -c $(pwd)/docker/nginx/dev-proxy.conf

# Start Nginx
sudo nginx -c $(pwd)/docker/nginx/dev-proxy.conf

# Or use the script
./scripts/start-dev-proxy.sh
```

### Option C: Using Homebrew Nginx (macOS)

```bash
brew install nginx
sudo /opt/homebrew/bin/nginx -t -c $(pwd)/docker/nginx/dev-proxy.conf
sudo /opt/homebrew/bin/nginx -c $(pwd)/docker/nginx/dev-proxy.conf
```

## Step 4: Test

Open your browser and test:

- **Admin (Blade)**: http://dev.zena.local/admin/users
- **App (React)**: http://dev.zena.local/app/dashboard
- **API**: http://dev.zena.local/api/v1/...

## Troubleshooting

### Port 80 Already in Use

If port 80 is already in use, you can:

1. **Stop existing service:**
```bash
# Find what's using port 80
sudo lsof -i :80

# Stop it (example for Apache)
sudo apachectl stop
```

2. **Use different port:**
Edit `docker/nginx/dev-proxy.conf` and change `listen 80;` to `listen 8080;`
Then access: http://dev.zena.local:8080

### Nginx Not Starting

1. **Check config:**
```bash
sudo nginx -t -c $(pwd)/docker/nginx/dev-proxy.conf
```

2. **Check logs:**
```bash
# Docker
docker logs zenamanage-dev-proxy

# System Nginx
tail -f /var/log/nginx/dev-proxy-error.log
```

3. **Check if services are running:**
```bash
# Laravel
curl http://localhost:8000/health

# React
curl http://localhost:5173
```

### Session Not Persisting

1. **Check Laravel .env:**
```env
APP_URL=http://dev.zena.local
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=dev.zena.local,localhost
```

2. **Clear browser cookies** for `dev.zena.local`

3. **Check browser console** for cookie warnings

### CORS Errors

1. **Verify single-origin:** All requests should go through `dev.zena.local`
2. **Check SANCTUM_STATEFUL_DOMAINS:** Should include `dev.zena.local`
3. **Check Nginx proxy headers:** Ensure `Host` header is set correctly

## Stop Services

### Stop Nginx

```bash
# Docker
docker stop zenamanage-dev-proxy
docker rm zenamanage-dev-proxy

# System Nginx
sudo nginx -s stop -c $(pwd)/docker/nginx/dev-proxy.conf
```

### Remove Host Entry (Optional)

```bash
sudo sed -i '' '/dev.zena.local/d' /etc/hosts
```

## Alternative: Direct Port Access

If you don't want to set up Nginx, you can still access services directly:

- **Admin (Blade)**: http://localhost:8000/admin/users
- **App (React)**: http://localhost:5173/app/dashboard
- **API**: http://localhost:8000/api/v1/...

**Note:** With direct port access, you may encounter session/cookie issues between ports. Use single-origin routing for best experience.

