# Users Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all users-related test files for Users Domain organization work package  
**Seed:** 56789 (fixed for reproducibility)

---

## Summary

**Total Users Test Files:** 10  
**Files with @group users:** 0  
**Files needing @group users:** 10

---

## Test Files Inventory

### Feature Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/Users/ProfileManagementTest.php` | `ProfileManagementTest` | ❌ **MISSING** | 9+ | User profile management tests |
| `tests/Feature/Users/AccountManagementTest.php` | `AccountManagementTest` | ❌ **MISSING** | Multiple | User account management tests |
| `tests/Feature/Users/AvatarManagementTest.php` | `AvatarManagementTest` | ❌ **MISSING** | Multiple | User avatar management tests |
| `tests/Feature/UserManagementSimpleTest.php` | `UserManagementSimpleTest` | ❌ **MISSING** | Multiple | Simple user management tests |
| `tests/Feature/UserManagementAuthenticationTest.php` | `UserManagementAuthenticationTest` | ❌ **MISSING** | Multiple | User management with authentication tests |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/Models/UserTest.php` | `UserTest` | ❌ **MISSING** | 20+ | User model unit tests |
| `tests/Unit/Repositories/UserRepositoryTest.php` | `UserRepositoryTest` | ❌ **MISSING** | Multiple | User repository unit tests |
| `tests/Unit/Policies/UserPolicyTest.php` | `UserPolicyTest` | ❌ **MISSING** | Multiple | User policy unit tests |

### E2E Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/e2e/CriticalUserFlowsE2ETest.php` | `CriticalUserFlowsE2ETest` | ❌ **MISSING** | Multiple | Critical user flows E2E tests |

### Traits

| File | Class Name | @group Status | Notes |
|------|-----------|---------------|-------|
| `tests/Traits/AuthenticatesUsers.php` | `AuthenticatesUsers` | N/A | Trait for user authentication (not a test class) |

---

## Detailed File Analysis

### Feature Tests (5 files)

#### 1. `tests/Feature/Users/ProfileManagementTest.php`
- **Status:** ❌ Missing `@group users`
- **Class:** `ProfileManagementTest`
- **Test Methods:** 9+
  - `test_user_can_get_their_profile()`
  - `test_get_profile_requires_authentication()`
  - `test_user_can_update_their_profile()`
  - `test_user_can_update_profile_with_partial_data()`
  - `test_update_profile_requires_authentication()`
  - `test_update_profile_validation()`
  - `test_user_can_update_profile_with_patch_method()`
  - `test_profile_respects_tenant_isolation()`
  - `test_update_profile_ignores_empty_strings()`
- **Action Required:** Add `@group users` annotation in PHPDoc block

#### 2-5. Other Feature Test Files
- All missing `@group users` annotation
- Action Required: Add `@group users` annotation to each file

### Unit Tests (3 files)

#### 1. `tests/Unit/Models/UserTest.php`
- **Status:** ❌ Missing `@group users`
- **Class:** `UserTest`
- **Test Methods:** 20+ (using `@test` annotations)
- **Action Required:** Add `@group users` annotation in PHPDoc block

#### 2-3. Other Unit Test Files
- All missing `@group users` annotation
- Action Required: Add `@group users` annotation to each file

### E2E Tests (1 file)

#### 1. `tests/e2e/CriticalUserFlowsE2ETest.php`
- **Status:** ❌ Missing `@group users`
- **Class:** `CriticalUserFlowsE2ETest`
- **Test Methods:** Multiple
- **Action Required:** Add `@group users` annotation in PHPDoc block
- **Note:** E2E tests may be handled separately by Playwright

---

## Checklist for Future Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/Users/ProfileManagementTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Feature/Users/AccountManagementTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Feature/Users/AvatarManagementTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Feature/UserManagementSimpleTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Feature/UserManagementAuthenticationTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Unit/Models/UserTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Unit/Repositories/UserRepositoryTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/Unit/Policies/UserPolicyTest.php` - Add `@group users` to PHPDoc
- [ ] `tests/e2e/CriticalUserFlowsE2ETest.php` - Add `@group users` to PHPDoc (if applicable)

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group users" tests/Feature/ tests/Unit/ tests/Integration/ tests/E2E/
```

Expected output should show all 9 test files (excluding traits).

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `users-unit` - Unit tests with `@group users`
- `users-feature` - Feature tests with `@group users`
- `users-integration` - Integration tests with `@group users`

### E2E Tests

E2E tests may be handled separately by Playwright (handled by Codex).

---

## Notes

1. **E2E Tests:** Users E2E tests in `tests/e2e/` may be handled separately by Playwright.

2. **Test Methods Count:** Some test methods use `@test` annotations instead of `test_` prefix. Both should be included in the `@group users` annotation.

3. **User Management Tests:** Multiple user management test files exist. All should be included in the users domain.

4. **Profile Management:** Profile management is a key part of the users domain - ensure all profile-related tests are included.

5. **Avatar Management:** Avatar uploads may require file storage configuration in tests.

6. **Traits:** `tests/Traits/AuthenticatesUsers.php` is a trait, not a test class, so it doesn't need `@group users` annotation.

---

## Next Steps

1. Future agent should add `@group users` annotations to all 9 test files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=users --seed=56789
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=users-feature
   php artisan test --testsuite=users-unit
   php artisan test --testsuite=users-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for future agent)
