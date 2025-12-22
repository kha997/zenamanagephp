# 📋 Production Setup - Quick Checklist

**Quick reference checklist for production deployment.**

## ✅ Pre-Deployment Checklist

- [ ] `.env` file configured with production values
- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Database credentials configured
- [ ] Redis configured
- [ ] Meilisearch installed and configured (optional but recommended)
- [ ] File storage configured (local or S3)
- [ ] Queue connection set to `redis`

## 🚀 Deployment Steps

1. **Install Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   cd frontend && npm install && cd ..
   ```

2. **Generate Key & Migrate**
   ```bash
   php artisan key:generate
   php artisan migrate --force
   ```

3. **Initialize Search** (if Meilisearch enabled)
   ```bash
   php artisan search:init
   ```

4. **Generate Frontend Types**
   ```bash
   cd frontend
   npm run generate:api-types
   npm run generate:abilities
   cd ..
   ```

5. **Optimize Caches**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Health Check**
   ```bash
   php artisan health:check --detailed
   ```

## 🔄 Post-Deployment

- [ ] Start queue workers (supervisor/systemd)
- [ ] Configure monitoring (OpenTelemetry optional)
- [ ] Set up CDN for media (optional)
- [ ] Configure backup schedule
- [ ] Set up log rotation
- [ ] Configure SSL/HTTPS

## 📚 Full Guide

See **[PRODUCTION_SETUP_GUIDE.md](PRODUCTION_SETUP_GUIDE.md)** for detailed instructions.

---

**✅ Ready to deploy!**

