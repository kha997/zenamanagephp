# Tenants Management System - Comprehensive Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [API Documentation](#api-documentation)
4. [Frontend Implementation](#frontend-implementation)
5. [Database Schema](#database-schema)
6. [Security & Authentication](#security--authentication)
7. [Performance Optimization](#performance-optimization)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

## Overview

The Tenants Management System is a comprehensive solution for managing multi-tenant applications. It provides a complete admin interface for viewing, filtering, searching, and managing tenants with real-time KPI monitoring, bulk operations, and detailed tenant information.

### Key Features
- **Real-time KPI Dashboard**: Total tenants, active/suspended counts, new registrations, trial expiring
- **Advanced Filtering**: Search by name/domain, filter by status/plan/region, date ranges
- **Bulk Operations**: Suspend, resume, change plan, delete multiple tenants
- **Individual Actions**: View, edit, suspend, resume, impersonate, delete
- **Export Functionality**: CSV export with current filters or selected tenants
- **Responsive Design**: Mobile-first approach with adaptive layouts
- **Performance Optimized**: Caching, lazy loading, optimized queries
- **Comprehensive Testing**: E2E, performance, and integration tests

## Architecture

### System Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend API   │    │   Database      │
│                 │    │                 │    │                 │
│ • Alpine.js     │◄──►│ • Laravel       │◄──►│ • MySQL         │
│ • Tailwind CSS  │    │ • Sanctum Auth  │    │ • Indexes       │
│ • Chart.js      │    │ • Rate Limiting │    │ • Constraints   │
│ • Performance   │    │ • Caching       │    │ • Audit Logs    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Technology Stack
- **Backend**: Laravel 10, PHP 8.1+
- **Frontend**: Alpine.js, Tailwind CSS, Chart.js
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Sanctum
- **Caching**: Redis (optional)
- **Testing**: PHPUnit, Laravel Dusk

## API Documentation

### Base URL
```
/api/admin/tenants
```

### Authentication
All endpoints require authentication via Laravel Sanctum:
```http
Authorization: Bearer {token}
```

### Endpoints

#### 1. List Tenants
```http
GET /api/admin/tenants
```

**Query Parameters:**
- `q` (string): Search query
- `status` (string): Filter by status (active, suspended, trial, archived)
- `plan` (string): Filter by plan (free, pro, enterprise)
- `region` (string): Filter by region
- `from` (date): Start date filter
- `to` (date): End date filter
- `range` (string): Date range (7d, 30d, 90d)
- `sort` (string): Sort field and direction (e.g., `-created_at`, `name:asc`)
- `page` (integer): Page number
- `per_page` (integer): Items per page

**Response:**
```json
{
  "data": [
    {
      "id": "tenant-id",
      "name": "Tenant Name",
      "domain": "tenant.com",
      "code": "tenant-code",
      "status": "active",
      "settings": {
        "plan": "pro",
        "owner_email": "owner@tenant.com"
      },
      "region": "us-east-1",
      "users_count": 25,
      "projects_count": 5,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "total": 100,
    "per_page": 25,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 25
  }
}
```

#### 2. Get Tenant Details
```http
GET /api/admin/tenants/{id}
```

**Response:**
```json
{
  "data": {
    "id": "tenant-id",
    "name": "Tenant Name",
    "domain": "tenant.com",
    "code": "tenant-code",
    "status": "active",
    "settings": {
      "plan": "pro",
      "owner_email": "owner@tenant.com"
    },
    "region": "us-east-1",
    "users_count": 25,
    "projects_count": 5,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```

#### 3. Create Tenant
```http
POST /api/admin/tenants
```

**Request Body:**
```json
{
  "name": "New Tenant",
  "domain": "newtenant.com",
  "code": "new-tenant",
  "plan": "pro",
  "region": "us-east-1",
  "owner_email": "owner@newtenant.com"
}
```

#### 4. Update Tenant
```http
PUT /api/admin/tenants/{id}
```

**Request Body:**
```json
{
  "name": "Updated Tenant",
  "domain": "updated.com",
  "plan": "enterprise"
}
```

#### 5. Delete Tenant
```http
DELETE /api/admin/tenants/{id}
```

#### 6. Suspend Tenant
```http
POST /api/admin/tenants/{id}/suspend
```

**Request Body:**
```json
{
  "reason": "Manual suspension"
}
```

#### 7. Resume Tenant
```http
POST /api/admin/tenants/{id}/resume
```

**Request Body:**
```json
{
  "reason": "Manual resume"
}
```

#### 8. Impersonate Tenant
```http
POST /api/admin/tenants/{id}/impersonate
```

**Response:**
```json
{
  "message": "Impersonation started successfully",
  "impersonation_url": "/tenant/impersonate/{token}",
  "tenant": {...},
  "expires_at": "2024-01-01T01:00:00Z"
}
```

#### 9. Bulk Operations
```http
POST /api/admin/tenants/bulk/suspend
POST /api/admin/tenants/bulk/resume
POST /api/admin/tenants/bulk/change-plan
POST /api/admin/tenants/bulk/delete
POST /api/admin/tenants/bulk/export
```

**Request Body:**
```json
{
  "tenant_ids": ["id1", "id2", "id3"],
  "reason": "Bulk operation reason",
  "plan": "pro" // for change-plan only
}
```

#### 10. Export Tenants
```http
GET /api/admin/tenants/export.csv
```

**Query Parameters:** Same as list tenants

#### 11. KPI Data
```http
GET /api/admin/tenants-kpis
```

**Query Parameters:**
- `period` (string): Time period (7d, 30d, 90d)

**Response:**
```json
{
  "data": {
    "kpis": {
      "total": 100,
      "active": 85,
      "suspended": 10,
      "trial": 5,
      "new30d": 15,
      "trialExpiring": 3
    },
    "deltas": {
      "total": 12.5,
      "active": 8.2,
      "suspended": -5.1,
      "new30d": 25.0,
      "trialExpiring": 0
    },
    "sparklines": {
      "total": {
        "labels": ["Jan 1", "Jan 2", ...],
        "data": [95, 97, 100, ...]
      }
    }
  }
}
```

### Error Responses

#### 400 Bad Request
```json
{
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "domain": ["The domain field is required."]
  }
}
```

#### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

#### 403 Forbidden
```json
{
  "message": "Only super admins can impersonate tenants"
}
```

#### 404 Not Found
```json
{
  "message": "No query results for model [App\\Models\\Tenant] {id}"
}
```

#### 422 Unprocessable Entity
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": ["Status must be one of: trial, active, suspended, archived"]
  }
}
```

#### 429 Too Many Requests
```json
{
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

## Frontend Implementation

### File Structure
```
public/js/tenants/
├── page.js          # Main page logic
├── detail.js        # Tenant detail page
└── performance.js   # Performance optimizations

resources/views/admin/tenants/
├── index.blade.php  # Main tenants page
└── show.blade.php   # Tenant detail page

public/css/
└── tenants-enhanced.css  # Enhanced styles
```

### Key Components

#### 1. TenantsPage Class
Main class handling all tenant operations:

```javascript
class TenantsPage {
    constructor() {
        this.state = {
            list: [],
            kpis: {},
            meta: {...},
            loading: false,
            error: null,
            selectedRows: new Set(),
            filters: {...}
        };
        
        this.cache = new Map();
        this.performance = window.TenantsPerformance;
    }
    
    // Core methods
    async loadTenants() { ... }
    async loadKPIs() { ... }
    updateTable() { ... }
    updateKPIs() { ... }
    
    // Actions
    async bulkSuspend() { ... }
    async bulkResume() { ... }
    async suspendTenant(id) { ... }
    async resumeTenant(id) { ... }
    async impersonateTenant(id) { ... }
    async deleteTenant(id) { ... }
    
    // Form handling
    async createTenant(data) { ... }
    async updateTenant(id, data) { ... }
    validateTenantForm(data) { ... }
    
    // UI updates
    updateUI() { ... }
    showToast(message, type) { ... }
    setLoadingState(loading) { ... }
}
```

#### 2. Performance Optimization
```javascript
class TenantsPerformance {
    constructor() {
        this.debounceTimers = new Map();
        this.intersectionObserver = null;
        this.performanceMetrics = {...};
    }
    
    // Performance methods
    optimizeTableRendering(tenants) { ... }
    debounce(key, func, delay) { ... }
    throttle(func, delay) { ... }
    measurePageLoad() { ... }
    reportPerformanceMetrics() { ... }
}
```

#### 3. Form Validation
```javascript
validateTenantForm(data) {
    const errors = {};
    
    // Required fields
    if (!data.name || data.name.trim().length === 0) {
        errors.name = 'Tenant name is required';
    }
    
    // Domain validation
    if (!this.isValidDomain(data.domain.trim())) {
        errors.domain = 'Please enter a valid domain';
    }
    
    // Plan validation
    if (!['free', 'pro', 'enterprise'].includes(data.plan)) {
        errors.plan = 'Please select a valid plan';
    }
    
    return {
        isValid: Object.keys(errors).length === 0,
        errors
    };
}
```

### State Management

#### State Structure
```javascript
{
    list: [],           // Array of tenant objects
    kpis: {},          // KPI data with deltas and sparklines
    meta: {            // Pagination metadata
        total: 0,
        page: 1,
        per_page: 25,
        last_page: 1
    },
    loading: false,    // Loading state
    error: null,       // Error message
    selectedRows: new Set(), // Selected tenant IDs
    filters: {         // Current filters
        q: '',
        status: '',
        plan: '',
        from: '',
        to: '',
        range: '',
        region: '',
        sort: '-created_at'
    }
}
```

#### Cache Management
```javascript
// Cache with TTL
setCache(key, data, etag) {
    this.cache.set(key, {
        data,
        etag,
        timestamp: Date.now()
    });
}

getCache(key) {
    const cached = this.cache.get(key);
    if (!cached) return null;
    
    // Check TTL
    if (Date.now() - cached.timestamp > this.cacheTTL) {
        this.cache.delete(key);
        return null;
    }
    
    return cached;
}
```

### Event Handling

#### Search with Debouncing
```javascript
initSearch() {
    const searchInput = document.querySelector('#search-input');
    searchInput.addEventListener('input', (e) => {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.filters.q = e.target.value;
            this.state.meta.page = 1;
            this.loadTenants();
            this.updateUrl();
        }, 300);
    });
}
```

#### Filter Chips
```javascript
initFilterChips() {
    const filterChips = document.querySelectorAll('.filter-chip');
    filterChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const filterType = chip.dataset.filterType;
            const filterValue = chip.dataset.filterValue;
            const isActive = chip.classList.contains('active');
            
            // Toggle active state
            chip.classList.toggle('active');
            chip.setAttribute('aria-pressed', !isActive);
            
            // Apply filter
            this.filters[filterType] = isActive ? '' : filterValue;
            this.state.meta.page = 1;
            this.loadTenants();
            this.updateUrl();
        });
    });
}
```

#### Bulk Actions
```javascript
initBulkActions() {
    // Select all checkbox
    const selectAllCheckbox = document.querySelector('#select-all-checkbox');
    selectAllCheckbox.addEventListener('change', (e) => {
        const isChecked = e.target.checked;
        const checkboxes = document.querySelectorAll('.tenant-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
            if (isChecked) {
                this.state.selectedRows.add(checkbox.value);
            } else {
                this.state.selectedRows.delete(checkbox.value);
            }
        });
        
        this.updateBulkActionsBar();
    });
    
    // Individual checkboxes
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('tenant-checkbox')) {
            if (e.target.checked) {
                this.state.selectedRows.add(e.target.value);
            } else {
                this.state.selectedRows.delete(e.target.value);
            }
            this.updateBulkActionsBar();
        }
    });
}
```

## Database Schema

### Tenants Table
```sql
CREATE TABLE tenants (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    code VARCHAR(100) UNIQUE,
    slug VARCHAR(100) UNIQUE,
    status ENUM('trial', 'active', 'suspended', 'archived') DEFAULT 'trial',
    settings JSON,
    region VARCHAR(100),
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### Indexes
```sql
-- Performance indexes
CREATE INDEX idx_tenants_status ON tenants(status);
CREATE INDEX idx_tenants_created_at ON tenants(created_at);
CREATE INDEX idx_tenants_updated_at ON tenants(updated_at);
CREATE INDEX idx_tenants_region ON tenants(region);
CREATE INDEX idx_tenants_trial_ends_at ON tenants(trial_ends_at);

-- Composite indexes for common queries
CREATE INDEX idx_tenants_status_created ON tenants(status, created_at);
CREATE INDEX idx_tenants_region_status ON tenants(region, status);

-- Unique constraints
CREATE UNIQUE INDEX idx_tenants_domain_unique ON tenants(domain);
CREATE UNIQUE INDEX idx_tenants_code_unique ON tenants(code);
CREATE UNIQUE INDEX idx_tenants_slug_unique ON tenants(slug);
```

### Related Tables
```sql
-- Users table (tenant relationship)
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Projects table (tenant relationship)
CREATE TABLE projects (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

## Security & Authentication

### Authentication Flow
1. **Admin Login**: User logs in with admin credentials
2. **Token Generation**: Laravel Sanctum generates API token
3. **Token Storage**: Token stored in localStorage or meta tag
4. **API Requests**: Token sent in Authorization header
5. **Token Validation**: Backend validates token and user permissions

### Authorization Levels
- **Super Admin**: Full access, can impersonate tenants
- **Admin**: Can manage tenants, cannot impersonate
- **User**: No access to tenant management

### Security Measures
- **Rate Limiting**: 10 requests/minute for exports
- **CSRF Protection**: Web routes protected with CSRF tokens
- **Input Validation**: All inputs validated and sanitized
- **SQL Injection Prevention**: Using Eloquent ORM
- **XSS Protection**: Output escaped in Blade templates
- **Audit Logging**: All actions logged with user context

### Middleware Chain
```php
Route::middleware(['auth:sanctum', 'admin.only', 'tenant.isolation'])
    ->prefix('admin/tenants')
    ->group(function () {
        // Routes
    });
```

## Performance Optimization

### Backend Optimizations

#### 1. Database Query Optimization
```php
// Optimized withCount queries
$query->withCount([
    'users' => function ($query) {
        $query->where('status', 'active');
    },
    'projects' => function ($query) {
        $query->where('status', 'active');
    }
]);
```

#### 2. Caching Strategy
```php
// ETag caching
$etag = '"' . substr(hash('xxh3', json_encode([$col, $dir, $validated, $paginator->total()])), 0, 16) . '"';
if ($request->header('If-None-Match') === $etag) {
    return response()->noContent(304)->header('ETag', $etag);
}

// Response caching
return response()->json($payload)
    ->header('ETag', $etag)
    ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=30')
    ->header('X-Response-Time', round((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms');
```

#### 3. KPI Caching
```php
$cacheKey = "tenants_kpis_{$period}";
$kpiData = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($period) {
    // Expensive KPI calculations
});
```

### Frontend Optimizations

#### 1. Lazy Loading
```javascript
// Intersection Observer for lazy loading
this.intersectionObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const action = entry.target.dataset.lazyAction;
            if (action && typeof window.Tenants[action] === 'function') {
                window.Tenants[action]();
            }
        }
    });
}, { rootMargin: '50px' });
```

#### 2. Debounced Search
```javascript
// Debounce search input
debounce(key, func, delay = 300) {
    if (this.debounceTimers.has(key)) {
        clearTimeout(this.debounceTimers.get(key));
    }
    
    const timer = setTimeout(() => {
        func();
        this.debounceTimers.delete(key);
    }, delay);
    
    this.debounceTimers.set(key, timer);
}
```

#### 3. Optimized Table Rendering
```javascript
// Batch DOM updates with document fragments
optimizeTableRendering(tenants) {
    const fragment = document.createDocumentFragment();
    const tableBody = document.querySelector('.tenants-table tbody');
    
    // Create rows in batches
    const batchSize = 20;
    const batches = this.chunkArray(tenants, batchSize);
    
    let currentBatch = 0;
    const renderBatch = () => {
        if (currentBatch >= batches.length) {
            tableBody.appendChild(fragment);
            return;
        }
        
        const batch = batches[currentBatch];
        batch.forEach(tenant => {
            const row = this.createTenantRow(tenant);
            fragment.appendChild(row);
        });
        
        currentBatch++;
        requestAnimationFrame(renderBatch);
    };
    
    renderBatch();
}
```

#### 4. Performance Monitoring
```javascript
// Monitor API call performance
const originalFetch = window.fetch;
window.fetch = async (...args) => {
    const startTime = performance.now();
    const response = await originalFetch(...args);
    const endTime = performance.now();
    
    this.performanceMetrics.apiCalls.push({
        url: args[0],
        duration: endTime - startTime,
        timestamp: Date.now()
    });
    
    return response;
};
```

### Performance Targets
- **Page Load**: < 2 seconds
- **API Response**: < 500ms for list, < 300ms for KPIs
- **Table Rendering**: < 100ms for 100 rows
- **Search Response**: < 300ms with debouncing
- **Bulk Operations**: < 1 second for 10 tenants

## Testing

### Test Structure
```
tests/
├── Feature/
│   ├── TenantsApiTest.php           # API endpoint tests
│   ├── TenantIsolationTest.php      # Multi-tenant isolation
│   └── TenantsPerformanceTest.php   # Performance tests
├── Browser/
│   ├── TenantsE2ETest.php          # End-to-end tests
│   └── TenantsSoftRefreshTest.php  # Soft refresh tests
└── Unit/
    └── TenantModelTest.php         # Model tests
```

### Test Categories

#### 1. Feature Tests
```php
class TenantsApiTest extends TestCase
{
    public function test_tenants_index_returns_paginated_data()
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $tenants = Tenant::factory()->count(5)->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/tenants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'domain', 'status']
                ],
                'meta' => ['total', 'per_page', 'current_page']
            ]);
    }
}
```

#### 2. Browser Tests
```php
class TenantsE2ETest extends DuskTestCase
{
    public function test_complete_tenant_management_workflow()
    {
        $this->browse(function (Browser $browser) {
            // Login
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login');
            
            // Navigate to tenants
            $browser->visit('/admin/tenants')
                    ->assertSee('Tenants')
                    ->assertSee('Create Tenant');
            
            // Test search
            $browser->type('#search-input', 'test')
                    ->pause(500)
                    ->assertSee('test');
            
            // Test bulk actions
            $browser->check('#select-all-checkbox')
                    ->assertSee('bulk-actions-bar');
        });
    }
}
```

#### 3. Performance Tests
```php
class TenantsPerformanceTest extends TestCase
{
    public function test_api_response_times()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.5, $responseTime, 'API should respond within 500ms');
    }
}
```

### Test Data
```php
// Tenant Factory
class TenantFactory extends Factory
{
    protected $model = Tenant::class;
    
    public function definition()
    {
        return [
            'id' => $this->faker->uuid(),
            'name' => $this->faker->company(),
            'domain' => $this->faker->domainName(),
            'code' => $this->faker->slug(),
            'status' => $this->faker->randomElement(['trial', 'active', 'suspended']),
            'settings' => [
                'plan' => $this->faker->randomElement(['free', 'pro', 'enterprise']),
                'owner_email' => $this->faker->email()
            ],
            'region' => $this->faker->randomElement(['us-east-1', 'us-west-2', 'eu-west-1']),
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days')
        ];
    }
}
```

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Browser

# Run with coverage
php artisan test --coverage

# Run performance tests
php artisan test tests/Feature/TenantsPerformanceTest.php
```

## Deployment

### Environment Configuration
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zenamanage
DB_USERNAME=root
DB_PASSWORD=

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

# Rate Limiting
RATE_LIMIT_EXPORT_PER_MIN=10
```

### Production Optimizations
```php
// config/app.php
'debug' => false,
'url' => env('APP_URL', 'https://your-domain.com'),

// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

// config/database.php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_TIMEOUT => 30,
        ],
    ],
],
```

### Deployment Checklist
- [ ] Database migrations run
- [ ] Indexes created
- [ ] Cache cleared
- [ ] Config cached
- [ ] Routes cached
- [ ] Views cached
- [ ] Queue workers running
- [ ] Cron jobs configured
- [ ] SSL certificates installed
- [ ] Rate limiting configured
- [ ] Monitoring setup
- [ ] Backup strategy implemented

### Monitoring
```php
// Performance monitoring
Log::info('Tenant API Performance', [
    'endpoint' => $request->path(),
    'response_time' => $responseTime,
    'memory_usage' => memory_get_usage(true),
    'user_id' => auth()->id(),
    'ip' => $request->ip()
]);

// Error tracking
Log::error('Tenant API Error', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'user_id' => auth()->id(),
    'request' => $request->all()
]);
```

## Troubleshooting

### Common Issues

#### 1. API Authentication Errors
**Problem**: 401 Unauthorized errors
**Solution**: 
- Check token validity
- Verify Sanctum configuration
- Ensure user has admin permissions

#### 2. Performance Issues
**Problem**: Slow page loads
**Solution**:
- Check database indexes
- Enable query caching
- Optimize API responses
- Use Redis for caching

#### 3. Form Validation Errors
**Problem**: Forms not submitting
**Solution**:
- Check validation rules
- Verify CSRF tokens
- Check JavaScript errors
- Validate input data

#### 4. Bulk Operations Failing
**Problem**: Bulk actions not working
**Solution**:
- Check rate limiting
- Verify permissions
- Check database constraints
- Monitor error logs

### Debug Mode
```php
// Enable debug mode
APP_DEBUG=true
LOG_LEVEL=debug

// Check logs
tail -f storage/logs/laravel.log

// Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Performance Debugging
```javascript
// Enable performance monitoring
window.TenantsPerformance.reportPerformanceMetrics();

// Check API response times
console.log('API Calls:', window.TenantsPerformance.performanceMetrics.apiCalls);

// Monitor memory usage
console.log('Memory Usage:', performance.memory);
```

### Database Debugging
```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Analyze query performance
EXPLAIN SELECT * FROM tenants WHERE status = 'active';

-- Check index usage
SHOW INDEX FROM tenants;

-- Monitor table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'zenamanage'
ORDER BY (data_length + index_length) DESC;
```

### Browser Debugging
```javascript
// Check for JavaScript errors
window.addEventListener('error', (e) => {
    console.error('JavaScript Error:', e.error);
});

// Monitor network requests
const originalFetch = window.fetch;
window.fetch = (...args) => {
    console.log('API Request:', args[0]);
    return originalFetch(...args).then(response => {
        console.log('API Response:', response.status, response.url);
        return response;
    });
};

// Check localStorage
console.log('LocalStorage:', localStorage);
console.log('SessionStorage:', sessionStorage);
```

## Conclusion

The Tenants Management System provides a comprehensive solution for managing multi-tenant applications with:

- **Complete CRUD Operations**: Create, read, update, delete tenants
- **Advanced Filtering**: Search, filter, and sort capabilities
- **Bulk Operations**: Efficient management of multiple tenants
- **Real-time KPIs**: Live dashboard with sparklines and deltas
- **Performance Optimized**: Caching, lazy loading, and optimized queries
- **Responsive Design**: Mobile-first approach with adaptive layouts
- **Comprehensive Testing**: E2E, performance, and integration tests
- **Security Focused**: Authentication, authorization, and audit logging

The system is designed to scale with your application and provide a smooth user experience for administrators managing tenant accounts.
