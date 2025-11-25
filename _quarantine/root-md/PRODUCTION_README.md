# 🚀 ZenaManage Production Setup

Quick reference for production deployment.

## ⚡ Quick Setup

### Automated Setup (Recommended)

```bash
# Make script executable
chmod +x scripts/setup-production.sh

# Run setup
./scripts/setup-production.sh
```

### Manual Setup

```bash
# 1. Configure environment
cp .env.example .env
# Edit .env with your production settings

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
cd frontend && npm install && cd ..

# 3. Generate key and run migrations
php artisan key:generate
php artisan migrate --force

# 4. Initialize search indexes
php artisan search:init

# 5. Generate frontend types
cd frontend
npm run generate:api-types
npm run generate:abilities
cd ..

# 6. Optimize caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Verify setup
php artisan health:check
```

## 🔧 Required Environment Variables

See `env.example` for all available options. Key settings:

```env
APP_ENV=production
APP_DEBUG=false
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEDIA_VIRUS_SCAN_ENABLED=true
MEDIA_STRIP_EXIF=true
```

## 🚦 Start Queue Workers

```bash
# Main queue
php artisan queue:work

# Outbox processor
php artisan queue:work --queue=outbox

# Search indexer
php artisan queue:work --queue=search

# Media processor
php artisan queue:work --queue=media
```

**Production:** Use supervisor/systemd to manage workers.

## 📊 Health Check

```bash
php artisan health:check --detailed
```

## 📚 Full Documentation

- **[Complete Production Setup Guide](docs/PRODUCTION_SETUP_GUIDE.md)**
- [Implementation Status](docs/IMPLEMENTATION_COMPLETE.md)
- [Architecture Plan](docs/ARCHITECTURE_REVIEW_AND_PLAN.md)

---

**✅ Ready for production!**

