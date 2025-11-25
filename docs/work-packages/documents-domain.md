# Udocuments Domain Test Organization

**Package ID:** [Auto-assigned]  
**Domain:** documents  
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 45678  
**Branch:** `test-org/documents-domain`

## Overview

Organize all documents-related tests into a cohesive test group with reproducible seed data.

## Tasks Checklist

### Phase 1: PHPUnit Groups
- [ ] Add `@group documents` annotation to all documents tests
- [ ] Verify annotations: `grep -r "@group documents" tests/`

### Phase 2: Test Suites
- [ ] Add `documents-unit` test suite to `phpunit.xml`
- [ ] Add `documents-feature` test suite
- [ ] Add `documents-integration` test suite
- [ ] Verify: `php artisan test --testsuite=documents-feature`

### Phase 3: Test Data Seeding
- [ ] Add `TestDataSeeder::seedUdocumentsDomain($seed = 45678)` method
- [ ] Use fixed seed for reproducibility
- [ ] Verify: `php artisan test --group=documents --seed=45678`

### Phase 4: Fixtures
- [ ] Create `tests/fixtures/domains/documents/fixtures.json`
- [ ] Use standard test data structure

### Phase 5: Playwright Projects
- [ ] Add `documents-e2e-chromium` project to `playwright.config.ts`
- [ ] Verify: `npm run test:documents:e2e`

### Phase 6: NPM Scripts
- [ ] Add scripts to `package.json`:
  - `test:documents`
  - `test:documents:unit`
  - `test:documents:feature`
  - `test:documents:integration`
  - `test:documents:e2e`
- [ ] Verify all scripts work

## Files to Modify

### Add @group annotations:
- `tests/Feature/**/documents*.php`
- `tests/Unit/**/documents*.php`
- `tests/Integration/**/documents*.php`

### Modify configuration:
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seed method
- `playwright.config.ts` - Add project
- `package.json` - Add scripts

## Verification Commands

```bash
# 1. Check annotations
grep -r "@group documents" tests/

# 2. Run test suite
php artisan test --testsuite=documents-feature

# 3. Verify reproducibility
php artisan test --group=documents --seed=45678 > /tmp/documents-test1.log
php artisan test --group=documents --seed=45678 > /tmp/documents-test2.log
diff /tmp/documents-test1.log /tmp/documents-test2.log
# Should output nothing (identical results)

# 4. Test NPM scripts
npm run test:documents
npm run test:documents:unit
npm run test:documents:feature
npm run test:documents:e2e
```

## Completion Criteria

✅ All documents tests have `@group documents` annotation  
✅ Test suites exist and work  
✅ `TestDataSeeder::seedUdocumentsDomain()` method exists with fixed seed  
✅ Fixtures file created  
✅ Playwright project added  
✅ NPM scripts added and working  
✅ Reproducibility verified  
✅ Documentation updated
