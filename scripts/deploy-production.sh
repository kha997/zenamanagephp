#!/bin/bash

# Production Deployment Script
# Triển khai ứng dụng lên môi trường production

set -e

# Configuration
APP_DIR="/var/www/html"
BACKUP_DIR="/var/backups/zenamanage"
LOG_FILE="/var/log/zenamanage/deployment.log"
DATE=$(date '+%Y%m%d_%H%M%S')
GIT_BRANCH="${1:-main}"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "Starting deployment of branch: $GIT_BRANCH"

# 1. Create backup before deployment
log "Creating backup before deployment..."
mkdir -p "$BACKUP_DIR/$DATE"
cp -r "$APP_DIR" "$BACKUP_DIR/$DATE/app_backup"

# 2. Put application in maintenance mode
log "Enabling maintenance mode..."
cd "$APP_DIR"
php artisan down --message="Deploying new version" --retry=60

# 3. Pull latest code
log "Pulling latest code from $GIT_BRANCH..."
git fetch origin
git checkout "$GIT_BRANCH"
git pull origin "$GIT_BRANCH"

# 4. Install/update dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

log "Installing NPM dependencies..."
npm ci --production

# 5. Build frontend assets
log "Building frontend assets..."
npm run build

# 6. Clear and cache configuration
log "Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# 7. Run database migrations
log "Running database migrations..."
php artisan migrate --force

# 8. Clear application cache
log "Clearing application cache..."
php artisan cache:clear
php artisan queue:restart

# 9. Set proper permissions
log "Setting file permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# 10. Restart services
log "Restarting services..."
sudo systemctl reload nginx
sudo systemctl restart php8.0-fpm
sudo supervisorctl restart all

# 11. Run health check
log "Running health check..."
sleep 10
HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/health)
if [ "$HEALTH_STATUS" != "200" ]; then
    log "ERROR: Health check failed with status $HEALTH_STATUS"
    log "Rolling back deployment..."
    
    # Rollback
    php artisan up
    cp -r "$BACKUP_DIR/$DATE/app_backup/*" "$APP_DIR/"
    sudo systemctl reload nginx
    sudo systemctl restart php8.0-fpm
    
    log "Rollback completed. Deployment failed."
    exit 1
fi

# 12. Disable maintenance mode
log "Disabling maintenance mode..."
php artisan up

# 13. Clean up old backups (keep last 5)
log "Cleaning up old backups..."
cd "$BACKUP_DIR"
ls -t | tail -n +6 | xargs -r rm -rf

log "Deployment completed successfully!"
log "Application is now live with the latest changes from $GIT_BRANCH"