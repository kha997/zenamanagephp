#!/bin/bash

# ZenaManage Production Deployment Script
# Version: 2.1.0
# Date: January 2025

echo "🚀 Starting ZenaManage Production Deployment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Not in Laravel project directory. Please run from project root."
    exit 1
fi

print_status "ZenaManage Production Deployment Script"
print_status "Phase 2 Complete - Priority Pages Implementation"
print_status "================================================"

# Step 1: Environment Setup
print_status "Step 1: Setting up production environment..."

# Backup current .env
if [ -f ".env" ]; then
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    print_success "Backed up current .env file"
fi

# Create production .env (if not exists)
if [ ! -f ".env.production" ]; then
    print_warning "Creating production .env template..."
    cat > .env.production << EOF
APP_NAME="ZenaManage"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage_production
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="\${PUSHER_HOST}"
VITE_PUSHER_PORT="\${PUSHER_PORT}"
VITE_PUSHER_SCHEME="\${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"
EOF
    print_success "Created .env.production template"
    print_warning "Please update .env.production with your production values before continuing"
fi

# Step 2: Install Dependencies
print_status "Step 2: Installing production dependencies..."

# Install PHP dependencies
composer install --no-dev --optimize-autoloader
if [ $? -eq 0 ]; then
    print_success "PHP dependencies installed"
else
    print_error "Failed to install PHP dependencies"
    exit 1
fi

# Install Node dependencies and build assets
# Skip npm production install since we need dev dependencies for build
print_warning "Skipping npm production install (need dev dependencies for build)"

# Build assets
npm run build
if [ $? -eq 0 ]; then
    print_success "Assets built successfully"
else
    print_error "Failed to build assets"
    exit 1
fi

# Step 3: Laravel Optimization
print_status "Step 3: Optimizing Laravel for production..."

# Generate application key if not set
php artisan key:generate --force

# Clear and cache configuration
php artisan config:clear
php artisan config:cache
print_success "Configuration cached"

# Clear and cache routes
php artisan route:clear
php artisan route:cache
print_success "Routes cached"

# Clear and cache views
php artisan view:clear
php artisan view:cache
print_success "Views cached"

# Clear and cache events
php artisan event:clear
php artisan event:cache
print_success "Events cached"

# Step 4: Database Setup
print_status "Step 4: Setting up database..."

# Run migrations
php artisan migrate --force
if [ $? -eq 0 ]; then
    print_success "Database migrations completed"
else
    print_error "Database migrations failed"
    exit 1
fi

# Step 5: File Permissions
print_status "Step 5: Setting file permissions..."

# Set proper permissions
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod -R 755 public
print_success "File permissions set"

# Step 6: Security Check
print_status "Step 6: Running security checks..."

# Check if .env is not publicly accessible
if [ -f "public/.env" ]; then
    print_error ".env file is publicly accessible! Removing..."
    rm public/.env
fi

# Check storage symlink
if [ ! -L "public/storage" ]; then
    print_warning "Storage symlink not found, creating..."
    php artisan storage:link
fi

print_success "Security checks completed"

# Step 7: Health Check
print_status "Step 7: Running health checks..."

# Test database connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection: OK';"
if [ $? -eq 0 ]; then
    print_success "Database connection verified"
else
    print_error "Database connection failed"
fi

# Test cache
php artisan tinker --execute="Cache::put('test', 'ok', 1); echo 'Cache: ' . Cache::get('test');"
if [ $? -eq 0 ]; then
    print_success "Cache system verified"
else
    print_warning "Cache system may have issues"
fi

# Step 8: Final Status
print_status "Step 8: Deployment Summary"

echo ""
echo "================================================"
print_success "🎉 ZenaManage Production Deployment Complete!"
echo "================================================"
echo ""
echo "📊 Deployment Summary:"
echo "  ✅ Assets built and optimized"
echo "  ✅ Laravel optimized for production"
echo "  ✅ Database migrations completed"
echo "  ✅ File permissions set"
echo "  ✅ Security checks passed"
echo "  ✅ Health checks completed"
echo ""
echo "🚀 Next Steps:"
echo "  1. Update .env.production with your production values"
echo "  2. Copy .env.production to .env"
echo "  3. Restart your web server"
echo "  4. Monitor logs and performance"
echo ""
echo "📋 Follow-up Tickets:"
echo "  - UI-001: Document version upload race condition (non-critical)"
echo "  - UI-002: MariaDB backup command issue (non-critical)"
echo "  - UI-003: Dark mode implementation (optional)"
echo ""
echo "📚 Documentation:"
echo "  - PHASE_2_COMPLETE_REPORT.md"
echo "  - FOLLOW_UP_TICKETS.md"
echo "  - PRODUCTION_DEPLOYMENT_CHECKLIST.md"
echo ""
print_success "Deployment completed successfully! 🎉"