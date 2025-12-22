# Utasks Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** tasks  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 34567  
**Branch:** `test-org/tasks-domain`

## Overview

Organize all tasks-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group tasks` annotation to all tasks tests
- [ ] Verify annotations: `grep -r "@group tasks" tests/`

### Phase 2: Test Suites
- [ ] Add `tasks-unit` test suite to `phpunit.xml`
- [ ] Add `tasks-feature` test suite
- [ ] Add `tasks-integration` test suite
- [ ] Verify: `php artisan test --testsuite=tasks-feature`

### Phase 3: Test Data Seeding
- [ ] Add `TestDataSeeder::seedUtasksDomain($seed = 34567)` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=tasks --seed=34567`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/tasks/fixtures.json`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add `tasks-e2e-chromium` project to `playwright.config.ts`
- [ ] Verify: `npm run test:tasks:e2e`

### Phase 6: NPM Scripts
- [ ] Add scripts to `package.json`:
  - `test:tasks`
  - `test:tasks:unit`
  - `test:tasks:feature`
  - `test:tasks:integration`
  - `test:tasks:e2e`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/**/tasks*.php`
- `tests/Unit/**/tasks*.php`
- `tests/Integration/**/tasks*.php`

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seed method
- `playwright.config.ts` - Add project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group tasks" tests/

# 2. Run test suite
php artisan test --testsuite=tasks-feature

# 3. Verify reproducibility
php artisan test --group=tasks --seed=34567 > /tmp/tasks-test1.log
php artisan test --group=tasks --seed=34567 > /tmp/tasks-test2.log
diff /tmp/tasks-test1.log /tmp/tasks-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:tasks
npm run test:tasks:unit
npm run test:tasks:feature
npm run test:tasks:e2e
```

## Completion Criteria

✅ All tasks tests have `@group tasks` annotation  
✅ Test suites exist and work  
✅ `TestDataSeeder::seedUtasksDomain()` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
