# Dashboard Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all dashboard-related test files for Dashboard Domain organization work package  
**Seed:** 67890 (fixed for reproducibility)

---

## Summary

**Total Dashboard Test Files:** 13  
**Files with @group dashboard:** 0  
**Files needing @group dashboard:** 13

---

## Test Files Inventory

### Feature Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/Dashboard/DashboardApiTest.php` | `DashboardApiTest` | ❌ **MISSING** | 19+ | Dashboard API tests |
| `tests/Feature/Dashboard/AppDashboardApiTest.php` | `AppDashboardApiTest` | ❌ **MISSING** | Multiple | App dashboard API tests |
| `tests/Feature/AdminDashboardTest.php` | `AdminDashboardTest` | ❌ **MISSING** | Multiple | Admin dashboard tests |
| `tests/Feature/DashboardAnalyticsTest.php` | `DashboardAnalyticsTest` | ❌ **MISSING** | Multiple | Dashboard analytics tests |
| `tests/Feature/DashboardAnalyticsSimpleTest.php` | `DashboardAnalyticsSimpleTest` | ❌ **MISSING** | Multiple | Simple dashboard analytics tests |
| `tests/Feature/DashboardWithETagTest.php` | `DashboardWithETagTest` | ❌ **MISSING** | Multiple | Dashboard ETag caching tests |
| `tests/Feature/DashboardEnhancementTest.php` | `DashboardEnhancementTest` | ❌ **MISSING** | Multiple | Dashboard enhancement tests |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/Dashboard/DashboardServiceTest.php` | `DashboardServiceTest` | ❌ **MISSING** | Multiple | Dashboard service unit tests |
| `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php` | `DashboardRoleBasedServiceTest` | ❌ **MISSING** | Multiple | Dashboard role-based service tests |

### Browser Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Browser/DashboardTest.php` | `DashboardTest` | ❌ **MISSING** | Multiple | Browser/Dusk dashboard tests |
| `tests/Browser/DashboardSoftRefreshTest.php` | `DashboardSoftRefreshTest` | ❌ **MISSING** | Multiple | Dashboard soft refresh browser tests |

### E2E Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/e2e/DashboardE2ETest.php` | `DashboardE2ETest` | ❌ **MISSING** | Multiple | Dashboard E2E tests |

### Performance Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Performance/DashboardPerformanceTest.php` | `DashboardPerformanceTest` | ❌ **MISSING** | Multiple | Dashboard performance tests |

---

## Detailed File Analysis

### Feature Tests (7 files)

#### 1. `tests/Feature/Dashboard/DashboardApiTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardApiTest`
- **Test Methods:** 19+ (using `@test` annotations)
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

#### 2-7. Other Feature Test Files
- All missing `@group dashboard` annotation
- Action Required: Add `@group dashboard` annotation to each file

### Unit Tests (2 files)

#### 1. `tests/Unit/Dashboard/DashboardServiceTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardServiceTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

#### 2. `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardRoleBasedServiceTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

### Browser Tests (2 files)

#### 1. `tests/Browser/DashboardTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

#### 2. `tests/Browser/DashboardSoftRefreshTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardSoftRefreshTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

### E2E Tests (1 file)

#### 1. `tests/e2e/DashboardE2ETest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardE2ETest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block
- **Note:** E2E tests may be handled separately by Playwright

### Performance Tests (1 file)

#### 1. `tests/Performance/DashboardPerformanceTest.php`
- **Status:** ❌ Missing `@group dashboard`
- **Class:** `DashboardPerformanceTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group dashboard` annotation in PHPDoc block

---

## Checklist for Future Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/Dashboard/DashboardApiTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/Dashboard/AppDashboardApiTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/AdminDashboardTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/DashboardAnalyticsTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/DashboardAnalyticsSimpleTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/DashboardWithETagTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Feature/DashboardEnhancementTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Unit/Dashboard/DashboardServiceTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Browser/DashboardTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/Browser/DashboardSoftRefreshTest.php` - Add `@group dashboard` to PHPDoc
- [ ] `tests/e2e/DashboardE2ETest.php` - Add `@group dashboard` to PHPDoc (if applicable)
- [ ] `tests/Performance/DashboardPerformanceTest.php` - Add `@group dashboard` to PHPDoc

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group dashboard" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/ tests/e2e/ tests/Performance/
```

Expected output should show all 13 files.

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `dashboard-unit` - Unit tests with `@group dashboard`
- `dashboard-feature` - Feature tests with `@group dashboard`
- `dashboard-integration` - Integration tests with `@group dashboard`

### Browser Tests

Browser tests (Dusk) are not included in PHPUnit test suites by default. Consider:
- Adding to Playwright E2E tests (handled by Codex)
- Or creating separate Dusk test suite if needed

### Performance Tests

Performance tests may need special handling. Consider if they should be part of the dashboard domain or handled separately.

---

## Notes

1. **E2E Tests:** Dashboard E2E tests in `tests/e2e/` may be handled separately by Playwright.

2. **Test Methods Count:** Some test methods use `@test` annotations instead of `test_` prefix. Both should be included in the `@group dashboard` annotation.

3. **Dashboard Models:** Dashboard tests use multiple models:
   - `UserDashboard` - User-specific dashboard configurations
   - `DashboardWidget` - Available widgets in the system
   - `DashboardMetric` - Dashboard metrics
   - `DashboardMetricValue` - Metric values over time
   - `DashboardAlert` - Dashboard alerts

4. **Performance Tests:** Performance tests may need special configuration or handling.

5. **Caching:** Dashboard tests may involve caching (ETag tests). Ensure cache is properly configured in tests.

---

## Next Steps

1. Future agent should add `@group dashboard` annotations to all 13 files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=dashboard --seed=67890
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=dashboard-feature
   php artisan test --testsuite=dashboard-unit
   php artisan test --testsuite=dashboard-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for future agent)
