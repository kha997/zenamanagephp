# Uprojects Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** projects  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 23456  
**Branch:** `test-org/projects-domain`

## Overview

Organize all projects-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group projects` annotation to all projects tests
- [ ] Verify annotations: `grep -r "@group projects" tests/`

### Phase 2: Test Suites
- [ ] Add `projects-unit` test suite to `phpunit.xml`
- [ ] Add `projects-feature` test suite
- [ ] Add `projects-integration` test suite
- [ ] Verify: `php artisan test --testsuite=projects-feature`

### Phase 3: Test Data Seeding
- [ ] Add `TestDataSeeder::seedUprojectsDomain($seed = 23456)` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=projects --seed=23456`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/projects/fixtures.json`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add `projects-e2e-chromium` project to `playwright.config.ts`
- [ ] Verify: `npm run test:projects:e2e`

### Phase 6: NPM Scripts
- [ ] Add scripts to `package.json`:
  - `test:projects`
  - `test:projects:unit`
  - `test:projects:feature`
  - `test:projects:integration`
  - `test:projects:e2e`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/**/projects*.php`
- `tests/Unit/**/projects*.php`
- `tests/Integration/**/projects*.php`

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seed method
- `playwright.config.ts` - Add project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group projects" tests/

# 2. Run test suite
php artisan test --testsuite=projects-feature

# 3. Verify reproducibility
php artisan test --group=projects --seed=23456 > /tmp/projects-test1.log
php artisan test --group=projects --seed=23456 > /tmp/projects-test2.log
diff /tmp/projects-test1.log /tmp/projects-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:projects
npm run test:projects:unit
npm run test:projects:feature
npm run test:projects:e2e
```

## Completion Criteria

✅ All projects tests have `@group projects` annotation  
✅ Test suites exist and work  
✅ `TestDataSeeder::seedUprojectsDomain()` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
