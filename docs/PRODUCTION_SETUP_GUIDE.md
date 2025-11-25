# 🚀 Production Setup Guide

**Date:** January 19, 2025  
**Purpose:** Complete guide for setting up ZenaManage in production

---

## 📋 Prerequisites

- PHP 8.0+ with required extensions
- MySQL 8.0+ or MariaDB 10.3+
- Redis (for cache and queues)
- Node.js 18+ and npm (for frontend)
- Composer
- Meilisearch (optional, for search)
- OpenTelemetry collector (optional, for tracing)

---

## 🔧 Step-by-Step Setup

### 1. Environment Configuration

Copy and configure `.env` file:

```bash
cp .env.example .env
```

Edit `.env` and configure:

#### Required Settings
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=zenamanage
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password

QUEUE_CONNECTION=redis
```

#### Meilisearch (Recommended)
```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-meilisearch-key
```

#### OpenTelemetry (Optional)
```env
OPENTELEMETRY_ENABLED=true
OPENTELEMETRY_TRACE_EXPORTER=otlp
OPENTELEMETRY_OTLP_ENDPOINT=http://localhost:4318/v1/traces
```

#### Media Pipeline
```env
MEDIA_VIRUS_SCAN_ENABLED=true
MEDIA_STRIP_EXIF=true
MEDIA_IMAGE_PROCESSING_ENABLED=true
MEDIA_CDN_ENABLED=false
MEDIA_DEFAULT_QUOTA_MB=10240
```

---

### 2. Install Dependencies

```bash
# Backend dependencies
composer install --no-dev --optimize-autoloader

# Frontend dependencies
cd frontend
npm install
cd ..
```

---

### 3. Generate Application Key

```bash
php artisan key:generate
```

---

### 4. Run Database Migrations

```bash
php artisan migrate --force
```

This will create all necessary tables including:
- `outbox` (for transactional outbox)
- `idempotency_keys` (for idempotency)
- `tenants` with `media_quota_mb` and `media_used_mb` columns

---

### 5. Initialize Search Indexes

If Meilisearch is configured:

```bash
php artisan search:init
```

Or manually:
```bash
php artisan scout:import "App\Models\Project"
php artisan scout:import "App\Models\Task"
php artisan scout:import "App\Models\Document"
```

---

### 6. Generate Frontend Types

```bash
cd frontend
npm run generate:api-types
npm run generate:abilities
cd ..
```

---

### 7. Optimize Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### 8. Automated Setup (Alternative)

Use the automated setup script:

```bash
chmod +x scripts/setup-production.sh
./scripts/setup-production.sh
```

Or use the artisan command:

```bash
php artisan setup:production
```

---

## 🔍 Health Check

After setup, verify everything is working:

```bash
php artisan health:check
```

For detailed information:

```bash
php artisan health:check --detailed
```

---

## 🚦 Queue Workers

Start queue workers for background processing:

```bash
# Main queue worker
php artisan queue:work --queue=default

# Outbox processor (for reliable event publishing)
php artisan queue:work --queue=outbox

# Search indexer (for Meilisearch indexing)
php artisan queue:work --queue=search

# Media processor (for image processing)
php artisan queue:work --queue=media
```

**Production Recommendation:** Use supervisor or systemd to manage queue workers:

```ini
# /etc/supervisor/conf.d/zenamanage-worker.conf
[program:zenamanage-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/zenamanage/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/zenamanage/storage/logs/worker.log
stopwaitsecs=3600
```

---

## 📊 Monitoring

### Outbox Metrics

Check outbox health:

```bash
php artisan tinker
>>> app(\App\Services\OutboxService::class)->getMetrics()
```

### Search Index Status

```bash
php artisan tinker
>>> \App\Models\Project::search('test')->count()
```

---

## 🔐 Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secure
- [ ] Redis password set
- [ ] CORS configured for production domains
- [ ] Security headers enabled
- [ ] HTTPS enabled
- [ ] File permissions correct (storage: 775, bootstrap/cache: 775)

---

## 🎯 Performance Optimization

1. **OPcache** (PHP)
   ```ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.max_accelerated_files=10000
   ```

2. **Redis** for cache and sessions

3. **CDN** for media files (if enabled)

4. **Queue workers** for heavy operations

---

## 🐛 Troubleshooting

### Migration Issues

```bash
# Check migration status
php artisan migrate:status

# Rollback and re-run
php artisan migrate:rollback
php artisan migrate
```

### Search Index Issues

```bash
# Re-index all models
php artisan search:init --force
```

### Cache Issues

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Queue Issues

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

---

## 📚 Additional Resources

- [Architecture Review & Plan](ARCHITECTURE_REVIEW_AND_PLAN.md)
- [Implementation Complete](IMPLEMENTATION_COMPLETE.md)
- [OpenAPI Specification](../docs/api/openapi.yaml)

---

**✅ Setup complete! Your ZenaManage instance is ready for production.**

