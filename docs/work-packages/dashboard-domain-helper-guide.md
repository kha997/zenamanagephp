# Dashboard Domain Helper Guide

**For:** Future Agent (Builder)  
**Purpose:** Comprehensive implementation guide for Dashboard Domain test organization  
**Reference:** `docs/work-packages/dashboard-domain.md` (main work package)  
**Audit:** `docs/work-packages/dashboard-domain-audit.md` (file inventory)

---

## Overview

This guide will help you implement the Dashboard Domain test organization work package. The goal is to:

1. Add `@group dashboard` annotations to all dashboard-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedDashboardDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `67890` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `dashboard-unit`, `dashboard-feature`, `dashboard-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/dashboard-domain-audit.md` for file inventory

---

## File Inventory

### Files to Add @group Annotations (13 files)

**Feature Tests (7 files):**
1. `tests/Feature/Dashboard/DashboardApiTest.php`
2. `tests/Feature/Dashboard/AppDashboardApiTest.php`
3. `tests/Feature/AdminDashboardTest.php`
4. `tests/Feature/DashboardAnalyticsTest.php`
5. `tests/Feature/DashboardAnalyticsSimpleTest.php`
6. `tests/Feature/DashboardWithETagTest.php`
7. `tests/Feature/DashboardEnhancementTest.php`

**Unit Tests (2 files):**
1. `tests/Unit/Dashboard/DashboardServiceTest.php`
2. `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`

**Browser Tests (2 files):**
1. `tests/Browser/DashboardTest.php`
2. `tests/Browser/DashboardSoftRefreshTest.php`

**E2E Tests (1 file):**
1. `tests/e2e/DashboardE2ETest.php`

**Performance Tests (1 file):**
1. `tests/Performance/DashboardPerformanceTest.php`

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group dashboard` annotation to all dashboard test files.

#### Example: Adding Annotation

**Before:**
```php
<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;

/**
 * @group dashboard
 * Dashboard API Test
 */
class DashboardApiTest extends TestCase
{
    // ...
}
```

#### Verification

After adding annotations, verify:
```bash
grep -r "@group dashboard" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/ tests/e2e/ tests/Performance/
```

Expected: All 13 files should appear.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Verification Commands

```bash
php artisan test --testsuite=dashboard-unit
php artisan test --testsuite=dashboard-feature
php artisan test --testsuite=dashboard-integration
php artisan test --group=dashboard --seed=67890
```

---

### Phase 3: Implement seedDashboardDomain Method

**Goal:** Add `seedDashboardDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed dashboard domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete dashboard domain test setup including:
 * - Tenant
 * - Users (with different roles)
 * - Projects (for dashboard data)
 * - Dashboard widgets (available widgets)
 * - User dashboards (user-specific dashboard configurations)
 * - Dashboard metrics (KPI metrics)
 * - Dashboard metric values (metric data over time)
 * - Dashboard alerts
 * 
 * @param int $seed Fixed seed value (default: 67890)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     projects: \App\Models\Project[],
 *     dashboard_widgets: \App\Models\DashboardWidget[],
 *     user_dashboards: \App\Models\UserDashboard[],
 *     dashboard_metrics: \App\Models\DashboardMetric[],
 *     dashboard_metric_values: \App\Models\DashboardMetricValue[],
 *     dashboard_alerts: \App\Models\DashboardAlert[]
 * }
 */
public static function seedDashboardDomain(int $seed = 67890): array
```

#### Implementation Template

```php
public static function seedDashboardDomain(int $seed = 67890): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Dashboard Test Tenant',
        'slug' => 'dashboard-test-tenant',
        'status' => 'active',
    ]);
    
    // Create users
    $users = [];
    $users['admin'] = self::createUserWithRole('admin', $tenant, [
        'name' => 'Dashboard Admin User',
        'email' => 'admin@dashboard-test.test',
        'password' => 'password',
    ]);
    
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Dashboard PM User',
        'email' => 'pm@dashboard-test.test',
        'password' => 'password',
    ]);
    
    $users['member'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Dashboard Member User',
        'email' => 'member@dashboard-test.test',
        'password' => 'password',
    ]);
    
    // Create a project for dashboard data
    $project = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'DASH-PROJ-001',
        'name' => 'Dashboard Test Project',
        'description' => 'Project for dashboard domain testing',
        'status' => 'active',
        'owner_id' => $users['project_manager']->id,
        'budget_total' => 100000.00,
        'start_date' => now(),
        'end_date' => now()->addMonths(6),
    ]);
    
    // Create dashboard widgets (available widgets in system)
    $dashboardWidgets = [];
    
    $dashboardWidgets['project_overview'] = \App\Models\DashboardWidget::create([
        'name' => 'Project Overview',
        'type' => 'card',
        'category' => 'overview',
        'config' => ['show_progress' => true, 'show_budget' => true],
        'data_source' => ['type' => 'project', 'endpoint' => '/api/projects'],
        'is_active' => true,
        'description' => 'Overview of project status',
    ]);
    
    $dashboardWidgets['budget_chart'] = \App\Models\DashboardWidget::create([
        'name' => 'Budget Chart',
        'type' => 'chart',
        'category' => 'budget',
        'config' => ['chart_type' => 'line', 'period' => 'monthly'],
        'data_source' => ['type' => 'budget', 'endpoint' => '/api/budget'],
        'is_active' => true,
        'description' => 'Budget tracking chart',
    ]);
    
    $dashboardWidgets['task_metrics'] = \App\Models\DashboardWidget::create([
        'name' => 'Task Metrics',
        'type' => 'metric',
        'category' => 'progress',
        'config' => ['show_completion' => true, 'show_overdue' => true],
        'data_source' => ['type' => 'tasks', 'endpoint' => '/api/tasks'],
        'is_active' => true,
        'description' => 'Task completion metrics',
    ]);
    
    // Create user dashboards
    $userDashboards = [];
    
    // Admin dashboard
    $userDashboards['admin'] = \App\Models\UserDashboard::create([
        'user_id' => $users['admin']->id,
        'tenant_id' => $tenant->id,
        'name' => 'Admin Dashboard',
        'layout_config' => [
            'columns' => 3,
            'rows' => 2,
        ],
        'widgets' => [
            ['widget_id' => $dashboardWidgets['project_overview']->id, 'position' => [0, 0], 'size' => [2, 1]],
            ['widget_id' => $dashboardWidgets['budget_chart']->id, 'position' => [2, 0], 'size' => [1, 2]],
            ['widget_id' => $dashboardWidgets['task_metrics']->id, 'position' => [0, 1], 'size' => [2, 1]],
        ],
        'preferences' => [
            'theme' => 'dark',
            'refresh_interval' => 60,
        ],
        'is_default' => true,
        'is_active' => true,
    ]);
    
    // Project manager dashboard
    $userDashboards['pm'] = \App\Models\UserDashboard::create([
        'user_id' => $users['project_manager']->id,
        'tenant_id' => $tenant->id,
        'name' => 'PM Dashboard',
        'layout_config' => [
            'columns' => 2,
            'rows' => 2,
        ],
        'widgets' => [
            ['widget_id' => $dashboardWidgets['project_overview']->id, 'position' => [0, 0], 'size' => [1, 1]],
            ['widget_id' => $dashboardWidgets['task_metrics']->id, 'position' => [1, 0], 'size' => [1, 1]],
        ],
        'preferences' => [
            'theme' => 'light',
            'refresh_interval' => 30,
        ],
        'is_default' => true,
        'is_active' => true,
    ]);
    
    // Create dashboard metrics
    $dashboardMetrics = [];
    
    $dashboardMetrics['project_progress'] = \App\Models\DashboardMetric::create([
        'metric_code' => 'project.progress',
        'category' => 'project',
        'name' => 'Project Progress',
        'unit' => 'percent',
        'config' => ['min' => 0, 'max' => 100],
        'is_active' => true,
        'description' => 'Overall project completion percentage',
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
    ]);
    
    $dashboardMetrics['budget_utilization'] = \App\Models\DashboardMetric::create([
        'metric_code' => 'budget.utilization',
        'category' => 'budget',
        'name' => 'Budget Utilization',
        'unit' => 'percent',
        'config' => ['min' => 0, 'max' => 100],
        'is_active' => true,
        'description' => 'Budget utilization percentage',
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
    ]);
    
    // Create dashboard metric values
    $dashboardMetricValues = [];
    
    // Metric values for project progress
    for ($i = 0; $i < 5; $i++) {
        $dashboardMetricValues[] = \App\Models\DashboardMetricValue::create([
            'metric_id' => $dashboardMetrics['project_progress']->id,
            'value' => 10 + ($i * 5), // 10, 15, 20, 25, 30
            'recorded_at' => now()->subDays(5 - $i),
        ]);
    }
    
    // Metric values for budget utilization
    for ($i = 0; $i < 5; $i++) {
        $dashboardMetricValues[] = \App\Models\DashboardMetricValue::create([
            'metric_id' => $dashboardMetrics['budget_utilization']->id,
            'value' => 20 + ($i * 3), // 20, 23, 26, 29, 32
            'recorded_at' => now()->subDays(5 - $i),
        ]);
    }
    
    // Create dashboard alerts
    $dashboardAlerts = [];
    
    $dashboardAlerts[] = \App\Models\DashboardAlert::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'type' => 'warning',
        'title' => 'Budget Alert',
        'message' => 'Budget utilization is above 80%',
        'severity' => 'medium',
        'is_active' => true,
    ]);
    
    $dashboardAlerts[] = \App\Models\DashboardAlert::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'type' => 'info',
        'title' => 'Project Update',
        'message' => 'Project milestone reached',
        'severity' => 'low',
        'is_active' => true,
    ]);
    
    return [
        'tenant' => $tenant,
        'users' => array_values($users),
        'projects' => [$project],
        'dashboard_widgets' => array_values($dashboardWidgets),
        'user_dashboards' => array_values($userDashboards),
        'dashboard_metrics' => array_values($dashboardMetrics),
        'dashboard_metric_values' => $dashboardMetricValues,
        'dashboard_alerts' => $dashboardAlerts,
    ];
}
```

#### Key Points

- Use `mt_srand($seed)` at the start for reproducibility
- Create dashboard widgets with different types: card, chart, metric, table, alert
- Create user dashboards with different layouts and widget configurations
- Create dashboard metrics for different categories: project, budget, quality, safety
- Create dashboard metric values over time for trend analysis
- Create dashboard alerts with different types and severities
- Return structured array with all created entities

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/dashboard/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 67890,
  "domain": "dashboard",
  "widget_types": ["chart", "table", "card", "metric", "alert"],
  "widget_categories": ["overview", "progress", "analytics", "alerts", "quality", "budget", "safety"],
  "metric_categories": ["project", "budget", "quality", "safety", "schedule", "resource", "performance"],
  "dashboard_widgets": [
    {
      "name": "Project Overview",
      "type": "card",
      "category": "overview"
    },
    {
      "name": "Budget Chart",
      "type": "chart",
      "category": "budget"
    },
    {
      "name": "Task Metrics",
      "type": "metric",
      "category": "progress"
    }
  ],
  "user_dashboards": [
    {
      "name": "Admin Dashboard",
      "is_default": true
    },
    {
      "name": "PM Dashboard",
      "is_default": true
    }
  ]
}
```

---

### Phase 5: Playwright Projects (Optional)

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package.

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running dashboard tests.

#### Scripts to Add

```json
{
  "scripts": {
    "test:dashboard": "php artisan test --group=dashboard",
    "test:dashboard:unit": "php artisan test --testsuite=dashboard-unit",
    "test:dashboard:feature": "php artisan test --testsuite=dashboard-feature",
    "test:dashboard:integration": "php artisan test --testsuite=dashboard-integration",
    "test:dashboard:e2e": "playwright test --project=dashboard-e2e-chromium"
  }
}
```

---

## Common Pitfalls

### 1. Dashboard Widget Types

**Problem:** Using invalid widget types.

**Solution:** Use valid types: `chart`, `table`, `card`, `metric`, `alert`

### 2. User Dashboard Configuration

**Problem:** User dashboard layout or widget configuration not properly formatted.

**Solution:** Ensure layout_config and widgets are stored as JSON arrays:
```php
'layout_config' => [
    'columns' => 3,
    'rows' => 2,
],
'widgets' => [
    ['widget_id' => $widgetId, 'position' => [0, 0], 'size' => [2, 1]],
],
```

### 3. Dashboard Metrics

**Problem:** Dashboard metrics not properly linked to projects or tenants.

**Solution:** Always set `tenant_id` and optionally `project_id`:
```php
DashboardMetric::create([
    'tenant_id' => $tenant->id,
    'project_id' => $project->id, // Optional
    'metric_code' => 'project.progress',
    // ...
]);
```

### 4. Dashboard Metric Values

**Problem:** Metric values not properly linked to metrics.

**Solution:** Ensure `metric_id` is set and `recorded_at` is provided:
```php
DashboardMetricValue::create([
    'metric_id' => $metric->id,
    'value' => 50.0,
    'recorded_at' => now(),
]);
```

### 5. Caching

**Problem:** Dashboard tests may involve caching (ETag tests).

**Solution:** Ensure cache is properly configured in tests:
```php
Cache::flush(); // Clear cache before tests
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group dashboard" tests/Feature/ tests/Unit/ ...`
2. **Run test suites:** `php artisan test --testsuite=dashboard-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedDashboardDomain:** `php artisan test --group=dashboard --seed=67890`

---

## Completion Checklist

- [ ] All 13 files have `@group dashboard` annotation
- [ ] Test suites run successfully
- [ ] `seedDashboardDomain()` method exists and works correctly
- [ ] Fixtures file created
- [ ] NPM scripts added (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `67890`

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/dashboard-domain.md`
- **File Audit:** `docs/work-packages/dashboard-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Future Agent (Builder)
