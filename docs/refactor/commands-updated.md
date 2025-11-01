# ZENAMANAGE REFACTOR COMMANDS GUIDE

**Version:** 1.1  
**Created:** 2024-12-19  
**Updated:** 2024-12-19  
**Purpose:** Comprehensive command reference for refactoring operations and system management  

## 🛠️ **DEVELOPMENT COMMANDS**

### **Code Quality & Linting**

#### **PHP Code Quality**
```bash
# Laravel Pint (Code Style)
./vendor/bin/pint                    # Fix code style issues
./vendor/bin/pint --test            # Check code style without fixing
./vendor/bin/pint --dirty           # Only fix changed files
./vendor/bin/pint --config=pint.json # Use custom config

# PHPStan (Static Analysis)
./vendor/bin/phpstan analyse        # Run static analysis
./vendor/bin/phpstan analyse --level=8 # Maximum strictness
./vendor/bin/phpstan analyse --memory-limit=2G # Increase memory
./vendor/bin/phpstan analyse --no-progress # No progress bar

# Larastan (Laravel-specific Analysis)
./vendor/bin/larastan analyse       # Laravel-specific static analysis
./vendor/bin/larastan analyse --memory-limit=2G
./vendor/bin/larastan analyse --no-progress

# Rector (Code Modernization)
./vendor/bin/rector process         # Apply code transformations
./vendor/bin/rector process --dry-run # Preview changes
./vendor/bin/rector process --set=laravel # Laravel-specific rules
```

#### **JavaScript/TypeScript Quality**
```bash
# ESLint
npx eslint .                        # Check all JS/TS files
npx eslint . --fix                 # Fix auto-fixable issues
npx eslint . --ext .js,.ts,.tsx    # Check specific extensions
npx eslint . --config .eslintrc.js # Use custom config

# TypeScript Compiler
npx tsc --noEmit                   # Type check without emitting
npx tsc --noEmit --strict          # Strict type checking
npx tsc --build                     # Build TypeScript project

# Prettier
npx prettier --check .             # Check formatting
npx prettier --write .              # Fix formatting
npx prettier --write "src/**/*.{js,ts,tsx}" # Format specific files
```

### **Testing Commands**

#### **PHP Testing**
```bash
# PHPUnit
./vendor/bin/phpunit                # Run all tests
./vendor/bin/phpunit --testdox      # Human-readable output
./vendor/bin/phpunit --coverage-html coverage/ # Generate HTML coverage
./vendor/bin/phpunit --coverage-text # Generate text coverage
./vendor/bin/phpunit --filter=Feature # Run only feature tests
./vendor/bin/phpunit --filter=Unit   # Run only unit tests

# Laravel Testing
php artisan test                    # Run Laravel tests
php artisan test --coverage         # Run with coverage
php artisan test --parallel         # Run tests in parallel
php artisan test --stop-on-failure # Stop on first failure
php artisan test --group=slow       # Run specific test group
```

#### **E2E Testing**
```bash
# Playwright
npx playwright test                 # Run E2E tests
npx playwright test --headed        # Run with browser UI
npx playwright test --debug         # Run in debug mode
npx playwright test --grep="login" # Run specific tests
npx playwright test --project=chromium # Run on specific browser

# Cypress
npx cypress run                     # Run headless tests
npx cypress open                    # Open Cypress UI
npx cypress run --spec="cypress/e2e/login.cy.js" # Run specific spec
```

### **Performance & Optimization**

#### **Laravel Optimization**
```bash
# Cache Management
php artisan cache:clear             # Clear application cache
php artisan config:clear           # Clear configuration cache
php artisan route:clear             # Clear route cache
php artisan view:clear              # Clear view cache
php artisan optimize:clear          # Clear all caches

# Optimization
php artisan optimize                # Optimize for production
php artisan config:cache            # Cache configuration
php artisan route:cache             # Cache routes
php artisan view:cache              # Cache views
php artisan event:cache             # Cache events

# Database Optimization
php artisan migrate:status         # Check migration status
php artisan migrate:fresh --seed    # Fresh migration with seeding
php artisan db:seed                 # Run database seeders
php artisan migrate:rollback        # Rollback last migration
```

#### **Asset Optimization**
```bash
# Laravel Mix/Vite
npm run dev                         # Development build
npm run build                       # Production build
npm run watch                       # Watch for changes
npm run hot                         # Hot module replacement

# Asset Minification
npm run production                  # Production build with minification
npm run analyze                     # Analyze bundle size
```

### **Security & Compliance**

#### **Security Scanning**
```bash
# Composer Security
composer audit                      # Check for security vulnerabilities
composer audit --format=json        # JSON output
composer audit --only=high          # Only high severity issues

# NPM Security
npm audit                           # Check for vulnerabilities
npm audit fix                       # Fix vulnerabilities
npm audit --audit-level=high        # Only high severity

# Laravel Security
php artisan route:list --compact    # List all routes
php artisan route:list --path=admin # List admin routes
php artisan route:list --middleware=auth # List authenticated routes
```

#### **Accessibility Testing**
```bash
# Lighthouse CI
npx lighthouse-ci autorun           # Run Lighthouse CI
npx lighthouse-ci autorun --config=lighthouserc.json # Custom config
npx lighthouse-ci autorun --upload.target=temporary # Upload results

# Axe Core
npx axe http://localhost:8000       # Test accessibility
npx axe --tags wcag2a,wcag2aa      # Test specific WCAG levels
npx axe --rules color-contrast      # Test specific rules
```

## 🚀 **DEPLOYMENT COMMANDS**

### **Environment Setup**
```bash
# Environment Configuration
cp .env.example .env                # Copy environment file
php artisan key:generate            # Generate application key
php artisan config:cache            # Cache configuration
php artisan route:cache             # Cache routes
php artisan view:cache              # Cache views

# Database Setup
php artisan migrate                 # Run migrations
php artisan db:seed                 # Seed database
php artisan migrate:fresh --seed    # Fresh migration with seeding
```

### **Production Deployment**
```bash
# Pre-deployment Checks
php artisan optimize:clear          # Clear all caches
php artisan config:cache            # Cache configuration
php artisan route:cache             # Cache routes
php artisan view:cache              # Cache views
php artisan event:cache             # Cache events

# Asset Compilation
npm run production                  # Build production assets
npm run build                       # Build assets

# Database Migration
php artisan migrate --force         # Force migration in production
php artisan migrate:status         # Check migration status

# Cache Warmup
php artisan cache:warmup            # Warm up cache (if implemented)
```

### **Rollback Procedures**
```bash
# Code Rollback
git revert HEAD                     # Revert last commit
git reset --hard HEAD~1             # Reset to previous commit
git checkout <previous-commit>       # Checkout previous commit

# Database Rollback
php artisan migrate:rollback        # Rollback last migration
php artisan migrate:rollback --step=3 # Rollback 3 migrations
php artisan migrate:reset           # Rollback all migrations

# Cache Rollback
php artisan cache:clear             # Clear application cache
php artisan config:clear           # Clear configuration cache
php artisan route:clear             # Clear route cache
php artisan view:clear              # Clear view cache
```

## 🔧 **MAINTENANCE COMMANDS**

### **System Health Checks**
```bash
# Application Health
php artisan health:check            # Check application health
php artisan health:check --detailed # Detailed health check
php artisan health:check --format=json # JSON output

# Database Health
php artisan db:show                # Show database information
php artisan db:monitor              # Monitor database performance
php artisan migrate:status         # Check migration status

# Cache Health
php artisan cache:table             # Create cache table
php artisan queue:table             # Create queue table
php artisan session:table           # Create session table
```

### **Log Management**
```bash
# Log Viewing
tail -f storage/logs/laravel.log    # Follow Laravel log
tail -f storage/logs/laravel.log | grep ERROR # Filter errors
grep "ERROR" storage/logs/laravel.log # Search for errors

# Log Rotation
php artisan log:clear               # Clear log files (if implemented)
logrotate /etc/logrotate.d/laravel  # Rotate logs
```

### **Queue Management**
```bash
# Queue Operations
php artisan queue:work               # Start queue worker
php artisan queue:restart           # Restart queue workers
php artisan queue:failed            # List failed jobs
php artisan queue:retry all         # Retry all failed jobs
php artisan queue:flush             # Flush all failed jobs
```

## 📊 **MONITORING COMMANDS**

### **Performance Monitoring**
```bash
# Application Performance
php artisan monitor:performance     # Monitor performance (if implemented)
php artisan monitor:memory         # Monitor memory usage
php artisan monitor:cpu            # Monitor CPU usage

# Database Performance
php artisan db:monitor              # Monitor database performance
php artisan db:slow-queries         # Show slow queries
php artisan db:connections          # Show database connections
```

### **Legacy Route Monitoring**
```bash
# Legacy Route Usage
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/usage

# Migration Phase Status
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/migration-phase

# Generate Report
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/report

# Clean Old Data
curl -X POST -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/cleanup \
     -d '{"days_to_keep": 30}'
```

## 🧪 **TESTING SCRIPTS**

### **Comprehensive Test Suite**
```bash
#!/bin/bash
# run-tests.sh - Comprehensive test runner

echo "🧪 Running ZenaManage Test Suite..."

# Code Quality Checks
echo "📋 Running code quality checks..."
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/larastan analyse

# Unit Tests
echo "🔬 Running unit tests..."
./vendor/bin/phpunit --testsuite=Unit --coverage-text

# Integration Tests
echo "🔗 Running integration tests..."
./vendor/bin/phpunit --testsuite=Feature --coverage-text

# E2E Tests
echo "🌐 Running E2E tests..."
npx playwright test

# Accessibility Tests
echo "♿ Running accessibility tests..."
npx lighthouse-ci autorun

# Performance Tests
echo "⚡ Running performance tests..."
npm run test:performance

echo "✅ All tests completed!"
```

### **Legacy Route Test Script**
```bash
#!/bin/bash
# test-legacy-routes.sh - Test legacy route functionality

echo "🔄 Testing Legacy Route Migration..."

# Test deprecation headers
echo "📋 Testing deprecation headers..."
curl -I http://localhost:8000/dashboard | grep -i deprecation

# Test redirects
echo "🔄 Testing redirects..."
curl -L http://localhost:8000/dashboard

# Test 410 responses (after removal date)
echo "❌ Testing 410 responses..."
curl -i http://localhost:8000/dashboard

# Test monitoring endpoints
echo "📊 Testing monitoring endpoints..."
curl -H "Authorization: Bearer <token>" \
     http://localhost:8000/api/v1/legacy-routes/usage

echo "✅ Legacy route tests completed!"
```

## 🔍 **DEBUGGING COMMANDS**

### **Application Debugging**
```bash
# Debug Mode
php artisan serve --host=0.0.0.0 --port=8000 # Start development server
php artisan tinker                 # Interactive PHP shell
php artisan route:list             # List all routes
php artisan route:list --path=api  # List API routes

# Debug Information
php artisan about                  # Show application information
php artisan env                    # Show environment information
php artisan config:show            # Show configuration
php artisan route:show             # Show route information
```

### **Database Debugging**
```bash
# Database Debugging
php artisan db:show                # Show database information
php artisan migrate:status         # Show migration status
php artisan schema:dump            # Dump database schema
php artisan db:seed --class=DebugSeeder # Run debug seeder
```

## 📈 **ANALYTICS COMMANDS**

### **Usage Analytics**
```bash
# Application Analytics
php artisan analytics:usage        # Show usage analytics (if implemented)
php artisan analytics:users         # Show user analytics
php artisan analytics:performance   # Show performance analytics

# Legacy Route Analytics
php artisan legacy:analytics        # Show legacy route analytics
php artisan legacy:usage            # Show legacy route usage
php artisan legacy:migration        # Show migration progress
```

## 🔐 **SECURITY COMMANDS**

### **Security Auditing**
```bash
# Security Checks
php artisan security:audit         # Run security audit (if implemented)
php artisan security:check         # Check security configuration
php artisan security:scan          # Scan for vulnerabilities

# User Management
php artisan user:list              # List all users
php artisan user:create            # Create new user
php artisan user:delete            # Delete user
php artisan user:reset-password     # Reset user password
```

## 📋 **UTILITY SCRIPTS**

### **System Utilities**
```bash
# File Management
find . -name "*.php" -exec php -l {} \; # Check PHP syntax
find . -name "*.blade.php" -exec php -l {} \; # Check Blade syntax

# Database Utilities
php artisan db:backup              # Backup database (if implemented)
php artisan db:restore             # Restore database (if implemented)
php artisan db:optimize            # Optimize database

# Cache Utilities
php artisan cache:clear            # Clear all caches
php artisan cache:forget key       # Forget specific cache key
php artisan cache:remember key value # Remember cache value
```

### **Development Utilities**
```bash
# Code Generation
php artisan make:controller        # Generate controller
php artisan make:model             # Generate model
php artisan make:migration          # Generate migration
php artisan make:seeder            # Generate seeder
php artisan make:test              # Generate test

# Code Analysis
php artisan route:list --compact   # Compact route list
php artisan route:list --json      # JSON route list
php artisan route:list --columns=uri,name,action # Specific columns
```

## 🚨 **EMERGENCY COMMANDS**

### **Emergency Procedures**
```bash
# Emergency Rollback
git revert HEAD                     # Revert last commit
php artisan migrate:rollback       # Rollback database
php artisan cache:clear            # Clear all caches

# Emergency Maintenance
php artisan down --message="Emergency maintenance" # Put site in maintenance mode
php artisan up                      # Bring site back online

# Emergency Debugging
php artisan tinker                 # Interactive debugging
php artisan log:clear               # Clear logs
php artisan queue:restart           # Restart queue workers
```

---

**Last Updated:** December 19, 2024  
**Version:** 1.1  
**Maintainer:** ZenaManage Development Team
