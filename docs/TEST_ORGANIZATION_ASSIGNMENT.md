# Test Organization Work Assignment Guide

**Date:** 2025-11-08  
**Purpose:** Phân công công việc tổ chức test suite cho nhiều agents

## Cấu trúc Work Packages

Mỗi domain được chia thành một work package độc lập, có thể giao cho agent khác nhau.

## Work Packages

### Package 1: Auth Domain
**Agent:** Continue Agent Test  
**Status:** Ready  
**Seed:** 12345  
**Branch:** `test-org/auth-domain`

**Tasks:**
- [ ] Add `@group auth` annotations to all auth tests
- [ ] Create `auth-unit`, `auth-feature`, `auth-integration` test suites in phpunit.xml
- [ ] Extend `TestDataSeeder::seedAuthDomain($seed = 12345)`
- [ ] Create `tests/fixtures/domains/auth/fixtures.json`
- [ ] Add `auth-e2e-chromium` project to playwright.config.ts
- [ ] Add NPM scripts: `test:auth`, `test:auth:unit`, `test:auth:feature`, `test:auth:e2e`

**Files to modify:**
- `tests/Feature/Auth/*.php` - Add @group annotations
- `tests/Unit/AuthServiceTest.php` - Add @group annotation
- `tests/Integration/SecurityIntegrationTest.php` - Add @group annotation
- `phpunit.xml` - Add test suites
- `tests/Helpers/TestDataSeeder.php` - Add seedAuthDomain method
- `playwright.config.ts` - Add auth project
- `package.json` - Add scripts

**Verification:**
```bash
# Check annotations
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/AuthServiceTest.php

# Run test suite
php artisan test --testsuite=auth-feature

# Verify reproducibility
php artisan test --group=auth --seed=12345 > /tmp/test1.log
php artisan test --group=auth --seed=12345 > /tmp/test2.log
diff /tmp/test1.log /tmp/test2.log  # Should be empty
```

---

### Package 2: Projects Domain
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 23456  
**Branch:** `test-org/projects-domain`

**Tasks:**
- [ ] Add `@group projects` annotations to all projects tests
- [ ] Create `projects-unit`, `projects-feature`, `projects-integration` test suites
- [ ] Extend `TestDataSeeder::seedProjectsDomain($seed = 23456)`
- [ ] Create `tests/fixtures/domains/projects/fixtures.json`
- [ ] Add `projects-e2e-chromium` project to playwright.config.ts
- [ ] Add NPM scripts: `test:projects`, `test:projects:unit`, `test:projects:feature`, `test:projects:e2e`

**Files to modify:**
- `tests/Feature/Api/Projects/*.php`
- `tests/Feature/ProjectManagementTest.php`
- `tests/Unit/Services/ProjectServiceTest.php` (if exists)
- `phpunit.xml`
- `tests/Helpers/TestDataSeeder.php`
- `playwright.config.ts`
- `package.json`

**Verification:** Similar to Auth domain

---

### Package 3: Tasks Domain
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 34567  
**Branch:** `test-org/tasks-domain`

**Tasks:**
- [ ] Add `@group tasks` annotations
- [ ] Create test suites
- [ ] Extend `TestDataSeeder::seedTasksDomain($seed = 34567)`
- [ ] Create fixtures
- [ ] Add Playwright project
- [ ] Add NPM scripts

**Files to modify:**
- `tests/Feature/Api/Tasks/*.php`
- `tests/Feature/TaskManagementTest.php`
- `tests/Unit/Services/TaskServiceTest.php`
- `phpunit.xml`
- `tests/Helpers/TestDataSeeder.php`
- `playwright.config.ts`
- `package.json`

---

### Package 4: Documents Domain
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 45678  
**Branch:** `test-org/documents-domain`

**Tasks:** Similar structure

**Files to modify:**
- `tests/Feature/Api/Documents/*.php`
- `tests/Browser/DocumentManagementTest.php`
- `tests/Unit/Services/DocumentServiceTest.php` (if exists)
- Similar files as above

---

### Package 5: Users Domain
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 56789  
**Branch:** `test-org/users-domain`

**Tasks:** Similar structure

**Files to modify:**
- `tests/Feature/Users/*.php`
- `tests/Feature/Auth/*.php` (user management parts)
- Similar files as above

---

### Package 6: Dashboard Domain
**Agent:** [Unassigned]  
**Status:** Ready  
**Seed:** 67890  
**Branch:** `test-org/dashboard-domain`

**Tasks:** Similar structure

**Files to modify:**
- `tests/Feature/Dashboard/*.php`
- `tests/Integration/DashboardCacheIntegrationTest.php`
- `tests/Browser/DashboardTest.php`
- Similar files as above

---

### Package 7: Core Infrastructure (Cursor)
**Agent:** Cursor  
**Status:** In Progress  
**Branch:** `test-org/core-infrastructure`

**Tasks:**
- [ ] Create `tests/Traits/DomainTestIsolation.php`
- [ ] Update `phpunit.xml` with groups structure
- [ ] Create `scripts/aggregate-test-results.sh`
- [ ] Update `.github/workflows/ci.yml` with matrix strategy
- [ ] Create `docs/TEST_GROUPS.md` documentation
- [ ] Update `TEST_SUITE_SUMMARY.md`

**Files to create:**
- `tests/Traits/DomainTestIsolation.php`
- `scripts/aggregate-test-results.sh`
- `docs/TEST_GROUPS.md`

**Files to modify:**
- `phpunit.xml`
- `.github/workflows/ci.yml`
- `TEST_SUITE_SUMMARY.md`

---

## Workflow

1. **Agent picks a package** from this file
2. **Creates branch:** `git checkout -b test-org/[domain]-domain`
3. **Reads package details** and implements tasks
4. **Updates progress** in `docs/TEST_ORGANIZATION_PROGRESS.md`
5. **Verifies** using provided commands
6. **Commits** with message: `test: organize [domain] domain tests`
7. **Creates PR** or merges when complete

## Reproducibility Requirements

- **Fixed Seeds:** Each domain uses a fixed seed number
- **TestDataSeeder:** All test data must use TestDataSeeder methods
- **Isolation:** Use DomainTestIsolation trait for setup
- **Verification:** Tests must produce identical results with same seed

## Conflict Resolution

If multiple agents work on overlapping files:
1. Check `docs/TEST_ORGANIZATION_PROGRESS.md` for assignments
2. Coordinate via package status
3. Use separate branches per domain
4. Merge in order: Core Infrastructure → Domain packages

## Completion Criteria

Each package is complete when:
- ✅ All annotations added
- ✅ Test suites created and working
- ✅ Seed data methods implemented
- ✅ Fixtures created
- ✅ Playwright projects added
- ✅ NPM scripts added
- ✅ Verification passes (reproducibility)
- ✅ Documentation updated

