# 🔧 DevOps Hardening & Standardization

**Date:** January 15, 2025  
**Status:** Implementation Phase  
**Goal:** Production-ready DevOps pipeline with security and automation

## 🎯 **DevOps Objectives**

### **Core Goals**
- **Automated CI/CD**: Zero-downtime deployments
- **Security First**: Automated security scanning
- **Monitoring**: Comprehensive observability
- **Scalability**: Auto-scaling infrastructure
- **Compliance**: Audit-ready processes

## 🚀 **CI/CD Pipeline Enhancement**

### **1. GitHub Actions Workflow**
```yaml
# .github/workflows/ci-cd.yml
name: CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

env:
  APP_ENV: testing
  DB_CONNECTION: mysql
  DB_DATABASE: zenamanage_test

jobs:
  # Code Quality & Security
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, dom, fileinfo, mysql
          
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader
        
      - name: Run PHP CS Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
        
      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse
        
      - name: Security Check
        run: ./vendor/bin/security-checker security:check

  # Testing
  test:
    runs-on: ubuntu-latest
    needs: quality
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: zenamanage_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, dom, fileinfo, mysql
          
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader
        
      - name: Setup environment
        run: |
          cp .env.example .env
          php artisan key:generate
          php artisan config:cache
          
      - name: Run migrations
        run: php artisan migrate --force
        
      - name: Run tests
        run: php artisan test --coverage
        
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml

  # Build & Deploy
  deploy:
    runs-on: ubuntu-latest
    needs: [quality, test]
    if: github.ref == 'refs/heads/main'
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
          cache: 'npm'
          
      - name: Install dependencies
        run: npm ci
        
      - name: Build assets
        run: npm run build
        
      - name: Deploy to production
        run: |
          # Add deployment script here
          echo "Deploying to production..."
```

### **2. Security Scanning Pipeline**
```yaml
# .github/workflows/security.yml
name: Security Scan

on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM
  push:
    branches: [ main ]

jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scan-ref: '.'
          format: 'sarif'
          output: 'trivy-results.sarif'
          
      - name: Upload Trivy scan results
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: 'trivy-results.sarif'
          
      - name: Run OWASP ZAP
        uses: zaproxy/action-full-scan@v0.4.0
        with:
          target: 'http://localhost:8000'
          rules_file_name: '.zap/rules.tsv'
          cmd_options: '-a'
```

## 🔒 **Security Hardening**

### **1. Security Headers Middleware**
```php
// app/Http/Middleware/SecurityHeadersMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' data:; " .
               "connect-src 'self' wss: https:; " .
               "media-src 'self'; " .
               "object-src 'none'; " .
               "frame-ancestors 'none'; " .
               "form-action 'self'; " .
               "base-uri 'self';";
               
        $response->headers->set('Content-Security-Policy', $csp);
        
        return $response;
    }
}
```

### **2. Rate Limiting Enhancement**
```php
// app/Http/Middleware/AdvancedRateLimitMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class AdvancedRateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);
        
        // Different limits for different endpoints
        $limits = [
            'api/auth/*' => 5,      // 5 requests per minute for auth
            'api/*' => 60,          // 60 requests per minute for API
            'web/*' => 120,         // 120 requests per minute for web
        ];
        
        $limit = $this->getLimitForRoute($request, $limits);
        
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()->getName(),
                'attempts' => RateLimiter::attempts($key)
            ]);
            
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 1 minute decay
        
        return $next($request);
    }
    
    private function getLimitForRoute(Request $request, array $limits)
    {
        $route = $request->route()->getName();
        
        foreach ($limits as $pattern => $limit) {
            if (fnmatch($pattern, $route)) {
                return $limit;
            }
        }
        
        return 60; // Default limit
    }
}
```

## 📊 **Monitoring & Observability**

### **1. Application Performance Monitoring**
```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    // Database query monitoring
    DB::listen(function ($query) {
        if ($query->time > 100) {
            Log::warning('Slow query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time
            ]);
        }
    });
    
    // Memory usage monitoring
    if (memory_get_usage(true) > 128 * 1024 * 1024) { // 128MB
        Log::warning('High memory usage detected', [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    }
}
```

### **2. Health Check Endpoint**
```php
// app/Http/Controllers/Api/HealthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'memory' => $this->checkMemory(),
        ];
        
        $overall = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $overall ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()
        ], $overall ? 200 : 503);
    }
    
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkCache()
    {
        try {
            Cache::put('health_check', 'ok', 1);
            $value = Cache::get('health_check');
            return ['status' => 'ok', 'message' => 'Cache working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkRedis()
    {
        try {
            Redis::ping();
            return ['status' => 'ok', 'message' => 'Redis connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkStorage()
    {
        try {
            $path = storage_path('app/health_check.txt');
            file_put_contents($path, 'ok');
            $content = file_get_contents($path);
            unlink($path);
            return ['status' => 'ok', 'message' => 'Storage writable'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkMemory()
    {
        $usage = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        $percentage = ($usage / $this->parseMemoryLimit($limit)) * 100;
        
        if ($percentage > 90) {
            return ['status' => 'warning', 'message' => 'High memory usage: ' . round($percentage, 2) . '%'];
        }
        
        return ['status' => 'ok', 'message' => 'Memory usage: ' . round($percentage, 2) . '%'];
    }
}
```

## 🚀 **Deployment Automation**

### **1. Production Deployment Script**
```bash
#!/bin/bash
# deploy-production.sh

set -e

echo "🚀 Starting Production Deployment"
echo "================================="

# Configuration
APP_NAME="ZenaManage"
APP_ENV="production"
BACKUP_DIR="/backups/$(date +%Y%m%d_%H%M%S)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Pre-deployment checks
print_status "Running pre-deployment checks..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run tests
print_status "Running test suite..."
php artisan test --env=testing

# Create backup
print_status "Creating backup..."
mkdir -p $BACKUP_DIR
php artisan backup:run --destination=local --destinationPath=$BACKUP_DIR

# Deploy application
print_status "Deploying application..."
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci --production
npm run build

# Run migrations
print_status "Running migrations..."
php artisan migrate --force

# Clear caches
print_status "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# Set permissions
print_status "Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Health check
print_status "Running health check..."
sleep 5
curl -f http://localhost:8000/api/health || {
    print_error "Health check failed!"
    exit 1
}

print_success "🎉 Deployment completed successfully!"
```

### **2. Rollback Script**
```bash
#!/bin/bash
# rollback.sh

set -e

echo "🔄 Starting Rollback"
echo "==================="

BACKUP_DIR=$1

if [ -z "$BACKUP_DIR" ]; then
    echo "Usage: $0 <backup_directory>"
    exit 1
fi

if [ ! -d "$BACKUP_DIR" ]; then
    echo "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

print_status() {
    echo -e "\033[1;33m[INFO]\033[0m $1"
}

print_success() {
    echo -e "\033[0;32m[SUCCESS]\033[0m $1"
}

# Restore database
print_status "Restoring database..."
php artisan backup:restore --source=local --sourcePath=$BACKUP_DIR

# Restore files
print_status "Restoring files..."
git reset --hard HEAD~1

# Clear caches
print_status "Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Health check
print_status "Running health check..."
sleep 5
curl -f http://localhost:8000/api/health || {
    echo "Health check failed!"
    exit 1
}

print_success "🎉 Rollback completed successfully!"
```

## 📋 **Implementation Timeline**

### **Week 1: CI/CD Pipeline**
- [ ] Set up GitHub Actions workflows
- [ ] Implement automated testing
- [ ] Configure security scanning
- [ ] Set up deployment automation

### **Week 2: Security Hardening**
- [ ] Implement security headers
- [ ] Enhanced rate limiting
- [ ] Security scanning integration
- [ ] Audit logging

### **Week 3: Monitoring & Observability**
- [ ] Application performance monitoring
- [ ] Health check endpoints
- [ ] Log aggregation
- [ ] Alerting system

### **Week 4: Production Readiness**
- [ ] Production deployment scripts
- [ ] Rollback procedures
- [ ] Documentation
- [ ] Team training

## 📊 **Success Metrics**

### **Deployment Metrics**
- **Deployment Time**: < 5 minutes
- **Downtime**: < 30 seconds
- **Success Rate**: > 99%
- **Rollback Time**: < 2 minutes

### **Security Metrics**
- **Vulnerability Scan**: Daily
- **Security Incidents**: 0
- **Compliance**: 100%
- **Audit Score**: A+

### **Performance Metrics**
- **Response Time**: < 200ms
- **Uptime**: > 99.9%
- **Error Rate**: < 0.1%
- **Resource Usage**: < 70%

---

**Next Action:** Implement CI/CD pipeline and security hardening
