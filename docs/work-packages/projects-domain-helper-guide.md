# Projects Domain Helper Guide

**For:** Future Agent (Builder)  
**Purpose:** Comprehensive implementation guide for Projects Domain test organization  
**Reference:** `docs/work-packages/projects-domain.md` (main work package)  
**Audit:** `docs/work-packages/projects-domain-audit.md` (file inventory)

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [File Inventory](#file-inventory)
4. [Step-by-Step Implementation](#step-by-step-implementation)
5. [Common Pitfalls](#common-pitfalls)
6. [Verification Steps](#verification-steps)
7. [Troubleshooting](#troubleshooting)

---

## Overview

This guide will help you implement the Projects Domain test organization work package. The goal is to:

1. Add `@group projects` annotations to all projects-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedProjectsDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `23456` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `projects-unit`, `projects-feature`, `projects-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/projects-domain-audit.md` for file inventory

**Check Core Infrastructure Status:**
```bash
# Verify test suites exist
grep -A 3 "projects-unit\|projects-feature\|projects-integration" phpunit.xml

# Verify trait exists
ls -la tests/Traits/DomainTestIsolation.php
```

---

## File Inventory

### Files to Add @group Annotations (31 files)

Based on `projects-domain-audit.md`, these files need `@group projects`:

**Feature Tests (22 files):**
1. `tests/Feature/ProjectApiTest.php`
2. `tests/Feature/ProjectsApiIntegrationTest.php`
3. `tests/Feature/ProjectManagementTest.php`
4. `tests/Feature/ProjectTest.php`
5. `tests/Feature/ProjectModuleTest.php`
6. `tests/Feature/ProjectTaskControllerTest.php`
7. `tests/Feature/SimpleProjectMilestoneTest.php`
8. `tests/Feature/VerySimpleProjectMilestoneTest.php`
9. `tests/Feature/Api/App/ProjectsControllerTest.php`
10. `tests/Feature/Api/ProjectManagerApiIntegrationTest.php`
11. `tests/Feature/Api/Projects/ProjectsContractTest.php`
12. `tests/Feature/Web/ProjectControllerTest.php`
13. `tests/Feature/Web/WebProjectControllerTest.php`
14. `tests/Feature/Web/WebProjectControllerApiResponseDebugTest.php`
15. `tests/Feature/Web/WebProjectControllerTenantDebugTest.php`
16. `tests/Feature/Web/WebProjectControllerShowDebugTest.php`
17. `tests/Feature/Integration/CompleteProjectWorkflowTest.php`
18. `tests/Feature/Integration/ProjectCalculationDebugTest.php`
19. `tests/Feature/Integration/ProjectsControllerTest.php`
20. `tests/Feature/Integration/SimplifiedProjectsControllerTest.php`
21. `tests/Feature/Integration/WebProjectControllerTest.php`
22. `tests/Feature/Unit/ProjectPolicyTest.php`

**Unit Tests (7 files):**
1. `tests/Unit/Models/ProjectTest.php`
2. `tests/Unit/Services/ProjectServiceTest.php`
3. `tests/Unit/Repositories/ProjectRepositoryTest.php`
4. `tests/Unit/Controllers/Api/ProjectManagerControllerTest.php`
5. `tests/Unit/ProjectPolicyTest.php`
6. `tests/Unit/Policies/ProjectPolicyTest.php`
7. `tests/Unit/Events/ProjectEventTest.php`

**Browser Tests (2 files):**
1. `tests/Browser/ProjectManagementTest.php`
2. `tests/Browser/Smoke/ProjectsFlowTest.php`

### Files to Modify

1. `tests/Helpers/TestDataSeeder.php` - Add `seedProjectsDomain()` method
2. `tests/fixtures/domains/projects/fixtures.json` - Create new file
3. `playwright.config.ts` - Add projects project (if exists)
4. `package.json` - Add NPM scripts

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group projects` annotation to all projects test files.

#### Example: Adding Annotation

**Before:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests cho Project API endpoints
 */
class ProjectApiTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @group projects
 * Feature tests cho Project API endpoints
 */
class ProjectApiTest extends TestCase
{
    // ...
}
```

#### Files to Update

All 31 files listed in the audit need `@group projects` annotation added to their PHPDoc blocks.

#### Verification

After adding annotations, verify:
```bash
grep -r "@group projects" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

Expected: All 31 files should appear in the output.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Test Suites (Already Configured)

The following test suites should already exist in `phpunit.xml`:

```xml
<testsuite name="projects-unit">
    <directory>tests/Unit</directory>
    <group>projects</group>
</testsuite>

<testsuite name="projects-feature">
    <directory>tests/Feature</directory>
    <group>projects</group>
</testsuite>

<testsuite name="projects-integration">
    <directory>tests/Integration</directory>
    <group>projects</group>
</testsuite>
```

#### Verification Commands

```bash
# Test each suite
php artisan test --testsuite=projects-unit
php artisan test --testsuite=projects-feature
php artisan test --testsuite=projects-integration

# Test with fixed seed
php artisan test --group=projects --seed=23456
```

**Expected:** All tests should run successfully.

---

### Phase 3: Implement seedProjectsDomain Method

**Goal:** Add `seedProjectsDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed projects domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete projects domain test setup including:
 * - Tenant
 * - Users (project manager, team members, client contact)
 * - Clients
 * - Projects with different statuses
 * - Components for projects
 * - User-project role assignments
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

#### Implementation Template

```php
public static function seedProjectsDomain(int $seed = 23456): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Projects Test Tenant',
        'slug' => 'projects-test-tenant',
        'status' => 'active',
    ]);
    
    // Create users with different roles
    $users = [];
    
    // Project manager
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Projects PM User',
        'email' => 'pm@projects-test.test',
        'password' => 'password',
    ]);
    
    // Team member
    $users['team_member'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Projects Team Member',
        'email' => 'member@projects-test.test',
        'password' => 'password',
    ]);
    
    // Client contact
    $users['client_contact'] = self::createUserWithRole('client', $tenant, [
        'name' => 'Projects Client Contact',
        'email' => 'client@projects-test.test',
        'password' => 'password',
    ]);
    
    // Admin user
    $users['admin'] = self::createUserWithRole('admin', $tenant, [
        'name' => 'Projects Admin User',
        'email' => 'admin@projects-test.test',
        'password' => 'password',
    ]);
    
    // Create clients
    $clients = [];
    $clients['active'] = \App\Models\Client::create([
        'tenant_id' => $tenant->id,
        'name' => 'Active Test Client',
        'email' => 'active@client.test',
        'phone' => '1234567890',
        'company' => 'Active Client Company',
        'lifecycle_stage' => 'customer',
    ]);
    
    $clients['prospect'] = \App\Models\Client::create([
        'tenant_id' => $tenant->id,
        'name' => 'Prospect Test Client',
        'email' => 'prospect@client.test',
        'phone' => '1234567891',
        'company' => 'Prospect Client Company',
        'lifecycle_stage' => 'prospect',
    ]);
    
    // Create projects with different statuses
    $projects = [];
    
    // Active project
    $projects['active'] = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'PROJ-001',
        'name' => 'Active Test Project',
        'description' => 'An active project for testing',
        'status' => 'active',
        'priority' => 'high',
        'owner_id' => $users['project_manager']->id,
        'client_id' => $clients['active']->id,
        'budget_total' => 100000.00,
        'budget_planned' => 80000.00,
        'budget_actual' => 0.00,
        'progress_pct' => 0,
        'estimated_hours' => 200.0,
        'actual_hours' => 0.0,
        'risk_level' => 'low',
        'start_date' => now(),
        'end_date' => now()->addMonths(6),
    ]);
    
    // Planning project
    $projects['planning'] = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'PROJ-002',
        'name' => 'Planning Test Project',
        'description' => 'A project in planning phase',
        'status' => 'planning',
        'priority' => 'normal',
        'owner_id' => $users['project_manager']->id,
        'client_id' => $clients['prospect']->id,
        'budget_total' => 50000.00,
        'budget_planned' => 50000.00,
        'budget_actual' => 0.00,
        'progress_pct' => 0,
        'estimated_hours' => 100.0,
        'actual_hours' => 0.0,
        'risk_level' => 'medium',
        'start_date' => now()->addMonth(),
        'end_date' => now()->addMonths(4),
    ]);
    
    // On hold project
    $projects['on_hold'] = \App\Models\Project::create([
        'tenant_id' => $tenant->id,
        'code' => 'PROJ-003',
        'name' => 'On Hold Test Project',
        'description' => 'A project on hold',
        'status' => 'on_hold',
        'priority' => 'low',
        'owner_id' => $users['project_manager']->id,
        'client_id' => $clients['active']->id,
        'budget_total' => 75000.00,
        'budget_planned' => 60000.00,
        'budget_actual' => 10000.00,
        'progress_pct' => 15,
        'estimated_hours' => 150.0,
        'actual_hours' => 20.0,
        'risk_level' => 'high',
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonths(5),
    ]);
    
    // Create components for active project
    $components = [];
    $componentNames = ['Design', 'Development', 'Testing', 'Deployment'];
    
    foreach ($componentNames as $index => $componentName) {
        $components[] = \App\Models\Component::create([
            'tenant_id' => $tenant->id,
            'project_id' => $projects['active']->id,
            'name' => $componentName,
            'description' => "Component: {$componentName}",
            'type' => 'phase',
            'status' => $index === 0 ? 'active' : 'pending',
            'priority' => 'normal',
            'progress_percent' => $index === 0 ? 25 : 0,
            'planned_cost' => 20000.00,
            'actual_cost' => $index === 0 ? 5000.00 : 0.00,
        ]);
    }
    
    // Attach users to projects with roles (if UserRoleProject model exists)
    // This depends on your project-user relationship structure
    // Example:
    // \App\Models\UserRoleProject::create([
    //     'user_id' => $users['team_member']->id,
    //     'project_id' => $projects['active']->id,
    //     'role_id' => $roleId,
    // ]);
    
    return [
        'tenant' => $tenant,
        'users' => array_values($users),
        'projects' => array_values($projects),
        'components' => $components,
        'clients' => array_values($clients),
    ];
}
```

#### Key Points

- Use `mt_srand($seed)` at the start for reproducibility
- Use existing `TestDataSeeder` methods (`createTenant()`, `createUserWithRole()`) where possible
- Create projects with different statuses: active, planning, on_hold, completed, cancelled
- Create components for at least one project
- Create clients with different lifecycle stages
- Attach users to projects if your system uses project-user relationships
- Return structured array with all created entities

#### Location

Add this method to `tests/Helpers/TestDataSeeder.php` after the `seedAuthDomain()` method.

#### Verification

```bash
# Test the method directly (if you create a test)
php artisan test --filter seedProjectsDomain

# Or test via projects tests
php artisan test --group=projects --seed=23456
```

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/projects/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 23456,
  "domain": "projects",
  "tenants": [
    {
      "name": "Projects Test Tenant",
      "slug": "projects-test-tenant",
      "status": "active"
    }
  ],
  "users": [
    {
      "name": "Projects PM User",
      "email": "pm@projects-test.test",
      "role": "project_manager"
    },
    {
      "name": "Projects Team Member",
      "email": "member@projects-test.test",
      "role": "member"
    },
    {
      "name": "Projects Client Contact",
      "email": "client@projects-test.test",
      "role": "client"
    },
    {
      "name": "Projects Admin User",
      "email": "admin@projects-test.test",
      "role": "admin"
    }
  ],
  "clients": [
    {
      "name": "Active Test Client",
      "email": "active@client.test",
      "company": "Active Client Company",
      "lifecycle_stage": "customer"
    },
    {
      "name": "Prospect Test Client",
      "email": "prospect@client.test",
      "company": "Prospect Client Company",
      "lifecycle_stage": "prospect"
    }
  ],
  "projects": [
    {
      "code": "PROJ-001",
      "name": "Active Test Project",
      "status": "active",
      "priority": "high"
    },
    {
      "code": "PROJ-002",
      "name": "Planning Test Project",
      "status": "planning",
      "priority": "normal"
    },
    {
      "code": "PROJ-003",
      "name": "On Hold Test Project",
      "status": "on_hold",
      "priority": "low"
    }
  ],
  "components": [
    {
      "name": "Design",
      "type": "phase",
      "status": "active"
    },
    {
      "name": "Development",
      "type": "phase",
      "status": "pending"
    },
    {
      "name": "Testing",
      "type": "phase",
      "status": "pending"
    },
    {
      "name": "Deployment",
      "type": "phase",
      "status": "pending"
    }
  ]
}
```

#### File Location

Create directory if needed:
```bash
mkdir -p tests/fixtures/domains/projects
```

Then create `tests/fixtures/domains/projects/fixtures.json` with the content above.

---

### Phase 5: Playwright Projects (Optional)

**Goal:** Add Playwright project configuration for projects E2E tests.

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package.

#### If Needed

Add to `playwright.config.ts` (if file exists):

```typescript
{
  name: 'projects-e2e-chromium',
  testMatch: '**/E2E/projects/**/*.spec.ts',
  use: { ...devices['Desktop Chrome'] },
}
```

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running projects tests.

#### Scripts to Add

Add to `package.json` `scripts` section:

```json
{
  "scripts": {
    "test:projects": "php artisan test --group=projects",
    "test:projects:unit": "php artisan test --testsuite=projects-unit",
    "test:projects:feature": "php artisan test --testsuite=projects-feature",
    "test:projects:integration": "php artisan test --testsuite=projects-integration",
    "test:projects:e2e": "playwright test --project=projects-e2e-chromium"
  }
}
```

---

## Common Pitfalls

### 1. Forgetting Fixed Seed

**Problem:** Not using fixed seed `23456` consistently.

**Solution:** Always use `mt_srand(23456)` at the start of `seedProjectsDomain()`.

### 2. Missing @group Annotation

**Problem:** Forgetting to add `@group projects` to some test files.

**Solution:** Use the verification command after Phase 1:
```bash
grep -r "@group projects" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

### 3. Project Status Values

**Problem:** Using invalid project status values.

**Solution:** Use valid statuses: `active`, `planning`, `on_hold`, `completed`, `cancelled`, `archived`

### 4. Missing Required Fields

**Problem:** Project creation fails due to missing required fields (e.g., `code`).

**Solution:** Ensure all required fields are provided:
- `tenant_id` (required)
- `code` (required, unique)
- `name` (required)
- `status` (required)

### 5. Component-Project Relationship

**Problem:** Components not properly linked to projects.

**Solution:** Ensure `project_id` is set when creating components:
```php
$component = Component::create([
    'tenant_id' => $tenant->id,
    'project_id' => $project->id, // Required
    'name' => 'Component Name',
    // ...
]);
```

### 6. Test Isolation Issues

**Problem:** Tests interfering with each other.

**Solution:** Use `DomainTestIsolation` trait in test classes:
```php
use Tests\Traits\DomainTestIsolation;

class ProjectsFeatureTest extends TestCase
{
    use DomainTestIsolation;
    
    protected ?int $domainSeed = 23456;
    protected ?string $domainName = 'projects';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
    }
}
```

---

## Verification Steps

### Step 1: Verify Annotations

```bash
grep -r "@group projects" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

**Expected:** 31 files should appear.

### Step 2: Verify Test Suites

```bash
php artisan test --testsuite=projects-feature
php artisan test --testsuite=projects-unit
php artisan test --testsuite=projects-integration
```

**Expected:** All tests should run successfully.

### Step 3: Verify Reproducibility

```bash
# Run tests twice with same seed
php artisan test --group=projects --seed=23456 > /tmp/projects-test1.log
php artisan test --group=projects --seed=23456 > /tmp/projects-test2.log

# Compare results (should be identical)
diff /tmp/projects-test1.log /tmp/projects-test2.log
```

**Expected:** No differences (empty diff output).

### Step 4: Verify seedProjectsDomain Method

```bash
# Run projects tests to verify seeding works
php artisan test --group=projects --seed=23456
```

**Expected:** All tests pass with consistent data.

### Step 5: Verify NPM Scripts

```bash
npm run test:projects
npm run test:projects:unit
npm run test:projects:feature
npm run test:projects:integration
```

**Expected:** All scripts execute successfully.

---

## Troubleshooting

### Issue: Tests Not Grouped Correctly

**Symptoms:** `php artisan test --group=projects` doesn't run expected tests.

**Solutions:**
1. Verify `@group projects` annotations are present in all test files
2. Check PHPDoc format (must be `/**` not `/*`)
3. Verify test suites in `phpunit.xml` have correct `<group>projects</group>` filter

### Issue: seedProjectsDomain Method Not Found

**Symptoms:** `Call to undefined method TestDataSeeder::seedProjectsDomain()`

**Solutions:**
1. Verify method is added to `tests/Helpers/TestDataSeeder.php`
2. Check method signature matches template exactly
3. Clear any cached files: `php artisan clear-compiled`

### Issue: Project Creation Fails

**Symptoms:** Projects cannot be created in tests.

**Solutions:**
1. Ensure `code` field is unique and provided
2. Verify `tenant_id` is set
3. Check required fields are all provided
4. Verify project status is valid

### Issue: Component Creation Fails

**Symptoms:** Components cannot be created for projects.

**Solutions:**
1. Ensure `project_id` is set and valid
2. Verify `tenant_id` matches project's tenant
3. Check component type and status are valid

### Issue: Test Data Not Reproducible

**Symptoms:** Same seed produces different results.

**Solutions:**
1. Ensure `mt_srand($seed)` is called at the start of `seedProjectsDomain()`
2. Check that no other code is calling `mt_srand()` with different values
3. Verify test isolation is working (use `DomainTestIsolation` trait)

---

## Completion Checklist

Before marking work as complete, verify:

- [ ] All 31 files have `@group projects` annotation
- [ ] Test suites (`projects-unit`, `projects-feature`, `projects-integration`) run successfully
- [ ] `seedProjectsDomain()` method exists and works correctly
- [ ] Fixtures file created at `tests/fixtures/domains/projects/fixtures.json`
- [ ] NPM scripts added to `package.json` (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `23456`
- [ ] Documentation updated (if needed)

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/projects-domain.md`
- **File Audit:** `docs/work-packages/projects-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Future Agent (Builder)

