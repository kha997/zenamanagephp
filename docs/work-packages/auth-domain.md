# Auth Domain Test Organization

**Package ID:** 1  
**Domain:** auth  
**Agent:** Continue Agent Test  
**Status:** Ready  
**Seed:** 12345  
**Branch:** `test-org/auth-domain`

## Overview

Organize all authentication-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group auth` annotation to `tests/Feature/Auth/*.php`
- [ ] Add `@group auth` annotation to `tests/Unit/AuthServiceTest.php`
- [ ] Add `@group auth` annotation to `tests/Integration/SecurityIntegrationTest.php`
- [ ] Verify annotations: `grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/`

### Phase 2: Test Suites
- [ ] Add `auth-unit` test suite to `phpunit.xml`:
  ```xml
  <testsuite name="auth-unit">
    <directory>tests/Unit</directory>
    <group>auth</group>
  </testsuite>
  ```
- [ ] Add `auth-feature` test suite
- [ ] Add `auth-integration` test suite
- [ ] Verify: `php artisan test --testsuite=auth-feature`

### Phase 3: Test Data Seeding
- [ ] Add method to `tests/Helpers/TestDataSeeder.php`:
  ```php
  public static function seedAuthDomain(int $seed = 12345): array
  {
      mt_srand($seed);
      // Create tenants, users, roles, permissions
      // Return array of created entities
  }
  ```
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=auth --seed=12345`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/auth/fixtures.json`:
  ```json
  {
    "tenants": [...],
    "users": [...],
    "roles": [...],
    "permissions": [...]
  }
  ```
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add to `playwright.config.ts`:
  ```typescript
  {
    name: 'auth-e2e-chromium',
    testMatch: '**/E2E/auth/**/*.spec.ts',
    use: { ...devices['Desktop Chrome'] },
  }
  ```
- [ ] Verify: `npm run test:auth:e2e`

### Phase 6: NPM Scripts
- [ ] Add to `package.json`:
  ```json
  "test:auth": "php artisan test --group=auth",
  "test:auth:unit": "php artisan test --testsuite=auth-unit",
  "test:auth:feature": "php artisan test --testsuite=auth-feature",
  "test:auth:integration": "php artisan test --testsuite=auth-integration",
  "test:auth:e2e": "playwright test --project=auth-e2e-chromium"
  ```
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/Auth/PasswordChangeTest.php`
- `tests/Unit/AuthServiceTest.php`
- `tests/Integration/SecurityIntegrationTest.php`
- Any other auth-related tests

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seedAuthDomain method
- `playwright.config.ts` - Add auth project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/

# 2. Run test suite
php artisan test --testsuite=auth-feature

# 3. Verify reproducibility (should produce identical results)
php artisan test --group=auth --seed=12345 > /tmp/auth-test1.log
php artisan test --group=auth --seed=12345 > /tmp/auth-test2.log
diff /tmp/auth-test1.log /tmp/auth-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:auth
npm run test:auth:unit
npm run test:auth:feature
npm run test:auth:e2e
```

## Completion Criteria

✅ All auth tests have `@group auth` annotation  
✅ Test suites (`auth-unit`, `auth-feature`, `auth-integration`) exist and work  
✅ `TestDataSeeder::seedAuthDomain()` method exists with fixed seed  
✅ Fixtures file created at `tests/fixtures/domains/auth/fixtures.json`  
✅ Playwright project `auth-e2e-chromium` added  
✅ NPM scripts added and working  
✅ Reproducibility verified (same seed = same results)  
✅ Documentation updated

## Notes

- Use seed `12345` consistently for all auth domain tests
- Follow patterns from existing TestDataSeeder methods
- Ensure test isolation (each test should clean up after itself)
- Coordinate with Core Infrastructure package for DomainTestIsolation trait

