# Auth Domain Test Files Audit

**Date:** 2025-11-08  
**Purpose:** Complete inventory of all authentication-related test files for Auth Domain organization work package  
**Seed:** 12345 (fixed for reproducibility)

---

## Summary

**Total Auth Test Files:** 9  
**Files with @group auth:** 3  
**Files needing @group auth:** 6

---

## Test Files Inventory

### Feature Tests (`tests/Feature/Auth/`)

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/Auth/PasswordChangeTest.php` | `PasswordChangeTest` | ✅ **HAS** | ~10+ | Password change functionality |
| `tests/Feature/Auth/EmailVerificationTest.php` | `EmailVerificationTest` | ✅ **HAS** | 8 | Email verification resend |
| `tests/Feature/Auth/AuthenticationTest.php` | `AuthenticationTest` | ❌ **MISSING** | 5 | Basic login/logout/profile |
| `tests/Feature/Auth/AuthenticationModuleTest.php` | `AuthenticationModuleTest` | ❌ **MISSING** | 18 | Comprehensive auth module tests |

### Unit Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Unit/AuthServiceTest.php` | `AuthServiceTest` | ✅ **HAS** | 17 | JWT auth service unit tests |

### Integration Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Integration/SecurityIntegrationTest.php` | `SecurityIntegrationTest` | ✅ **HAS** | Multiple | Security integration tests |

### Other Auth-Related Tests

| File | Class Name | @group Status | Test Methods | Notes |
|------|-----------|---------------|---------------|-------|
| `tests/Feature/AuthTest.php` | `AuthTest` | ❌ **MISSING** | Multiple | General auth feature tests |
| `tests/Feature/Buttons/ButtonAuthenticationTest.php` | `ButtonAuthenticationTest` | ❌ **MISSING** | Multiple | Button auth flows |
| `tests/Feature/Integration/SecurityIntegrationTest.php` | `SecurityIntegrationTest` | ❌ **MISSING** | Multiple | Feature-level security integration |
| `tests/Browser/AuthenticationTest.php` | `AuthenticationTest` | ❌ **MISSING** | Multiple | Browser/Dusk auth tests |

---

## Detailed File Analysis

### ✅ Files WITH @group auth (3 files)

#### 1. `tests/Feature/Auth/PasswordChangeTest.php`
- **Status:** ✅ Has `@group auth`
- **Line:** 15
- **Test Methods:** ~10+ (password change scenarios)
- **Action Required:** None - already annotated

#### 2. `tests/Feature/Auth/EmailVerificationTest.php`
- **Status:** ✅ Has `@group auth`
- **Line:** 16
- **Test Methods:** 8
  - `test_resend_verification_email_for_unverified_user_unauthenticated()`
  - `test_resend_verification_email_for_unverified_user_authenticated()`
  - `test_resend_verification_fails_for_already_verified_user()`
  - `test_resend_verification_fails_for_non_existent_email()`
  - `test_resend_verification_requires_email_when_unauthenticated()`
  - `test_resend_verification_respects_rate_limiting()`
  - `test_resend_verification_for_authenticated_user_uses_their_email()`
  - `test_resend_verification_email_validation()`
- **Action Required:** None - already annotated

#### 3. `tests/Unit/AuthServiceTest.php`
- **Status:** ✅ Has `@group auth`
- **Line:** 19
- **Test Methods:** 17
  - `test_login_success_with_valid_credentials()`
  - `test_login_fails_with_invalid_email()`
  - `test_login_fails_with_wrong_password()`
  - `test_register_success_with_valid_data()`
  - `test_register_fails_with_existing_email()`
  - `test_create_token_for_user_success()`
  - `test_validate_token_success_with_valid_token()`
  - `test_validate_token_fails_with_invalid_token()`
  - `test_validate_token_fails_with_expired_token()`
  - `test_get_current_user_success_with_valid_token()`
  - `test_get_current_user_fails_with_invalid_token()`
  - `test_refresh_token_success_with_valid_token()`
  - `test_check_permission_with_user_having_permission()`
  - `test_check_permission_with_user_not_having_permission()`
  - `test_logout_success()`
  - `test_get_token_payload_success_with_valid_token()`
  - `test_get_token_payload_fails_with_invalid_token()`
- **Action Required:** None - already annotated

#### 4. `tests/Integration/SecurityIntegrationTest.php`
- **Status:** ✅ Has `@group auth`
- **Line:** 23
- **Test Methods:** Multiple (security integration scenarios)
- **Action Required:** None - already annotated

---

### ❌ Files MISSING @group auth (6 files)

#### 1. `tests/Feature/Auth/AuthenticationTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `AuthenticationTest`
- **Test Methods:** 5
  - `test_user_can_login_with_valid_credentials()`
  - `test_user_cannot_login_with_invalid_credentials()`
  - `test_user_can_logout()`
  - `test_can_get_authenticated_user_profile()` (skipped)
  - `test_cannot_access_protected_endpoint_without_token()`
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~11-13)

#### 2. `tests/Feature/Auth/AuthenticationModuleTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `AuthenticationModuleTest`
- **Test Methods:** 18
  - `test_user_registration_via_api()`
  - `test_user_registration_validation()`
  - `test_user_login_via_api()`
  - `test_user_login_with_invalid_credentials()`
  - `test_user_logout_via_api()`
  - `test_password_reset_request()` (skipped)
  - `test_password_reset_with_invalid_email()`
  - `test_get_current_user_info()` (skipped)
  - `test_get_user_permissions()` (skipped)
  - `test_token_validation()`
  - `test_token_validation_with_invalid_token()`
  - `test_token_refresh()`
  - `test_tenant_isolation_in_user_management()` (skipped)
  - `test_admin_cross_tenant_access()` (skipped)
  - `test_rate_limiting_on_auth_endpoints()`
  - `test_password_policy_enforcement()`
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~13-18)

#### 3. `tests/Feature/AuthTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `AuthTest`
- **Test Methods:** Multiple (general auth feature tests)
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~11-13)

#### 4. `tests/Feature/Buttons/ButtonAuthenticationTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `ButtonAuthenticationTest`
- **Test Methods:** Multiple (button auth flows)
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~14-18)

#### 5. `tests/Feature/Integration/SecurityIntegrationTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `SecurityIntegrationTest` (Feature\Integration namespace)
- **Test Methods:** Multiple (feature-level security integration)
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~14)

#### 6. `tests/Browser/AuthenticationTest.php`
- **Status:** ❌ Missing `@group auth`
- **Class:** `AuthenticationTest` (Browser namespace)
- **Test Methods:** Multiple (Browser/Dusk auth tests)
- **Action Required:** Add `@group auth` annotation in PHPDoc block (line ~11-13)

---

## Checklist for Continue Agent

### Phase 1: Add @group Annotations

- [ ] `tests/Feature/Auth/AuthenticationTest.php` - Add `@group auth` to PHPDoc
- [ ] `tests/Feature/Auth/AuthenticationModuleTest.php` - Add `@group auth` to PHPDoc
- [ ] `tests/Feature/AuthTest.php` - Add `@group auth` to PHPDoc
- [ ] `tests/Feature/Buttons/ButtonAuthenticationTest.php` - Add `@group auth` to PHPDoc
- [ ] `tests/Feature/Integration/SecurityIntegrationTest.php` - Add `@group auth` to PHPDoc
- [ ] `tests/Browser/AuthenticationTest.php` - Add `@group auth` to PHPDoc

### Verification Command

After adding annotations, verify with:
```bash
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/ tests/Feature/Buttons/ tests/Browser/
```

Expected output should show all 9 files.

---

## Test Suite Organization

### Current Test Suites (from Core Infrastructure)

The following test suites are already configured in `phpunit.xml`:

- `auth-unit` - Unit tests with `@group auth`
- `auth-feature` - Feature tests with `@group auth`
- `auth-integration` - Integration tests with `@group auth`

### Browser Tests

Browser tests (Dusk) are not included in PHPUnit test suites by default. Consider:
- Adding to Playwright E2E tests (handled by Codex)
- Or creating separate Dusk test suite if needed

---

## Notes

1. **E2E Tests:** Auth E2E tests in `tests/e2e/auth/` are handled separately by Codex Agent (Frontend E2E Organization work package).

2. **Test Methods Count:** Some test methods are marked as `skipped` using `markTestSkipped()`. These should still be included in the `@group auth` annotation.

3. **Namespace Conflicts:** There are two `SecurityIntegrationTest` classes:
   - `Tests\Integration\SecurityIntegrationTest` (has @group auth)
   - `Tests\Feature\Integration\SecurityIntegrationTest` (missing @group auth)
   - Both should have `@group auth` annotation.

4. **Browser Tests:** Browser/Dusk tests may need special handling. Consider if they should be part of the auth domain or handled separately.

---

## Next Steps

1. Continue Agent should add `@group auth` annotations to the 6 missing files
2. Verify all annotations are correct using grep command
3. Run test suite to ensure tests are grouped correctly:
   ```bash
   php artisan test --group=auth --seed=12345
   ```
4. Verify test suites work:
   ```bash
   php artisan test --testsuite=auth-feature
   php artisan test --testsuite=auth-unit
   php artisan test --testsuite=auth-integration
   ```

---

**Last Updated:** 2025-11-08  
**Maintainer:** Cursor Agent (Prepared for Continue Agent)
