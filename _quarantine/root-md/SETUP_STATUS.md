# Development Setup Status

## Current Status

✅ **Laravel Backend**: Running on port 8000
✅ **React Frontend**: Running on port 5173
✅ **Port 80**: Available
❌ **Docker**: Not running (optional)
❌ **Nginx**: Not installed/running
❌ **Host Entry**: dev.zena.local not in /etc/hosts

## Next Steps

### Step 1: Add Host Entry

Run this command (requires sudo password):

```bash
./scripts/setup-dev-hosts.sh
```

Or manually:
```bash
sudo echo "127.0.0.1 dev.zena.local" >> /etc/hosts
```

### Step 2: Start Nginx Dev Proxy

You have 3 options:

#### Option A: Docker (Recommended if Docker is available)

1. Start Docker Desktop
2. Run:
```bash
docker run -d \
  --name zenamanage-dev-proxy \
  -p 80:80 \
  -v $(pwd)/docker/nginx/dev-proxy.conf:/etc/nginx/conf.d/default.conf:ro \
  --add-host=host.docker.internal:host-gateway \
  nginx:alpine
```

Note: You'll need to update `dev-proxy.conf` to use `host.docker.internal` instead of `localhost` for Docker.

#### Option B: Install and Use System Nginx

```bash
# Install Nginx
brew install nginx

# Test config
sudo nginx -t -c $(pwd)/docker/nginx/dev-proxy.conf

# Start Nginx
sudo nginx -c $(pwd)/docker/nginx/dev-proxy.conf
```

#### Option C: Use the Start Script

```bash
./scripts/start-dev-proxy.sh
```

### Step 3: Test

Once Nginx is running, test:

- **Admin (Blade)**: http://dev.zena.local/admin/users
- **App (React)**: http://dev.zena.local/app/dashboard
- **API**: http://dev.zena.local/api/v1/...

## Quick Commands

```bash
# Add host entry
./scripts/setup-dev-hosts.sh

# Start Nginx (choose one method above)
./scripts/start-dev-proxy.sh

# Check services
curl http://localhost:8000  # Laravel
curl http://localhost:5173  # React

# Test single-origin
curl http://dev.zena.local/admin/users
curl http://dev.zena.local/app/dashboard
```

## Troubleshooting

See `docs/QUICK_START_DEV.md` for detailed troubleshooting.

