#!/bin/bash

# ZenaManage Deployment Script
# This script handles the deployment of ZenaManage to production

set -e

echo "🚀 Starting ZenaManage Deployment..."

# Configuration
APP_NAME="zenamanage"
APP_ENV="production"
APP_URL="https://zenamanage.com"
DB_HOST="localhost"
DB_DATABASE="zenamanage_production"
DB_USERNAME="zenamanage_user"
DB_PASSWORD="secure_password"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   log_error "This script should not be run as root"
   exit 1
fi

# Check if Laravel is installed
if [ ! -f "artisan" ]; then
    log_error "Laravel not found. Please run this script from the Laravel root directory."
    exit 1
fi

# Backup current deployment
log_info "Creating backup of current deployment..."
if [ -d "backup" ]; then
    rm -rf backup
fi
mkdir -p backup
cp -r . backup/ 2>/dev/null || true

# Pull latest code
log_info "Pulling latest code from repository..."
git pull origin main

# Install/Update dependencies
log_info "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Install npm dependencies and build assets
log_info "Installing npm dependencies and building assets..."
npm install
npm run production

# Set permissions
log_info "Setting proper permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Clear caches
log_info "Clearing application caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run migrations
log_info "Running database migrations..."
php artisan migrate --force

# Seed database (if needed)
log_info "Seeding database..."
php artisan db:seed --force

# Optimize application
log_info "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
log_info "Restarting services..."
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm

# Run health checks
log_info "Running health checks..."
php artisan health:check

# Test deployment
log_info "Testing deployment..."
curl -f http://localhost/health || log_warning "Health check failed"

log_info "✅ Deployment completed successfully!"

# Cleanup
log_info "Cleaning up..."
rm -rf backup

echo "🎉 ZenaManage has been deployed successfully!"
echo "📊 Application URL: $APP_URL"
echo "📈 Health Check: $APP_URL/health"
echo "📋 Logs: storage/logs/laravel.log"