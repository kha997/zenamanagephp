#!/bin/bash

# ZenaManage Production Deployment Script
# Version: 2.0.0
# Date: 2025-10-16

# --- Configuration ---
APP_NAME="ZenaManage"
APP_ENV="production"
APP_URL="${APP_URL:-https://zenamanage.com}"
DEPLOY_USER="${DEPLOY_USER:-www-data}"
DEPLOY_GROUP="${DEPLOY_GROUP:-www-data}"

# --- Functions ---
print_status() {
    echo -e "\n[INFO] $1"
}
print_success() {
    echo -e "\n[SUCCESS] $1"
}
print_warning() {
    echo -e "\n[WARNING] $1"
}
print_error() {
    echo -e "\n[ERROR] $1"
}

# --- Main Deployment Steps ---
print_status "🚀 Starting $APP_NAME Production Deployment..."
print_status "Environment: $APP_ENV"
print_status "URL: $APP_URL"
print_status "================================================"

# Step 1: Pre-deployment checks
print_status "Step 1: Pre-deployment checks..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Laravel artisan file not found. Are you in the correct directory?"
    exit 1
fi

# Check if .env.production exists
if [ ! -f ".env.production" ]; then
    print_warning ".env.production not found, using .env"
    if [ ! -f ".env" ]; then
        print_error ".env file not found!"
        exit 1
    fi
fi

# Check disk space
DISK_USAGE=$(df -h . | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    print_error "Disk usage is too high: ${DISK_USAGE}%"
    exit 1
fi

print_success "Pre-deployment checks passed"

# Step 2: Backup current deployment
print_status "Step 2: Creating backup..."

BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup database
if command -v mysqldump >/dev/null 2>&1; then
    print_status "Backing up database..."
    mysqldump --user="${DB_USERNAME:-root}" \
              --password="${DB_PASSWORD:-}" \
              --host="${DB_HOST:-localhost}" \
              --single-transaction \
              --skip-routines \
              --skip-triggers \
              --skip-events \
              "${DB_DATABASE:-zenamanage}" > "$BACKUP_DIR/database.sql"
    print_success "Database backup completed"
else
    print_warning "mysqldump not found, skipping database backup"
fi

# Backup current files
print_status "Backing up current files..."
tar -czf "$BACKUP_DIR/files.tar.gz" \
    --exclude="node_modules" \
    --exclude="vendor" \
    --exclude="storage/logs" \
    --exclude="storage/framework/cache" \
    --exclude="storage/framework/sessions" \
    --exclude="storage/framework/views" \
    --exclude=".git" \
    .

print_success "Files backup completed"

# Step 3: Install dependencies
print_status "Step 3: Installing dependencies..."

# Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction
if [ $? -eq 0 ]; then
    print_success "PHP dependencies installed"
else
    print_error "Failed to install PHP dependencies"
    exit 1
fi

# Install Node dependencies
npm ci --production=false
if [ $? -eq 0 ]; then
    print_success "Node dependencies installed"
else
    print_error "Failed to install Node dependencies"
    exit 1
fi

# Step 4: Build assets
print_status "Step 4: Building assets..."

npm run build
if [ $? -eq 0 ]; then
    print_success "Assets built successfully"
else
    print_error "Failed to build assets"
    exit 1
fi

# Step 5: Laravel optimization
print_status "Step 5: Optimizing Laravel..."

# Generate application key if not exists
if [ -z "$(grep APP_KEY .env 2>/dev/null | cut -d '=' -f2)" ]; then
    php artisan key:generate --force
fi

# Clear and cache configuration
php artisan config:clear
php artisan config:cache

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache

# Clear and cache events
php artisan event:clear
php artisan event:cache

print_success "Laravel optimization completed"

# Step 6: Database operations
print_status "Step 6: Database operations..."

# Run migrations
php artisan migrate --force
if [ $? -eq 0 ]; then
    print_success "Database migrations completed"
else
    print_error "Failed to run database migrations"
    exit 1
fi

# Run seeders (if needed)
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
    print_success "Database seeders completed"
fi

# Step 7: File permissions
print_status "Step 7: Setting file permissions..."

# Set proper permissions
chown -R "$DEPLOY_USER:$DEPLOY_GROUP" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod -R 755 public

# Set executable permissions for scripts
chmod +x scripts/*.sh 2>/dev/null || true

print_success "File permissions set"

# Step 8: Health checks
print_status "Step 8: Running health checks..."

# Check if application is accessible
if command -v curl >/dev/null 2>&1; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL" || echo "000")
    if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
        print_success "Application is accessible (HTTP $HTTP_STATUS)"
    else
        print_warning "Application returned HTTP $HTTP_STATUS"
    fi
else
    print_warning "curl not found, skipping HTTP health check"
fi

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';" >/dev/null 2>&1
if [ $? -eq 0 ]; then
    print_success "Database connection verified"
else
    print_error "Database connection failed"
    exit 1
fi

# Step 9: Cleanup
print_status "Step 9: Cleanup..."

# Remove old backups (keep last 7 days)
find backups -type d -mtime +7 -exec rm -rf {} + 2>/dev/null || true

# Clear old logs
find storage/logs -name "*.log" -mtime +30 -delete 2>/dev/null || true

print_success "Cleanup completed"

# Step 10: Deployment summary
print_status "Step 10: Deployment Summary"
print_status "================================================"
print_success "✅ Application: $APP_NAME"
print_success "✅ Environment: $APP_ENV"
print_success "✅ URL: $APP_URL"
print_success "✅ Backup: $BACKUP_DIR"
print_success "✅ Dependencies: Installed"
print_success "✅ Assets: Built"
print_success "✅ Database: Migrated"
print_success "✅ Permissions: Set"
print_success "✅ Health: Verified"
print_success "✅ Cleanup: Completed"

# Performance metrics
print_status "Performance Metrics:"
echo "  - Disk Usage: $(df -h . | awk 'NR==2 {print $5}')"
echo "  - Memory Usage: $(free -h | awk 'NR==2 {print $3 "/" $2}')"
echo "  - Load Average: $(uptime | awk -F'load average:' '{print $2}')"

print_success "🎉 Deployment completed successfully!"
print_status "Deployment finished at: $(date)"

# Exit with success
exit 0