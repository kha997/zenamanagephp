# Deprecated Tests Review and Cleanup Plan

**Date:** 2025-11-08  
**Status:** In Progress

## Executive Summary

This document categorizes and provides recommendations for deprecated tests found in the test suite. The goal is to migrate, fix, or remove tests that are no longer relevant or need updates to match current architecture patterns.

## Categorization Criteria

1. **Cần migrate:** Tests need updates to use AuthHelper/TestDataSeeder/API endpoints
2. **Cần fix:** Tests have errors that need fixing
3. **Cần remove:** Tests are no longer needed
4. **Keep as-is:** Tests are legacy but still necessary

## Category 1: Cần Migrate (Need Migration)

### Tests Using Old Web Routes

**Pattern:** Tests using `/login` instead of `/api/v1/auth/login`

**Files:**
- `tests/Feature/LoggingIntegrationTest.php` - Uses `/login` route (lines 133, 151, 277)
- `tests/Feature/CsrfProtectionTest.php` - Uses `/login` route (line 132)
- `tests/Feature/AdminDashboardTest.php` - Expects route [login] (needs fix)

**Action Required:**
- Replace web route calls with API endpoints
- Update to use AuthHelper for authentication
- Verify routes exist or update expectations

### Tests Using `$this->actingAs()` Without AuthHelper

**Pattern:** Tests using `$this->actingAs()` instead of `AuthHelper::authenticateAs()`

**Files Found:** 522 instances across multiple files

**Key Files:**
- `tests/Feature/LoggingIntegrationTest.php` - Multiple instances
- `tests/Feature/FinalSystemTest.php` - Multiple instances (partially migrated)
- `tests/Feature/Integration/SecurityIntegrationTest.php` - Multiple instances
- `tests/Feature/Buttons/ButtonAuthenticationTest.php`
- `tests/Feature/Auth/PasswordChangeTest.php` - Uses `actingAs` with 'sanctum'
- `tests/Feature/Users/*` - Multiple user management tests
- `tests/Feature/Layout/AppLayoutHeaderTest.php`
- `tests/Feature/TenantIsolationTest.php`
- `tests/Feature/Performance/PerformanceFeatureTest.php`
- `tests/Feature/Web/ProjectControllerTest.php`
- `tests/Feature/QualityAssuranceTest.php`
- `tests/Feature/BillingTest.php`
- And many more...

**Action Required:**
- Replace `$this->actingAs($user)` with `AuthHelper::authenticateAs($this, $user)`
- For API tests, use `AuthHelper::getAuthToken()` and set Authorization header
- Update test setup to use TestDataSeeder

**Priority:** Medium (many tests, but migration is straightforward)

## Category 2: Cần Fix (Need Fixing)

### 2.1 Tests with Syntax Errors

#### ProjectApiTest.php
- **File:** `tests/Feature/Api/ProjectApiTest.php`
- **Status:** All tests skipped
- **Reason:** Syntax errors in test structure
- **Action:** Fix syntax errors or remove file
- **Priority:** High

#### NotificationApiTest.php
- **File:** `tests/Feature/Api/NotificationApiTest.php`
- **Status:** All tests skipped
- **Reason:** Using wrong Notification model (`Src\Notification` instead of `App\Models`)
- **Action:** Fix model namespace to `App\Models\Notification`
- **Priority:** High

### 2.2 Tests with Database/Model Issues

#### Tests Using Missing Models
- **Files:**
  - `tests/Feature/Api/IntegrationTest.php` - Missing ZenaProject and related models
  - `tests/Feature/Api/TaskDependenciesTest.php` - Missing ZenaProject and ZenaTask models
  - `tests/Feature/Api/TaskApiTest.php` - Missing ZenaProject and ZenaTask models
  - `tests/Feature/Api/SubmittalApiTest.php` - Missing ZenaProject and ZenaSubmittal models
  - `tests/Feature/Api/SecurityTest.php` - Missing ZenaProject model
  - `tests/Feature/Api/RfiApiTest.php` - Missing ZenaProject and ZenaRfi models
  - `tests/Feature/Api/DocumentManagementTest.php` - Missing ZenaProject and ZenaDocument models
  - `tests/Feature/Api/ChangeRequestApiTest.php` - Missing ZenaProject and ZenaChangeRequest models
  - `tests/Feature/Api/ComponentApiTest.php` - Missing Src\CoreProject\Models\Component class
  - `tests/Feature/Api/PerformanceTest.php` - Missing ZenaProject and related models
  - `tests/Feature/Api/TaskDependenciesTest.php` - Missing ZenaProject and ZenaTask models

- **Action:** Update to use correct models (`Src\CoreProject\Models\*` or `App\Models\*`)
- **Priority:** Medium

#### Tests with Database Schema Issues
- **Files:**
  - `tests/Unit/Dashboard/DashboardServiceTest.php` - Missing `code` field in projects table
  - `tests/Unit/Dashboard/DashboardRoleBasedServiceTest.php` - Missing `dashboard_metrics` table
  - `tests/Unit/Repositories/UserRepositoryTest.php` - Users table missing `deleted_at` column
  - `tests/Unit/Models/ModelsTest.php` - Document model has `file_type` field issue

- **Action:** Fix database schema or update tests
- **Priority:** Medium

### 2.3 Tests with Route/Endpoint Issues

#### Missing Routes
- **Files:**
  - `tests/Feature/AdminDashboardTest.php` - Route [login] not defined
  - `tests/Feature/FinalSystemTest.php` - Route `/api/dashboards` returns 404
  - `tests/Feature/ApiEndpointsTest.php` - Dashboard endpoints not implemented
  - `tests/Feature/Api/ProjectManagerApiIntegrationTest.php` - Endpoints not implemented
  - `tests/Feature/BillingTest.php` - Billing routes not implemented

- **Action:** Implement missing routes or update test expectations
- **Priority:** High

### 2.4 Tests with Configuration Issues

#### Missing Services/Controllers
- **Files:**
  - `tests/Feature/AIPoweredFeaturesTest.php` - Missing AIController class
  - `tests/Feature/AdvancedSecurityTest.php` - Missing AdvancedSecurityController class
  - `tests/Feature/Api/WebSocketTest.php` - WebSocket endpoints not implemented
  - `tests/Feature/Api/RealTimeNotificationsTest.php` - Using ZenaNotification model and non-existent endpoints

- **Action:** Implement missing services or remove tests
- **Priority:** Low (features may not be implemented yet)

#### Missing Infrastructure
- **Files:**
  - `tests/Feature/Api/CachingTest.php` - Redis not configured for testing
  - `tests/Integration/DashboardCacheIntegrationTest.php` - Caching infrastructure not implemented
  - `tests/Feature/BackgroundJobsTest.php` - Job dispatch not working properly

- **Action:** Configure infrastructure or skip tests appropriately
- **Priority:** Medium

## Category 3: Cần Remove (Need Removal)

### 3.1 Completely Skipped Test Files

These files have all tests skipped and may not be needed:

- `tests/Feature/Api/ProjectApiTest.php` - All skipped (syntax errors)
- `tests/Feature/Api/NotificationApiTest.php` - All skipped (wrong model)
- `tests/Feature/Api/IntegrationTest.php` - All skipped (missing models)
- `tests/Feature/Api/TaskDependenciesTest.php` - All skipped (missing models)
- `tests/Feature/Api/TaskApiTest.php` - All skipped (missing models)
- `tests/Feature/Api/WebSocketTest.php` - All skipped (not implemented)
- `tests/Feature/Api/SubmittalApiTest.php` - All skipped (missing models)
- `tests/Feature/Api/SecurityTest.php` - All skipped (missing models)
- `tests/Feature/Api/RfiApiTest.php` - All skipped (missing models)
- `tests/Feature/Api/RealTimeNotificationsTest.php` - All skipped (wrong model)
- `tests/Feature/Api/RateLimitingTest.php` - All skipped (not configured)
- `tests/Feature/Api/DocumentManagementTest.php` - All skipped (missing models)
- `tests/Feature/Api/ProjectManagerApiIntegrationTest.php` - All skipped (not implemented)
- `tests/Feature/Api/PerformanceTest.php` - All skipped (missing models)
- `tests/Feature/Api/NotificationApiTest.php` - All skipped (wrong model)
- `tests/Feature/Api/ComprehensiveApiIntegrationTest.php` - All skipped (needs setup)
- `tests/Feature/Api/CachingTest.php` - All skipped (Redis not configured)
- `tests/Feature/Api/ComponentApiTest.php` - All skipped (missing class)
- `tests/Feature/Api/ChangeRequestApiTest.php` - All skipped (missing models)
- `tests/Feature/ApiPerformanceTest.php` - All skipped (wrong models)
- `tests/Feature/ApiEndpointsTest.php` - All skipped (not implemented)
- `tests/Feature/Accessibility/AccessibilityTest.php` - All skipped (React-based, not suitable)

**Action:** Review each file and either:
1. Remove if feature is not planned
2. Fix if feature is planned
3. Keep if feature is planned but not yet implemented (with proper documentation)

**Priority:** Medium

### 3.2 Debug Tests

- `tests/Feature/AuthDebugTest.php` - Debug test with output (all skipped)
- **Action:** Remove debug tests from main test suite
- **Priority:** Low

## Category 4: Keep As-Is (Legacy but Necessary)

### Legacy Route Tests

- `tests/Feature/Routes/LegacyRedirectsTest.php` - Tests legacy redirects (still needed)
- `tests/Feature/Legacy/LegacyRouteRollbackTest.php` - Tests rollback procedures (still needed)

**Action:** Keep these tests as they verify legacy route functionality

### Cross-Browser Tests

- `tests/Feature/CrossBrowserTestSuite.php` - Cross-browser compatibility tests
- **Status:** Tests skipped (marked for manual execution)
- **Action:** Keep but document that they should be run manually
- **Priority:** Low

## Migration Checklist

### High Priority

- [ ] Fix syntax errors in `ProjectApiTest.php`
- [ ] Fix model namespace in `NotificationApiTest.php`
- [ ] Fix route [login] issue in `AdminDashboardTest.php`
- [ ] Fix missing `/api/dashboards` route in `FinalSystemTest.php`
- [ ] Fix test setup issues in `LoggingIntegrationTest.php` (unique constraint violations)

### Medium Priority

- [ ] Migrate tests using old web routes to API endpoints
- [ ] Migrate tests using `$this->actingAs()` to `AuthHelper`
- [ ] Update tests using wrong models to correct namespaces
- [ ] Fix database schema issues or update tests
- [ ] Review and fix/remove completely skipped test files

### Low Priority

- [ ] Migrate remaining tests to use `TestDataSeeder`
- [ ] Update tests to use `ApiResponseAssertions`
- [ ] Document skipped tests with proper reasons
- [ ] Remove debug tests

## Recommendations

### Immediate Actions

1. **Fix Critical Issues:**
   - Fix syntax errors in `ProjectApiTest.php` or remove file
   - Fix model namespace in `NotificationApiTest.php`
   - Fix route issues in `AdminDashboardTest.php` and `FinalSystemTest.php`

2. **Fix Test Setup:**
   - Fix unique constraint violations in `LoggingIntegrationTest.php`
   - Use `TestDataSeeder` with unique email generation

3. **Review Skipped Tests:**
   - Determine which skipped tests are still needed
   - Fix or remove tests that are no longer relevant

### Short-term Actions

1. **Migrate High-Value Tests:**
   - Migrate `LoggingIntegrationTest.php` to use AuthHelper properly
   - Migrate `FinalSystemTest.php` remaining tests
   - Migrate `AdminDashboardTest.php` to use API endpoints

2. **Clean Up Skipped Tests:**
   - Review all skipped test files
   - Remove tests for features not planned
   - Fix tests for features that are planned

### Long-term Actions

1. **Comprehensive Migration:**
   - Migrate all tests using `$this->actingAs()` to `AuthHelper`
   - Migrate all tests to use `TestDataSeeder`
   - Update all tests to use `ApiResponseAssertions`

2. **Test Infrastructure:**
   - Set up proper test database schema
   - Configure Redis for caching tests
   - Set up proper test data factories

## Summary Statistics

- **Total Deprecated Patterns Found:** ~148 skipped tests, 522 instances of `actingAs()`
- **High Priority Fixes:** 5 files
- **Medium Priority Migrations:** ~20 files
- **Low Priority Cleanups:** ~25 files
- **Keep As-Is:** 2 files

---

**Next Steps:**
1. Fix critical issues (syntax errors, route issues)
2. Migrate high-value tests
3. Review and clean up skipped tests
4. Document migration progress

