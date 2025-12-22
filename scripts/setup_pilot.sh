#!/bin/bash

# ZenaManage Pilot Setup Script
# This script sets up the application for pilot deployment

set -e  # Exit on error

echo "=========================================="
echo "ZenaManage Pilot Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

# Check if .env exists
if [ ! -f .env ]; then
    print_error ".env file not found!"
    print_info "Copying .env.pilot.example to .env..."
    if [ -f .env.pilot.example ]; then
        cp .env.pilot.example .env
        print_success ".env file created from .env.pilot.example"
        print_info "Please update .env with your actual configuration values"
        exit 1
    else
        print_error ".env.pilot.example not found!"
        exit 1
    fi
fi

# Step 1: Install PHP dependencies
print_info "Step 1: Installing PHP dependencies..."
if composer install --no-dev --optimize-autoloader; then
    print_success "PHP dependencies installed"
else
    print_error "Failed to install PHP dependencies"
    exit 1
fi

# Step 2: Generate application key if not set
print_info "Step 2: Checking application key..."
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    print_info "Generating application key..."
    php artisan key:generate --force
    print_success "Application key generated"
else
    print_success "Application key already set"
fi

# Step 3: Run database migrations
print_info "Step 3: Running database migrations..."
if php artisan migrate --force; then
    print_success "Database migrations completed"
else
    print_error "Failed to run database migrations"
    exit 1
fi

# Step 4: Seed demo tenant and users
print_info "Step 4: Seeding demo tenant and users..."
if php artisan db:seed --class=DemoTenantSeeder --force; then
    print_success "Demo tenant and users seeded"
else
    print_error "Failed to seed demo data"
    exit 1
fi

# Step 5: Install and build frontend
print_info "Step 5: Installing and building frontend..."
cd frontend || exit 1
if npm ci; then
    print_success "Frontend dependencies installed"
else
    print_error "Failed to install frontend dependencies"
    exit 1
fi

if npm run build; then
    print_success "Frontend built successfully"
else
    print_error "Failed to build frontend"
    exit 1
fi
cd .. || exit 1

# Step 6: Clear and cache configuration
print_info "Step 6: Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache
print_success "Configuration cached"

# Step 7: Cache routes
print_info "Step 7: Caching routes..."
php artisan route:clear
php artisan route:cache
print_success "Routes cached"

# Step 8: Cache views
print_info "Step 8: Caching views..."
php artisan view:clear
php artisan view:cache
print_success "Views cached"

# Step 9: Optimize autoloader
print_info "Step 9: Optimizing autoloader..."
composer dump-autoload --optimize --classmap-authoritative
print_success "Autoloader optimized"

# Step 10: Set permissions (if needed)
print_info "Step 10: Setting storage and cache permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
print_success "Permissions set"

echo ""
echo "=========================================="
print_success "Pilot setup completed successfully!"
echo "=========================================="
echo ""
print_info "Next steps:"
echo "  1. Update .env with your actual configuration"
echo "  2. Start the queue worker: php artisan queue:work"
echo "  3. Configure your web server to point to the public directory"
echo "  4. Access the application at your configured APP_URL"
echo ""
print_info "Demo login credentials:"
echo "  Admin:    admin@zena-demo.com / password123"
echo "  PM:       pm@zena-demo.com / password123"
echo "  Designer: designer@zena-demo.com / password123"
echo "  QC:       qc@zena-demo.com / password123"
echo "  Member:   member@zena-demo.com / password123"
echo ""

