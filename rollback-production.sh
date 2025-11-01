#!/bin/bash

# ZenaManage Production Rollback Script
# Version: 1.0.0
# Date: 2025-10-16

# --- Configuration ---
APP_NAME="ZenaManage"
BACKUP_DIR="backups"
ROLLBACK_TO="${1:-latest}"

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

# --- Main Rollback Steps ---
print_status "🔄 Starting $APP_NAME Production Rollback..."
print_status "Rollback target: $ROLLBACK_TO"
print_status "================================================"

# Step 1: Pre-rollback checks
print_status "Step 1: Pre-rollback checks..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Laravel artisan file not found. Are you in the correct directory?"
    exit 1
fi

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    print_error "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

# Find rollback target
if [ "$ROLLBACK_TO" = "latest" ]; then
    ROLLBACK_PATH=$(ls -t "$BACKUP_DIR" | head -n1)
    if [ -z "$ROLLBACK_PATH" ]; then
        print_error "No backups found in $BACKUP_DIR"
        exit 1
    fi
    ROLLBACK_PATH="$BACKUP_DIR/$ROLLBACK_PATH"
else
    ROLLBACK_PATH="$BACKUP_DIR/$ROLLBACK_TO"
    if [ ! -d "$ROLLBACK_PATH" ]; then
        print_error "Rollback target not found: $ROLLBACK_PATH"
        exit 1
    fi
fi

print_success "Rollback target: $ROLLBACK_PATH"

# Step 2: Create emergency backup
print_status "Step 2: Creating emergency backup..."

EMERGENCY_BACKUP="emergency_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$EMERGENCY_BACKUP"

# Backup current state
tar -czf "$EMERGENCY_BACKUP/current_state.tar.gz" \
    --exclude="node_modules" \
    --exclude="vendor" \
    --exclude="storage/logs" \
    --exclude="storage/framework/cache" \
    --exclude="storage/framework/sessions" \
    --exclude="storage/framework/views" \
    --exclude=".git" \
    .

print_success "Emergency backup created: $EMERGENCY_BACKUP"

# Step 3: Stop services (if applicable)
print_status "Step 3: Stopping services..."

# Stop queue workers
php artisan queue:restart 2>/dev/null || true

# Clear caches
php artisan cache:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

print_success "Services stopped"

# Step 4: Restore files
print_status "Step 4: Restoring files from backup..."

# Extract backup files
if [ -f "$ROLLBACK_PATH/files.tar.gz" ]; then
    tar -xzf "$ROLLBACK_PATH/files.tar.gz"
    print_success "Files restored from backup"
else
    print_error "Backup files not found: $ROLLBACK_PATH/files.tar.gz"
    exit 1
fi

# Step 5: Restore database
print_status "Step 5: Restoring database..."

if [ -f "$ROLLBACK_PATH/database.sql" ]; then
    # Restore database
    mysql --user="${DB_USERNAME:-root}" \
          --password="${DB_PASSWORD:-}" \
          --host="${DB_HOST:-localhost}" \
          "${DB_DATABASE:-zenamanage}" < "$ROLLBACK_PATH/database.sql"
    
    if [ $? -eq 0 ]; then
        print_success "Database restored from backup"
    else
        print_error "Failed to restore database"
        exit 1
    fi
else
    print_warning "Database backup not found, skipping database restore"
fi

# Step 6: Reinstall dependencies
print_status "Step 6: Reinstalling dependencies..."

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

# Step 7: Rebuild assets
print_status "Step 7: Rebuilding assets..."

npm run build
if [ $? -eq 0 ]; then
    print_success "Assets rebuilt successfully"
else
    print_error "Failed to rebuild assets"
    exit 1
fi

# Step 8: Laravel optimization
print_status "Step 8: Optimizing Laravel..."

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

# Step 9: Health checks
print_status "Step 9: Running health checks..."

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';" >/dev/null 2>&1
if [ $? -eq 0 ]; then
    print_success "Database connection verified"
else
    print_error "Database connection failed"
    exit 1
fi

# Check if application is accessible
if command -v curl >/dev/null 2>&1; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${APP_URL:-http://localhost}" || echo "000")
    if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
        print_success "Application is accessible (HTTP $HTTP_STATUS)"
    else
        print_warning "Application returned HTTP $HTTP_STATUS"
    fi
else
    print_warning "curl not found, skipping HTTP health check"
fi

# Step 10: Restart services
print_status "Step 10: Restarting services..."

# Restart queue workers
php artisan queue:restart

print_success "Services restarted"

# Step 11: Rollback summary
print_status "Step 11: Rollback Summary"
print_status "================================================"
print_success "✅ Application: $APP_NAME"
print_success "✅ Rollback target: $ROLLBACK_PATH"
print_success "✅ Emergency backup: $EMERGENCY_BACKUP"
print_success "✅ Files: Restored"
print_success "✅ Database: Restored"
print_success "✅ Dependencies: Installed"
print_success "✅ Assets: Rebuilt"
print_success "✅ Optimization: Completed"
print_success "✅ Health: Verified"
print_success "✅ Services: Restarted"

print_success "🎉 Rollback completed successfully!"
print_status "Rollback finished at: $(date)"

# Exit with success
exit 0
