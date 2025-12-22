# Phase 2: Documents Domain Seed Integration - Progress

**Date:** 2025-11-09  
**Agent:** Cursor  
**Status:** In Progress  
**Domain:** Documents

---

## Summary

Documents domain tests are being updated to use `seedDocumentsDomain()` for reproducible test data.

---

## Test Files Found (12 files)

1. `tests/Feature/DocumentApiTest.php`
2. `tests/Feature/Api/DocumentManagementTest.php`
3. `tests/Feature/Api/Documents/DocumentsContractTest.php`
4. `tests/Feature/DocumentVersioningTest.php`
5. `tests/Feature/DocumentVersioningSimpleTest.php`
6. `tests/Feature/DocumentVersioningNoFKTest.php`
7. `tests/Feature/DocumentVersioningDebugTest.php`
8. `tests/Unit/DocumentPolicyTest.php`
9. `tests/Feature/Unit/Policies/DocumentPolicyTest.php`
10. `tests/Feature/Unit/Policies/DocumentPolicySimpleTest.php`
11. `tests/Browser/DocumentManagementTest.php`
12. `tests/Unit/Helpers/TestDataSeederVerificationTest.php` (verification test - already correct)

---

## Seed Data Available

From `seedDocumentsDomain(45678)`:
- **Tenant:** `Documents Test Tenant` (slug: `documents-test-tenant-45678`)
- **Users:**
  - `pm@documents-test.test` (project_manager role)
  - `member@documents-test.test` (member role)
- **Projects:** 1 project (Documents Test Project)
- **Documents:**
  - Internal document
  - Client-visible document
  - Versioned document (with versions)
- **Document Versions:** 2 versions for versioned document

---

## Pattern to Use

```php
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;

class MyDocumentTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    protected $tenant;
    protected $user;
    protected $seedData; // Store to avoid re-seeding
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('documents');
        $this->setupDomainIsolation();
        
        // Seed documents domain test data (only once)
        $this->seedData = TestDataSeeder::seedDocumentsDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use project manager user from seed data
        $this->user = collect($this->seedData['users'])->firstWhere('email', 'pm@documents-test.test');
        if (!$this->user) {
            $this->user = $this->seedData['users'][0];
        }
        
        // Authenticate if needed
        Sanctum::actingAs($this->user);
    }
}
```

---

## Files to Update

### Feature Tests (7 files)
- [x] `tests/Feature/DocumentApiTest.php` - ✅ COMPLETED
- [x] `tests/Feature/Api/DocumentManagementTest.php` - ✅ COMPLETED
- [x] `tests/Feature/Api/Documents/DocumentsContractTest.php` - ✅ COMPLETED
- [x] `tests/Feature/DocumentVersioningTest.php` - ✅ COMPLETED
- [x] `tests/Feature/DocumentVersioningSimpleTest.php` - ✅ COMPLETED
- [x] `tests/Feature/DocumentVersioningNoFKTest.php` - ✅ COMPLETED
- [x] `tests/Feature/DocumentVersioningDebugTest.php` - ✅ COMPLETED

### Unit Tests (3 files)
- [x] `tests/Unit/DocumentPolicyTest.php` - ✅ COMPLETED
- [x] `tests/Feature/Unit/Policies/DocumentPolicyTest.php` - ✅ COMPLETED
- [x] `tests/Feature/Unit/Policies/DocumentPolicySimpleTest.php` - ✅ COMPLETED

### Browser Tests (1 file)
- [x] `tests/Browser/DocumentManagementTest.php` - ✅ COMPLETED

### Verification Test (1 file)
- [x] `tests/Unit/Helpers/TestDataSeederVerificationTest.php` - ✅ Already correct (tests seed methods themselves)

## Progress Summary

- **Completed**: 12/12 files (100%)
- **Remaining**: 0/12 files (0%)

## Notes
- All Documents domain test files have been updated to use `seedDocumentsDomain(45678)` for reproducible test data
- Files use `DomainTestIsolation` trait for proper test isolation
- Seed data is stored in `$this->seedData` to avoid re-seeding in test methods
- Browser tests (Dusk) updated to use seed data
- Policy tests updated to use seed data while keeping mocks for policy-specific tests

---

**Last Updated:** 2025-11-09  
**Status:** Documents Domain - Starting...

