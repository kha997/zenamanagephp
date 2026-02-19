# ZENAMANAGE REFACTOR COMMANDS GUIDE

**Version:** 1.0  
**Created:** 2024-12-19  
**Purpose:** Comprehensive command reference for refactoring operations  

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
npx prettier --write .             # Fix formatting
npx prettier --check "**/*.{js,ts,tsx}" # Check specific files
```

### **Testing Commands**

#### **PHPUnit Testing**
```bash
# Basic Testing
php artisan test                    # Run all tests
php artisan test --filter=FeatureTest # Run specific test
php artisan test --group=integration # Run test group
php artisan test --coverage         # Generate coverage report
php artisan test --coverage-html=coverage # HTML coverage report

# Advanced Testing
php artisan test --parallel        # Run tests in parallel
php artisan test --processes=4     # Use 4 processes
php artisan test --stop-on-failure # Stop on first failure
php artisan test --verbose         # Verbose output

# Direct PHPUnit
./vendor/bin/phpunit               # Run PHPUnit directly
./vendor/bin/phpunit --testdox     # TestDox output
./vendor/bin/phpunit --coverage-text # Text coverage report
./vendor/bin/phpunit tests/Feature/ # Run specific directory
```

#### **End-to-End Testing**
```bash
# Playwright
npx playwright test                # Run E2E tests
npx playwright test --headed       # Run with browser UI
npx playwright test --debug        # Debug mode
npx playwright test --grep="login" # Run specific tests

# Cypress
npx cypress run                    # Run headless tests
npx cypress open                   # Open Cypress UI
npx cypress run --spec="cypress/e2e/login.cy.js" # Run specific test
```

### **Performance & Optimization**

#### **Laravel Optimization**
```bash
# Cache Management
php artisan optimize:clear         # Clear all caches
php artisan cache:clear            # Clear application cache
php artisan config:clear           # Clear config cache
php artisan route:clear            # Clear route cache
php artisan view:clear             # Clear view cache

# Cache Generation
php artisan optimize               # Generate all caches
php artisan config:cache          # Cache configuration
php artisan route:cache            # Cache routes
php artisan view:cache             # Cache views
php artisan event:cache            # Cache events

# Performance Monitoring
php artisan horizon:status         # Check queue status
php artisan queue:work             # Process queue jobs
php artisan schedule:run            # Run scheduled tasks
```

#### **Database Optimization**
```bash
# Database Commands
php artisan migrate:status         # Check migration status
php artisan migrate:rollback       # Rollback last migration
php artisan migrate:reset          # Reset all migrations
php artisan db:seed                # Run seeders
php artisan db:wipe                # Wipe database

# Database Analysis
php artisan db:show                # Show database info
php artisan db:table users         # Show table structure
php artisan tinker                  # Interactive shell
```

### **Security & Authentication**

#### **Security Commands**
```bash
# Authentication
php artisan auth:clear-resets      # Clear password reset tokens
php artisan passport:install        # Install Passport
php artisan passport:keys           # Generate Passport keys

# Security Analysis
php artisan route:list              # List all routes
php artisan middleware:list         # List all middleware
php artisan make:middleware AuthCheck # Create middleware

# CSRF & Security
php artisan make:controller Api/AuthController # Create API controller
php artisan make:request LoginRequest # Create form request
```

---

## 🔍 **ANALYSIS COMMANDS**

### **Route Analysis**
```bash
# Route Inspection
php artisan route:list              # List all routes
php artisan route:list --path=login # Focused view for login routes (always supported)
php artisan route:list --path=login --json # View middleware if --json works; else read routes/web.php
php artisan route:list --path=admin # Filter by path
php artisan route:list --method=GET # Filter by method
php artisan route:list --name=admin # Filter by name

# Route Caching
php artisan route:cache             # Cache routes
php artisan route:clear             # Clear route cache
php artisan route:list --cached    # Show cached routes
```

### **Code Analysis**
```bash
# Find Duplicates
command -v rg >/dev/null && rg "class.*Controller" app/Http/Controllers/ || grep -RniE "class.*Controller" app/Http/Controllers/ # Find controller classes
command -v rg >/dev/null && rg "function.*Data" resources/views/ || grep -RniE "function.*Data" resources/views/ # Find data functions
command -v rg >/dev/null && rg "Route::" routes/ || grep -RniE "Route::" routes/ # Find route definitions

# Search Patterns
command -v rg >/dev/null && rg "middleware.*auth" routes/ || grep -RniE "middleware.*auth" routes/ # Find auth middleware usage
command -v rg >/dev/null && rg "POST.*projects" routes/ || grep -RniE "POST.*projects" routes/ # Find POST project routes
command -v rg >/dev/null && rg "dashboard.*blade" resources/views/ || grep -RniE "dashboard.*blade" resources/views/ # Find dashboard views
```

### **File Analysis**
```bash
# File Statistics
find . -name "*.php" -type f | wc -l # Count PHP files
find . -name "*.blade.php" -type f | wc -l # Count Blade files
find . -name "*.js" -o -name "*.ts" | wc -l # Count JS/TS files

# Large Files
find . -name "*.php" -type f -exec wc -l {} + | sort -nr | head -10 # Largest PHP files
find . -name "*.blade.php" -type f -exec wc -l {} + | sort -nr | head -10 # Largest Blade files
```

---

## 🚀 **DEPLOYMENT COMMANDS**

### **Pre-Deployment**
```bash
# Code Quality Checks
./vendor/bin/pint --test           # Check code style
./vendor/bin/phpstan analyse      # Static analysis
php artisan test                   # Run tests
npx eslint . --fix               # Fix JS issues
npx tsc --noEmit                 # Type check

# Performance Checks
php artisan optimize:clear        # Clear caches
php artisan route:cache           # Cache routes
php artisan config:cache          # Cache config
php artisan view:cache            # Cache views
```

### **Deployment**
```bash
# Production Deployment
composer install --no-dev --optimize-autoloader # Install production deps
php artisan migrate --force       # Run migrations
php artisan db:seed --force       # Run seeders
php artisan optimize              # Optimize for production
php artisan horizon:terminate    # Restart Horizon
```

### **Post-Deployment**
```bash
# Health Checks
php artisan route:list            # Verify routes
php artisan config:show           # Check config
php artisan queue:work --once     # Test queue
curl -f http://localhost/health   # Health check
```

---

## 🔧 **REFACTORING COMMANDS**

### **Route Refactoring**
```bash
# Backup Routes
cp routes/web.php routes/web.php.backup
cp routes/api.php routes/api.php.backup

# Analyze Route Issues
php artisan route:list | grep -E "(POST|PUT|PATCH|DELETE)" # Find side-effect routes
php artisan route:list | grep -v "middleware" # Find routes without middleware
php artisan route:list --path=admin | grep -v "rbac" # Find admin routes without RBAC
```

### **Controller Refactoring**
```bash
# Find Duplicate Controllers
find app/Http/Controllers -name "*Controller.php" | sort
(command -v rg >/dev/null && rg "class.*Controller" app/Http/Controllers/ || grep -RniE "class.*Controller" app/Http/Controllers/) | sort

# Move Controllers
mkdir -p app/Http/Controllers/Web
mkdir -p app/Http/Controllers/Api
mv app/Http/Controllers/ProjectController.php app/Http/Controllers/Web/
```

### **View Refactoring**
```bash
# Find Duplicate Views
find resources/views -name "*dashboard*" -type f
find resources/views -name "*project*" -type f

# Analyze View Usage
command -v rg >/dev/null && rg "dashboard-content" resources/views/ || grep -RniE "dashboard-content" resources/views/ # Find dashboard references
command -v rg >/dev/null && rg "projects-enhanced" resources/views/ || grep -RniE "projects-enhanced" resources/views/ # Find project references
```

---

## 📊 **MONITORING COMMANDS**

### **Performance Monitoring**
```bash
# New Relic (if configured)
php artisan newrelic:deploy        # Record deployment
php artisan newrelic:notify        # Send notification

# Custom Monitoring
php artisan queue:monitor          # Monitor queue
php artisan horizon:status         # Check Horizon status
php artisan schedule:list          # List scheduled tasks
```

### **Error Monitoring**
```bash
# Log Analysis
tail -f storage/logs/laravel.log   # Follow logs
grep "ERROR" storage/logs/laravel.log # Find errors
grep "Exception" storage/logs/laravel.log # Find exceptions
```

---

## 🧪 **TESTING COMMANDS**

### **Unit Testing**
```bash
# Run Specific Tests
php artisan test --filter=UserTest # Run user tests
php artisan test --filter=ProjectTest # Run project tests
php artisan test tests/Unit/ # Run unit tests only
php artisan test tests/Feature/ # Run feature tests only
```

### **Integration Testing**
```bash
# API Testing
php artisan test --filter=ApiTest # Run API tests
php artisan test tests/Feature/Api/ # Run API feature tests
curl -X GET http://localhost/api/health # Test API endpoint
```

### **E2E Testing**
```bash
# Playwright E2E
npx playwright test --grep="login" # Test login flow
npx playwright test --grep="dashboard" # Test dashboard
npx playwright test --grep="projects" # Test projects
```

---

## 🔄 **MAINTENANCE COMMANDS**

### **Regular Maintenance**
```bash
# Daily
php artisan queue:work --once      # Process queue
php artisan schedule:run           # Run scheduled tasks
php artisan cache:clear            # Clear cache

# Weekly
php artisan optimize:clear        # Clear all caches
php artisan route:cache            # Cache routes
php artisan config:cache           # Cache config
php artisan view:cache             # Cache views

# Monthly
php artisan migrate:status         # Check migrations
php artisan db:seed                # Update seed data
php artisan horizon:terminate      # Restart Horizon
```

### **Cleanup Commands**
```bash
# Clean Old Files
find storage/logs -name "*.log" -mtime +30 -delete # Delete old logs
find storage/framework/cache -type f -mtime +7 -delete # Delete old cache
find storage/framework/sessions -type f -mtime +7 -delete # Delete old sessions
```

---

## 📝 **DOCUMENTATION COMMANDS**

### **API Documentation**
```bash
# Generate OpenAPI/Swagger
php artisan l5-swagger:generate    # Generate Swagger docs
php artisan api:docs               # Generate API docs
php artisan make:resource UserResource # Create API resource
```

### **Code Documentation**
```bash
# PHPDoc
./vendor/bin/phpdoc -d app/ -t docs/api/ # Generate PHPDoc
./vendor/bin/phpdoc -d app/Http/Controllers/ -t docs/controllers/ # Controller docs
```

---

**Status:** ✅ Commands Guide Complete  
**Usage:** Reference this guide during refactoring operations
