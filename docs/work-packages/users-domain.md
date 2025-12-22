# Uusers Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** users  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 56789  
**Branch:** `test-org/users-domain`

## Overview

Organize all users-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group users` annotation to all users tests
- [ ] Verify annotations: `grep -r "@group users" tests/`

### Phase 2: Test Suites
- [ ] Add `users-unit` test suite to `phpunit.xml`
- [ ] Add `users-feature` test suite
- [ ] Add `users-integration` test suite
- [ ] Verify: `php artisan test --testsuite=users-feature`

### Phase 3: Test Data Seeding
- [ ] Add `TestDataSeeder::seedUusersDomain($seed = 56789)` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=users --seed=56789`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/users/fixtures.json`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add `users-e2e-chromium` project to `playwright.config.ts`
- [ ] Verify: `npm run test:users:e2e`

### Phase 6: NPM Scripts
- [ ] Add scripts to `package.json`:
  - `test:users`
  - `test:users:unit`
  - `test:users:feature`
  - `test:users:integration`
  - `test:users:e2e`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/**/users*.php`
- `tests/Unit/**/users*.php`
- `tests/Integration/**/users*.php`

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seed method
- `playwright.config.ts` - Add project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group users" tests/

# 2. Run test suite
php artisan test --testsuite=users-feature

# 3. Verify reproducibility
php artisan test --group=users --seed=56789 > /tmp/users-test1.log
php artisan test --group=users --seed=56789 > /tmp/users-test2.log
diff /tmp/users-test1.log /tmp/users-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:users
npm run test:users:unit
npm run test:users:feature
npm run test:users:e2e
```

## Completion Criteria

✅ All users tests have `@group users` annotation  
✅ Test suites exist and work  
✅ `TestDataSeeder::seedUusersDomain()` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
