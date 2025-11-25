# Documents Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all documents-related test files for Documents Domain organization work package  
**Seed:** 45678 (fixed for reproducibility)

---

## Summary

**Total Documents Test Files:** 11  
**Files with @group documents:** 0  
**Files needing @group documents:** 11

---

## Test Files Inventory

### Feature Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/DocumentApiTest.php` | `DocumentApiTest` | ❌ **MISSING** | 6+ | Document API CRUD operations |
| `tests/Feature/Api/Documents/DocumentsContractTest.php` | `DocumentsContractTest` | ❌ **MISSING** | Multiple | Documents API contract tests |
| `tests/Feature/Api/DocumentManagementTest.php` | `DocumentManagementTest` | ❌ **MISSING** | Multiple | Document management API tests |
| `tests/Feature/DocumentVersioningTest.php` | `DocumentVersioningTest` | ❌ **MISSING** | Multiple | Document versioning tests |
| `tests/Feature/DocumentVersioningSimpleTest.php` | `DocumentVersioningSimpleTest` | ❌ **MISSING** | Multiple | Simple versioning tests |
| `tests/Feature/DocumentVersioningNoFKTest.php` | `DocumentVersioningNoFKTest` | ❌ **MISSING** | Multiple | Versioning tests without foreign keys |
| `tests/Feature/DocumentVersioningDebugTest.php` | `DocumentVersioningDebugTest` | ❌ **MISSING** | Multiple | Debug versioning tests |
| `tests/Feature/Unit/Policies/DocumentPolicyTest.php` | `DocumentPolicyTest` | ❌ **MISSING** | Multiple | Document policy tests (Feature namespace) |
| `tests/Feature/Unit/Policies/DocumentPolicySimpleTest.php` | `DocumentPolicySimpleTest` | ❌ **MISSING** | Multiple | Simple document policy tests |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/DocumentPolicyTest.php` | `DocumentPolicyTest` | ❌ **MISSING** | Multiple | Document policy unit tests |

### Browser Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Browser/DocumentManagementTest.php` | `DocumentManagementTest` | ❌ **MISSING** | Multiple | Browser/Dusk document management tests |

---

## Detailed File Analysis

### Feature Tests (9 files)

#### 1. `tests/Feature/DocumentApiTest.php`
- **Status:** ❌ Missing `@group documents`
- **Class:** `DocumentApiTest`
- **Test Methods:** 6+
  - `test_can_upload_document()`
  - `test_can_get_all_documents()`
  - `test_can_upload_new_version()`
  - `test_can_revert_to_previous_version()`
  - `test_can_download_document()`
  - `test_upload_document_validation_errors()`
- **Action Required:** Add `@group documents` annotation in PHPDoc block

#### 2-9. Other Feature Test Files
- All missing `@group documents` annotation
- Action Required: Add `@group documents` annotation to each file

### Unit Tests (1 file)

#### 1. `tests/Unit/DocumentPolicyTest.php`
- **Status:** ❌ Missing `@group documents`
- **Class:** `DocumentPolicyTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group documents` annotation in PHPDoc block

### Browser Tests (1 file)

#### 1. `tests/Browser/DocumentManagementTest.php`
- **Status:** ❌ Missing `@group documents`
- **Class:** `DocumentManagementTest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group documents` annotation in PHPDoc block

---

## Checklist for Future Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/DocumentApiTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/Api/Documents/DocumentsContractTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/Api/DocumentManagementTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/DocumentVersioningTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/DocumentVersioningSimpleTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/DocumentVersioningNoFKTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/DocumentVersioningDebugTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/Unit/Policies/DocumentPolicyTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Feature/Unit/Policies/DocumentPolicySimpleTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Unit/DocumentPolicyTest.php` - Add `@group documents` to PHPDoc
- [ ] `tests/Browser/DocumentManagementTest.php` - Add `@group documents` to PHPDoc

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group documents" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected output should show all 11 files.

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `documents-unit` - Unit tests with `@group documents`
- `documents-feature` - Feature tests with `@group documents`
- `documents-integration` - Integration tests with `@group documents`

### Browser Tests

Browser tests (Dusk) are not included in PHPUnit test suites by default. Consider:
- Adding to Playwright E2E tests (handled by Codex)
- Or creating separate Dusk test suite if needed

---

## Notes

1. **E2E Tests:** Documents E2E tests in `tests/e2e/documents/` (if exists) are handled separately.

2. **Test Methods Count:** Some test methods use `@test` annotations instead of `test_` prefix. Both should be included in the `@group documents` annotation.

3. **Namespace Conflicts:** There are multiple `DocumentPolicyTest` classes:
   - `Tests\Feature\Unit\Policies\DocumentPolicyTest`
   - `Tests\Feature\Unit\Policies\DocumentPolicySimpleTest`
   - `Tests\Unit\DocumentPolicyTest`
   - All should have `@group documents` annotation.

4. **Document Versioning:** Multiple versioning test files exist (DocumentVersioningTest, DocumentVersioningSimpleTest, DocumentVersioningNoFKTest, DocumentVersioningDebugTest). All should be included in the documents domain.

5. **File Uploads:** Document tests may involve file uploads. Ensure test environment supports file storage (local or mock).

---

## Next Steps

1. Future agent should add `@group documents` annotations to all 11 files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=documents --seed=45678
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=documents-feature
   php artisan test --testsuite=documents-unit
   php artisan test --testsuite=documents-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for future agent)
