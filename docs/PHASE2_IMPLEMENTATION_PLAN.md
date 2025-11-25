# Phase 2: Seed Method Integration - Implementation Plan

**Date:** 2025-11-09  
**Agent:** Cursor  
**Status:** In Progress  
**Purpose:** Migrate all tests to use domain seed methods for reproducible test data

---

## Overview

Phase 2 focuses on updating all test files to use domain seed methods (`seedAuthDomain()`, `seedProjectsDomain()`, etc.) instead of manually creating test data or using factories directly. This ensures:
- Reproducible test data (fixed seeds)
- Consistent test setup across all tests
- Better test isolation
- Easier maintenance

---

## Strategy

### Pattern to Follow

1. **Add DomainTestIsolation trait** to test class
2. **Set domain seed and name** as class properties
3. **Call setupDomainIsolation()** in setUp()
4. **Use seed method** to create test data
5. **Store test data** using `storeTestData()` for cleanup

### Example Pattern

```php
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;

class MyAuthTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    protected int $domainSeed = 12345;
    protected string $domainName = 'auth';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        
        // Seed domain data
        $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
        $this->tenant = $data['tenant'];
        $this->user = $data['users'][0]; // or find by role/email
        $this->storeTestData('tenant', $this->tenant);
        
        // Authenticate if needed
        Sanctum::actingAs($this->user);
    }
}
```

---

## Implementation Order

### 1. Auth Domain (Priority: HIGH)
**Files to Update:**
- ✅ `tests/Feature/Auth/PasswordChangeTest.php` - **DONE** (Template)
- [ ] `tests/Feature/Auth/AuthenticationTest.php`
- [ ] `tests/Feature/Auth/AuthenticationModuleTest.php`
- [ ] `tests/Feature/Auth/EmailVerificationTest.php`
- [ ] `tests/Unit/AuthServiceTest.php`
- [ ] `tests/Integration/SecurityIntegrationTest.php`

**Seed Method:** `TestDataSeeder::seedAuthDomain(12345)`

**Estimated Time:** 1-2 hours

### 2. Projects Domain (Priority: HIGH)
**Files to Update:**
- [ ] All test files with `@group projects`

**Seed Method:** `TestDataSeeder::seedProjectsDomain(23456)`

**Estimated Time:** 1-2 hours

### 3. Tasks Domain (Priority: HIGH)
**Files to Update:**
- [ ] All test files with `@group tasks`

**Seed Method:** `TestDataSeeder::seedTasksDomain(34567)`

**Estimated Time:** 1-2 hours

### 4. Documents Domain (Priority: MEDIUM)
**Files to Update:**
- [ ] All test files with `@group documents`

**Seed Method:** `TestDataSeeder::seedDocumentsDomain(45678)`

**Estimated Time:** 1 hour

### 5. Users Domain (Priority: MEDIUM)
**Files to Update:**
- [ ] All test files with `@group users`

**Seed Method:** `TestDataSeeder::seedUsersDomain(56789)`

**Estimated Time:** 1 hour

### 6. Dashboard Domain (Priority: MEDIUM)
**Files to Update:**
- [ ] All test files with `@group dashboard`

**Seed Method:** `TestDataSeeder::seedDashboardDomain(67890)`

**Estimated Time:** 1 hour

---

## Migration Steps for Each Test File

1. **Add imports:**
   ```php
   use Tests\Traits\DomainTestIsolation;
   use Tests\Helpers\TestDataSeeder;
   ```

2. **Add trait to class:**
   ```php
   use DomainTestIsolation;
   ```

3. **Add domain properties:**
   ```php
   protected int $domainSeed = 12345; // Use appropriate seed for domain
   protected string $domainName = 'auth'; // Use appropriate domain name
   ```

4. **Update setUp() method:**
   - Call `$this->setupDomainIsolation();`
   - Replace manual data creation with seed method
   - Use seed data instead of factories/manual creation
   - Store test data using `$this->storeTestData()`

5. **Update test methods if needed:**
   - Reference seed data instead of manually created data
   - Use seed data IDs/relationships

6. **Remove unused imports:**
   - Remove factory imports if no longer needed
   - Remove manual model creation code

---

## Verification

After updating each domain:

1. **Run domain test suite:**
   ```bash
   php artisan test --group=auth
   php artisan test --group=projects
   # etc.
   ```

2. **Check for:**
   - ✅ Tests pass
   - ✅ No duplicate data errors
   - ✅ Test isolation works (tests don't interfere)
   - ✅ Seed data is used correctly

3. **Document progress:**
   - Update this file with completion status
   - Note any issues found

---

## Progress Tracking

### Auth Domain
- ⚠️ PasswordChangeTest - **TEMPLATE CREATED** (Blocked by test environment - Phase 6 needed)
  - Template pattern is correct
  - Test fails due to missing `tenant_id` column in `zena_roles` table (SQLite)
  - Seed method is correct, issue is test environment setup
- [ ] AuthenticationTest
- [ ] AuthenticationModuleTest
- [ ] EmailVerificationTest
- [ ] AuthServiceTest
- [ ] SecurityIntegrationTest

**Note:** Phase 2 implementation is blocked by Phase 6 (Test Environment Setup). Template pattern is correct and ready to use once test environment is fixed.

### Projects Domain
- [ ] Not started

### Tasks Domain
- [ ] Not started

### Documents Domain
- [ ] Not started

### Users Domain
- [ ] Not started

### Dashboard Domain
- [ ] Not started

---

## Known Issues

### Test Environment Issues (Blocking Phase 2)

**Issue:** SQLite test environment doesn't have all migrations applied
- `zena_roles` table missing `tenant_id` column
- This is a Phase 6 (Test Environment Setup) issue
- Seed methods are correct, but test environment needs migrations

**Impact:** Some tests will fail when using seed methods until Phase 6 is complete

**Workaround Options:**
1. Complete Phase 6 first (recommended)
2. Skip `tenant_id` in seed methods for SQLite (not recommended - breaks schema consistency)
3. Use MySQL for tests instead of SQLite

### Other Issues

- Some tests may need custom data beyond seed methods
- Some tests may need specific user roles/emails
- Integration tests may need multiple domains

**Solution:** Use seed methods as base, then add custom data if needed.

---

## Next Steps

1. ✅ Create implementation plan - **DONE**
2. ✅ Update PasswordChangeTest as template - **DONE**
3. ⏳ Update remaining Auth domain tests
4. ⏳ Update Projects domain tests
5. ⏳ Continue with other domains

---

**Last Updated:** 2025-11-09  
**Next Update:** After completing Auth domain

