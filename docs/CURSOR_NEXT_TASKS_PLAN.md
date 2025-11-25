# Cursor Next Tasks Plan

**Created:** 2025-11-08 10:15  
**Purpose:** Detailed plan for Cursor's next tasks to prepare support materials for remaining domains  
**Status:** Ready to implement  
**Estimated Time:** 8-10 hours total

---

## Overview

This plan outlines tasks for Cursor to prepare comprehensive support materials for the remaining 5 domains (Projects, Tasks, Documents, Users, Dashboard), validate infrastructure, and improve documentation. These tasks can be done in parallel with Codex and Continue's work without conflicts.

---

## Phase 1: Projects Domain Support Materials (Priority: High)

**Estimated Time:** 1.5 hours  
**Seed:** 23456  
**Similar to:** Auth Domain support materials

### Task 1.1: Audit Projects Test Files
**File:** `docs/work-packages/projects-domain-audit.md`

**Actions:**
- Scan all test directories for projects-related tests:
  - `tests/Feature/**/*Project*.php` (31 files found)
  - `tests/Unit/**/*Project*.php`
  - `tests/Integration/**/*Project*.php`
  - `tests/Browser/**/*Project*.php`
- Check current `@group projects` status for each file
- Document which files need annotations added
- Create checklist table with:
  - File path
  - Current status (@group present/absent)
  - Test class name
  - Number of test methods
  - Notes/observations

**Expected Output:**
- Complete inventory of 30+ projects test files
- Status of @group annotations
- Checklist for future agent

### Task 1.2: Create Projects Domain Helper Guide
**File:** `docs/work-packages/projects-domain-helper-guide.md`

**Sections:**
1. Overview
2. Prerequisites
3. File Inventory
4. Step-by-Step Implementation:
   - Phase 1: Add @group annotations (with examples)
   - Phase 2: Verify test suites (already done in Core Infrastructure)
   - Phase 3: Implement seedProjectsDomain method (with template)
   - Phase 4: Create fixtures file structure
   - Phase 5: Playwright projects (if applicable)
   - Phase 6: NPM scripts
5. Common Pitfalls
6. Verification Steps
7. Troubleshooting

**Key Content:**
- Example @group annotation format
- Reference to existing TestDataSeeder patterns
- Fixed seed value: 23456
- Expected return structure for seedProjectsDomain
- Models needed: Project, Component, Client, ProjectTemplate, UserRoleProject

### Task 1.3: Create seedProjectsDomain Template
**File:** `tests/Helpers/TestDataSeeder.php` (add method stub)

**Method Signature:**
```php
/**
 * Seed projects domain test data with fixed seed for reproducibility
 * 
 * @param int $seed Fixed seed value (default: 23456)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     projects: \App\Models\Project[],
 *     components: \App\Models\Component[],
 *     clients: \App\Models\Client[]
 * }
 */
public static function seedProjectsDomain(int $seed = 23456): array
```

**Template Structure:**
- Set seed: `mt_srand($seed)`
- Create tenant (use existing `createTenant()` pattern)
- Create users (project manager, team members, client)
- Create clients
- Create projects with different statuses
- Create components for projects
- Attach users to projects with roles
- Return structured array

### Task 1.4: Create Quick Start Guide
**File:** `docs/work-packages/projects-domain-quick-start.md`

**Content:**
- One-page quick reference
- Essential commands
- File checklist
- Key reminders (seed value, trait usage)
- Links to detailed guides

---

## Phase 2: Tasks Domain Support Materials (Priority: High)

**Estimated Time:** 1.5 hours  
**Seed:** 34567  
**Similar to:** Auth Domain support materials

### Task 2.1: Audit Tasks Test Files
**File:** `docs/work-packages/tasks-domain-audit.md`

**Actions:**
- Scan all test directories for tasks-related tests:
  - `tests/Feature/**/*Task*.php` (19 files found)
  - `tests/Unit/**/*Task*.php`
  - `tests/Integration/**/*Task*.php`
  - `tests/Browser/**/*Task*.php`
- Check current `@group tasks` status
- Document files needing annotations
- Create checklist table

**Expected Output:**
- Complete inventory of 19+ tasks test files
- Status of @group annotations
- Checklist for future agent

### Task 2.2: Create Tasks Domain Helper Guide
**File:** `docs/work-packages/tasks-domain-helper-guide.md`

**Similar structure to Projects guide, but focused on:**
- Task models and relationships
- Task dependencies
- Task assignments
- Task statuses and workflows

### Task 2.3: Create seedTasksDomain Template
**File:** `tests/Helpers/TestDataSeeder.php` (add method stub)

**Models needed:** Task, TaskAssignment, TaskDependency, Project (for parent project)

### Task 2.4: Create Quick Start Guide
**File:** `docs/work-packages/tasks-domain-quick-start.md`

---

## Phase 3: Documents Domain Support Materials (Priority: Medium)

**Estimated Time:** 1 hour  
**Seed:** 45678

### Task 3.1: Audit Documents Test Files
**File:** `docs/work-packages/documents-domain-audit.md`

### Task 3.2: Create Documents Domain Helper Guide
**File:** `docs/work-packages/documents-domain-helper-guide.md`

**Focus on:**
- Document models and relationships
- File uploads
- Document sharing
- Document versions

### Task 3.3: Create seedDocumentsDomain Template
**File:** `tests/Helpers/TestDataSeeder.php` (add method stub)

### Task 3.4: Create Quick Start Guide
**File:** `docs/work-packages/documents-domain-quick-start.md`

---

## Phase 4: Users Domain Support Materials (Priority: Medium)

**Estimated Time:** 1 hour  
**Seed:** 56789

### Task 4.1: Audit Users Test Files
**File:** `docs/work-packages/users-domain-audit.md`

### Task 4.2: Create Users Domain Helper Guide
**File:** `docs/work-packages/users-domain-helper-guide.md`

**Focus on:**
- User management
- User profiles
- User settings
- User roles and permissions

### Task 4.3: Create seedUsersDomain Template
**File:** `tests/Helpers/TestDataSeeder.php` (add method stub)

### Task 4.4: Create Quick Start Guide
**File:** `docs/work-packages/users-domain-quick-start.md`

---

## Phase 5: Dashboard Domain Support Materials (Priority: Medium)

**Estimated Time:** 1 hour  
**Seed:** 67890

### Task 5.1: Audit Dashboard Test Files
**File:** `docs/work-packages/dashboard-domain-audit.md`

### Task 5.2: Create Dashboard Domain Helper Guide
**File:** `docs/work-packages/dashboard-domain-helper-guide.md`

**Focus on:**
- Dashboard widgets
- Dashboard metrics
- KPI displays
- Dashboard customization

### Task 5.3: Create seedDashboardDomain Template
**File:** `tests/Helpers/TestDataSeeder.php` (add method stub)

### Task 5.4: Create Quick Start Guide
**File:** `docs/work-packages/dashboard-domain-quick-start.md`

---

## Phase 6: Infrastructure Validation (Priority: High)

**Estimated Time:** 1 hour

### Task 6.1: Validate DomainTestIsolation Trait
**Actions:**
- Run DomainTestIsolationTest (fix any remaining syntax errors if needed)
- Test trait with sample domain (e.g., auth)
- Verify all methods work correctly
- Document any issues found

### Task 6.2: Validate Test Suites
**Actions:**
- Verify all 18 test suites in phpunit.xml are correct
- Test running suites: `php artisan test --testsuite=auth-feature`
- Verify group filtering works: `php artisan test --group=auth`
- Document any issues

### Task 6.3: Validate Aggregate Script
**Actions:**
- Test aggregate-test-results.sh with sample JUnit XML files
- Verify JSON output format
- Test filtering by domain and type
- Verify jq dependency check works
- Document any issues

### Task 6.4: Validate CI Workflow
**Actions:**
- Check CI workflow syntax
- Verify matrix strategy configuration
- Check job dependencies
- Verify jq installation step
- Document any issues

---

## Phase 7: Documentation Improvements (Priority: Medium)

**Estimated Time:** 1 hour

### Task 7.1: Update DOCUMENTATION_INDEX.md
**Actions:**
- Add links to all new domain support materials
- Add links to test organization documentation
- Organize test-related documentation section
- Add cross-references

### Task 7.2: Create Migration Guide
**File:** `docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md`

**Content:**
- How to migrate existing tests to new structure
- Before/after examples
- Common migration patterns
- Troubleshooting migration issues

### Task 7.3: Create Best Practices Guide
**File:** `docs/TEST_ORGANIZATION_BEST_PRACTICES.md`

**Content:**
- Best practices for domain test organization
- When to use DomainTestIsolation trait
- How to choose appropriate seeds
- Test data management best practices
- Reproducibility guidelines

### Task 7.4: Update TEST_SUITE_SUMMARY.md
**Actions:**
- Add references to domain support materials
- Update examples with new domains
- Add links to helper guides

---

## Phase 8: Integration Preparation (Priority: Low)

**Estimated Time:** 0.5 hours

### Task 8.1: Create Integration Checklist
**File:** `docs/DOMAIN_INTEGRATION_CHECKLIST.md`

**Content:**
- Checklist for integrating domain work after review
- Pre-integration steps
- Verification procedures
- Rollback procedures

### Task 8.2: Create Rollback Procedures
**File:** `docs/ROLLBACK_PROCEDURES.md`

**Content:**
- How to rollback domain changes if issues found
- How to rollback Core Infrastructure if needed
- Data cleanup procedures

---

## Implementation Order

### Recommended Sequence:

1. **Phase 1: Projects Domain** (1.5h) - High priority, most test files
2. **Phase 2: Tasks Domain** (1.5h) - High priority, many test files
3. **Phase 6: Infrastructure Validation** (1h) - Ensure foundation is solid
4. **Phase 3: Documents Domain** (1h) - Medium priority
5. **Phase 4: Users Domain** (1h) - Medium priority
6. **Phase 5: Dashboard Domain** (1h) - Medium priority
7. **Phase 7: Documentation Improvements** (1h) - Improve discoverability
8. **Phase 8: Integration Preparation** (0.5h) - Prepare for future

**Total Estimated Time:** 8.5 hours

---

## Success Criteria

### For Each Domain:
- [ ] Audit file created with complete inventory
- [ ] Helper guide created with step-by-step instructions
- [ ] Quick start guide created
- [ ] seed{Domain}Domain() template method added to TestDataSeeder
- [ ] All materials follow same structure as Auth Domain

### For Infrastructure Validation:
- [ ] All tests pass
- [ ] Test suites work correctly
- [ ] Aggregate script works correctly
- [ ] CI workflow syntax is correct
- [ ] Issues documented if found

### For Documentation:
- [ ] DOCUMENTATION_INDEX.md updated
- [ ] Migration guide created
- [ ] Best practices guide created
- [ ] TEST_SUITE_SUMMARY.md updated

---

## Notes

- All tasks can be done independently without blocking Codex or Continue
- Use Auth Domain materials as template/reference
- Maintain consistency across all domains
- Update AGENT_COORDINATION_HUB.md after each phase
- Keep files unlocked (no conflicts with other agents)

---

## Files to Create/Modify

### New Files (20 files):
- `docs/work-packages/projects-domain-audit.md`
- `docs/work-packages/projects-domain-helper-guide.md`
- `docs/work-packages/projects-domain-quick-start.md`
- `docs/work-packages/tasks-domain-audit.md`
- `docs/work-packages/tasks-domain-helper-guide.md`
- `docs/work-packages/tasks-domain-quick-start.md`
- `docs/work-packages/documents-domain-audit.md`
- `docs/work-packages/documents-domain-helper-guide.md`
- `docs/work-packages/documents-domain-quick-start.md`
- `docs/work-packages/users-domain-audit.md`
- `docs/work-packages/users-domain-helper-guide.md`
- `docs/work-packages/users-domain-quick-start.md`
- `docs/work-packages/dashboard-domain-audit.md`
- `docs/work-packages/dashboard-domain-helper-guide.md`
- `docs/work-packages/dashboard-domain-quick-start.md`
- `docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md`
- `docs/TEST_ORGANIZATION_BEST_PRACTICES.md`
- `docs/DOMAIN_INTEGRATION_CHECKLIST.md`
- `docs/ROLLBACK_PROCEDURES.md`

### Modified Files (3 files):
- `tests/Helpers/TestDataSeeder.php` (add 5 seed method templates)
- `docs/DOCUMENTATION_INDEX.md` (add links)
- `TEST_SUITE_SUMMARY.md` (update with new domains)

---

**Last Updated:** 2025-11-08 10:15  
**Created By:** Cursor  
**Status:** Ready to implement

