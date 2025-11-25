# Docker Nginx Setup Status

## Current Status

❌ **Docker Daemon**: Not running
✅ **Docker Config**: `docker/nginx/dev-proxy.docker.conf` exists
✅ **Port 80**: Available (or already in use by another service)
⚠️ **Host Entry**: `dev.zena.local` not in `/etc/hosts`

## Next Steps

### Step 1: Start Docker Desktop

1. Open Docker Desktop application
2. Wait for Docker to fully start (whale icon in menu bar should be steady)
3. Verify Docker is running:
   ```bash
   docker info
   ```

### Step 2: Add Host Entry

```bash
./scripts/setup-dev-hosts.sh
```

Or manually:
```bash
sudo echo "127.0.0.1 dev.zena.local" >> /etc/hosts
```

### Step 3: Ensure Services Are Running

**Laravel Backend:**
```bash
php artisan serve --port=8000 --host=0.0.0.0
```

**React Frontend:**
```bash
cd frontend
npm run dev
```

### Step 4: Start Nginx Container

Once Docker is running, use one of these methods:

**Option A: Quick Script**
```bash
./scripts/start-dev-proxy-docker.sh
```

**Option B: Docker Command**
```bash
docker run -d \
  --name zenamanage-dev-proxy \
  -p 80:80 \
  -v $(pwd)/docker/nginx/dev-proxy.docker.conf:/etc/nginx/conf.d/default.conf:ro \
  --add-host=host.docker.internal:host-gateway \
  nginx:alpine
```

**Option C: Docker Compose**
```bash
docker-compose -f docker-compose.dev-proxy.yml up -d
```

### Step 5: Verify

```bash
# Check container status
docker ps | grep zenamanage-dev-proxy

# View logs
docker logs zenamanage-dev-proxy

# Test URLs
curl http://dev.zena.local/admin/users
curl http://dev.zena.local/app/dashboard
```

## Troubleshooting

### Port 80 Already in Use

If port 80 is already in use (e.g., by Apache/XAMPP):

**Option 1: Stop existing service**
```bash
# For XAMPP Apache
sudo /Applications/XAMPP/xamppfiles/xampp stopapache

# Or find and stop the service
sudo lsof -i :80
```

**Option 2: Use different port**
Edit `docker/nginx/dev-proxy.docker.conf`:
```nginx
listen 8080;  # Change from 80 to 8080
```

Then start container with:
```bash
docker run -d \
  --name zenamanage-dev-proxy \
  -p 8080:8080 \
  ...
```

And access: `http://dev.zena.local:8080`

### Docker Connection Issues

If you see "Cannot connect to Docker daemon":

1. **Check Docker Desktop is running:**
   - macOS: Look for Docker icon in menu bar
   - Click icon → "Open Docker Desktop"

2. **Restart Docker:**
   ```bash
   # macOS
   osascript -e 'quit app "Docker"'
   open -a Docker
   ```

3. **Check Docker socket:**
   ```bash
   ls -la ~/.docker/run/docker.sock
   ```

### Container Won't Start

1. **Check logs:**
   ```bash
   docker logs zenamanage-dev-proxy
   ```

2. **Verify config file:**
   ```bash
   docker run --rm -v $(pwd)/docker/nginx/dev-proxy.docker.conf:/etc/nginx/conf.d/default.conf:ro nginx:alpine nginx -t
   ```

3. **Check port availability:**
   ```bash
   lsof -i :80
   ```

## Alternative: Use System Nginx

If Docker is not available, you can use system Nginx:

```bash
# Install Nginx
brew install nginx

# Test config
sudo nginx -t -c $(pwd)/docker/nginx/dev-proxy.conf

# Start Nginx
sudo nginx -c $(pwd)/docker/nginx/dev-proxy.conf
```

Note: Use `dev-proxy.conf` (not `dev-proxy.docker.conf`) for system Nginx.

