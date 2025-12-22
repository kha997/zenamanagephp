# Dashboard Domain Quick Start Guide

**For:** Future Agent (Builder)  
**Purpose:** One-page quick reference for Dashboard Domain test organization  
**Full Guide:** See `docs/work-packages/dashboard-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all dashboard tests with fixed seed
php artisan test --group=dashboard --seed=67890

# Run by test suite
php artisan test --testsuite=dashboard-feature
php artisan test --testsuite=dashboard-unit
php artisan test --testsuite=dashboard-integration

# Verify annotations
grep -r "@group dashboard" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/ tests/e2e/ tests/Performance/
```

---

## File Checklist

### Add @group Annotations (13 files)
- [ ] All 7 Feature test files (see audit)
- [ ] All 2 Unit test files (see audit)
- [ ] All 2 Browser test files (see audit)
- [ ] All 1 E2E test file (see audit)
- [ ] All 1 Performance test file (see audit)

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedDashboardDomain()` method
- [ ] `tests/fixtures/domains/dashboard/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `67890` for dashboard domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **Widget Types:** chart, table, card, metric, alert
- **Widget Categories:** overview, progress, analytics, alerts, quality, budget, safety
- **Required Fields:** UserDashboards need `user_id`, `tenant_id`, `name`
- **Dashboard Metrics:** Need `tenant_id`, `metric_code`, `category`
- **Metric Values:** Need `metric_id`, `value`, `recorded_at`
- **Caching:** Dashboard tests may involve caching - ensure cache is configured

---

## Quick Example

### Using seedDashboardDomain Method

```php
$data = TestDataSeeder::seedDashboardDomain(67890);
$tenant = $data['tenant'];
$users = $data['users'];
$projects = $data['projects'];
$dashboard_widgets = $data['dashboard_widgets'];
$user_dashboards = $data['user_dashboards'];
$dashboard_metrics = $data['dashboard_metrics'];
$dashboard_metric_values = $data['dashboard_metric_values'];
$dashboard_alerts = $data['dashboard_alerts'];
```

---

## Resources

- **Main Work Package:** `docs/work-packages/dashboard-domain.md`
- **Helper Guide:** `docs/work-packages/dashboard-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/dashboard-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08
