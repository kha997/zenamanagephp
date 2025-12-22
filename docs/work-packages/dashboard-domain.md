# Udashboard Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** dashboard  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 67890  
**Branch:** `test-org/dashboard-domain`

## Overview

Organize all dashboard-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group dashboard` annotation to all dashboard tests
- [ ] Verify annotations: `grep -r "@group dashboard" tests/`

### Phase 2: Test Suites
- [ ] Add `dashboard-unit` test suite to `phpunit.xml`
- [ ] Add `dashboard-feature` test suite
- [ ] Add `dashboard-integration` test suite
- [ ] Verify: `php artisan test --testsuite=dashboard-feature`

### Phase 3: Test Data Seeding
- [ ] Add `TestDataSeeder::seedUdashboardDomain($seed = 67890)` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=dashboard --seed=67890`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/dashboard/fixtures.json`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add `dashboard-e2e-chromium` project to `playwright.config.ts`
- [ ] Verify: `npm run test:dashboard:e2e`

### Phase 6: NPM Scripts
- [ ] Add scripts to `package.json`:
  - `test:dashboard`
  - `test:dashboard:unit`
  - `test:dashboard:feature`
  - `test:dashboard:integration`
  - `test:dashboard:e2e`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/**/dashboard*.php`
- `tests/Unit/**/dashboard*.php`
- `tests/Integration/**/dashboard*.php`

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seed method
- `playwright.config.ts` - Add project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group dashboard" tests/

# 2. Run test suite
php artisan test --testsuite=dashboard-feature

# 3. Verify reproducibility
php artisan test --group=dashboard --seed=67890 > /tmp/dashboard-test1.log
php artisan test --group=dashboard --seed=67890 > /tmp/dashboard-test2.log
diff /tmp/dashboard-test1.log /tmp/dashboard-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:dashboard
npm run test:dashboard:unit
npm run test:dashboard:feature
npm run test:dashboard:e2e
```

## Completion Criteria

✅ All dashboard tests have `@group dashboard` annotation  
✅ Test suites exist and work  
✅ `TestDataSeeder::seedUdashboardDomain()` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
