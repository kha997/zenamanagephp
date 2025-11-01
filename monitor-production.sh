#!/bin/bash

# ZenaManage Production Monitoring Script
# Version: 2.1.1
# Purpose: Monitor production health and performance

echo "🔍 ZenaManage Production Monitoring"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

print_status "Starting Production Health Check..."

# 1. Application Status
print_status "1. Application Status Check"
echo "--------------------------------"
php artisan env
echo ""

# 2. Database Connection
print_status "2. Database Connection Test"
echo "--------------------------------"
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database: ✅ Connected'; } catch(Exception \$e) { echo 'Database: ❌ Error - ' . \$e->getMessage(); }"
echo ""

# 3. Cache Status
print_status "3. Cache System Check"
echo "-------------------------"
php artisan tinker --execute="try { Cache::put('health_check', 'ok', 1); echo 'Cache: ✅ Working - ' . Cache::get('health_check'); } catch(Exception \$e) { echo 'Cache: ❌ Error - ' . \$e->getMessage(); }"
echo ""

# 4. Storage Status
print_status "4. Storage System Check"
echo "--------------------------"
if [ -L "public/storage" ]; then
    print_success "Storage symlink: ✅ Connected"
else
    print_warning "Storage symlink: ⚠️ Missing"
fi

if [ -d "storage/logs" ]; then
    print_success "Logs directory: ✅ Exists"
    echo "Recent log files:"
    ls -la storage/logs/ | head -n 5
else
    print_error "Logs directory: ❌ Missing"
fi
echo ""

# 5. Asset Status
print_status "5. Asset Status Check"
echo "-------------------------"
if [ -d "public/build/assets" ]; then
    print_success "Built assets: ✅ Present"
    echo "Asset files:"
    ls -la public/build/assets/ | head -n 5
else
    print_warning "Built assets: ⚠️ Missing - run 'npm run build'"
fi
echo ""

# 6. Route Status
print_status "6. Route Status Check"
echo "-------------------------"
php artisan route:list --compact | head -n 10
echo ""

# 7. Configuration Status
print_status "7. Configuration Status"
echo "---------------------------"
if [ -f "bootstrap/cache/config.php" ]; then
    print_success "Config cache: ✅ Present"
else
    print_warning "Config cache: ⚠️ Missing - run 'php artisan config:cache'"
fi

if [ -f "bootstrap/cache/routes-v7.php" ]; then
    print_success "Route cache: ✅ Present"
else
    print_warning "Route cache: ⚠️ Missing - run 'php artisan route:cache'"
fi
echo ""

# 8. Error Log Check
print_status "8. Error Log Analysis"
echo "------------------------"
if [ -f "storage/logs/laravel.log" ]; then
    echo "Recent errors (last 10 lines):"
    tail -10 storage/logs/laravel.log | grep -i "error\|exception\|fatal" || echo "No recent errors found"
else
    print_warning "No log file found"
fi
echo ""

# 9. Performance Metrics
print_status "9. Performance Metrics"
echo "---------------------------"
echo "Memory usage:"
php -r "echo 'Current: ' . memory_get_usage(true) / 1024 / 1024 . ' MB' . PHP_EOL;"
php -r "echo 'Peak: ' . memory_get_peak_usage(true) / 1024 / 1024 . ' MB' . PHP_EOL;"
echo ""

# 10. Security Headers Check
print_status "10. Security Headers Check"
echo "-----------------------------"
echo "Checking for security middleware..."
php artisan route:list | grep -i "middleware" | head -5
echo ""

print_success "🎉 Production Health Check Complete!"
echo ""
echo "📊 Summary:"
echo "  - Application: $(php artisan env | grep 'Environment' | cut -d' ' -f4)"
echo "  - Database: Checked"
echo "  - Cache: Checked"
echo "  - Storage: Checked"
echo "  - Assets: Checked"
echo "  - Routes: Checked"
echo "  - Config: Checked"
echo "  - Logs: Checked"
echo "  - Performance: Checked"
echo "  - Security: Checked"
echo ""
echo "📋 Next Steps:"
echo "  1. Monitor logs regularly"
echo "  2. Check performance metrics"
echo "  3. Review error patterns"
echo "  4. Update monitoring as needed"
