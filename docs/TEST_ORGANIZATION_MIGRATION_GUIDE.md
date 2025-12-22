# Test Organization Migration Guide

**Date:** 2025-11-08  
**Purpose:** Guide for migrating existing tests to the new domain-based organization structure  
**Status:** Ready for use

---

## Overview

This guide helps you migrate existing tests to the new domain-based organization structure. The migration involves:

1. Adding `@group` annotations to test classes
2. Using `DomainTestIsolation` trait for test isolation
3. Using domain-specific seed methods from `TestDataSeeder`
4. Running tests using domain-specific test suites

---

## Migration Checklist

### Before Migration

- [ ] Review domain assignments (see work packages in `docs/work-packages/`)
- [ ] Identify which domain each test belongs to
- [ ] Review existing test data setup methods
- [ ] Check for hardcoded test data that should use `TestDataSeeder`

### During Migration

- [ ] Add `@group {domain}` annotation to test class
- [ ] Add `DomainTestIsolation` trait to test class
- [ ] Set `$domainSeed` and `$domainName` properties
- [ ] Call `setupDomainIsolation()` in `setUp()` method
- [ ] Replace custom test data creation with `TestDataSeeder::seed{Domain}Domain()`
- [ ] Update test assertions to use seeded data
- [ ] Verify test still passes

### After Migration

- [ ] Run test with domain-specific suite: `php artisan test --testsuite={domain}-{type}`
- [ ] Run test with group filter: `php artisan test --group={domain}`
- [ ] Verify reproducibility: Run test twice with same seed, compare results
- [ ] Update test documentation if needed

---

## Migration Examples

### Example 1: Feature Test Migration

#### Before

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_get_all_projects()
    {
        // Test implementation
    }
}
```

#### After

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group projects
 * Project API Feature Tests
 */
class ProjectApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected ?int $domainSeed = 23456;
    protected ?string $domainName = 'projects';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        
        // Seed domain-specific test data
        $data = TestDataSeeder::seedProjectsDomain($this->domainSeed);
        $this->storeTestData('tenant', $data['tenant']);
        $this->storeTestData('users', $data['users']);
        $this->storeTestData('projects', $data['projects']);
    }

    public function test_can_get_all_projects()
    {
        $tenant = $this->getTestData('tenant');
        $user = $this->getTestData('users')[0];
        
        // Test implementation using seeded data
    }
}
```

### Example 2: Unit Test Migration

#### Before

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_task_belongs_to_project()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
        ]);
        
        $this->assertEquals($this->project->id, $task->project_id);
    }
}
```

#### After

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group tasks
 * Task Model Unit Tests
 */
class TaskTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected ?int $domainSeed = 34567;
    protected ?string $domainName = 'tasks';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        
        // Seed domain-specific test data
        $data = TestDataSeeder::seedTasksDomain($this->domainSeed);
        $this->storeTestData('tenant', $data['tenant']);
        $this->storeTestData('projects', $data['projects']);
        $this->storeTestData('tasks', $data['tasks']);
    }

    public function test_task_belongs_to_project()
    {
        $project = $this->getTestData('projects')[0];
        $task = $this->getTestData('tasks')[0];
        
        $this->assertEquals($project->id, $task->project_id);
    }
}
```

### Example 3: Integration Test Migration

#### Before

```php
<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_tenant_isolation()
    {
        // Test implementation
    }
}
```

#### After

```php
<?php declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group auth
 * Security Integration Tests
 */
class SecurityIntegrationTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected ?int $domainSeed = 12345;
    protected ?string $domainName = 'auth';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        
        // Seed domain-specific test data
        $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
        $this->storeTestData('tenant', $data['tenant']);
        $this->storeTestData('users', $data['users']);
    }

    public function test_tenant_isolation()
    {
        $tenant = $this->getTestData('tenant');
        $user = $this->getTestData('users')[0];
        
        // Test implementation using seeded data
    }
}
```

---

## Common Migration Patterns

### Pattern 1: Replacing Factory Calls

**Before:**
```php
$tenant = Tenant::factory()->create();
$user = User::factory()->create(['tenant_id' => $tenant->id]);
```

**After:**
```php
$data = TestDataSeeder::seedAuthDomain(12345);
$tenant = $data['tenant'];
$user = $data['users'][0];
```

### Pattern 2: Using Stored Test Data

**Before:**
```php
protected $user;
protected $tenant;

protected function setUp(): void
{
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
}
```

**After:**
```php
protected function setUp(): void
{
    parent::setUp();
    $this->setupDomainIsolation();
    
    $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
    $this->storeTestData('tenant', $data['tenant']);
    $this->storeTestData('users', $data['users']);
}

// In test methods:
$tenant = $this->getTestData('tenant');
$user = $this->getTestData('users')[0];
```

### Pattern 3: Multiple Test Data Objects

**Before:**
```php
$project1 = Project::factory()->create(['status' => 'active']);
$project2 = Project::factory()->create(['status' => 'planning']);
$project3 = Project::factory()->create(['status' => 'on_hold']);
```

**After:**
```php
$data = TestDataSeeder::seedProjectsDomain(23456);
$projects = $data['projects'];
$activeProject = $projects['active'];
$planningProject = $projects['planning'];
$onHoldProject = $projects['on_hold'];
```

---

## Domain Assignments

### Auth Domain (Seed: 12345)
- Authentication tests
- Authorization tests
- Password management tests
- Email verification tests
- Security integration tests

### Projects Domain (Seed: 23456)
- Project CRUD tests
- Project management tests
- Project API tests
- Project component tests
- Project workflow tests

### Tasks Domain (Seed: 34567)
- Task CRUD tests
- Task assignment tests
- Task dependency tests
- Task status workflow tests
- Task API tests

### Documents Domain (Seed: 45678)
- Document upload tests
- Document versioning tests
- Document sharing tests
- Document API tests
- Document policy tests

### Users Domain (Seed: 56789)
- User profile tests
- User account management tests
- User avatar tests
- User repository tests
- User policy tests

### Dashboard Domain (Seed: 67890)
- Dashboard API tests
- Dashboard widget tests
- Dashboard metric tests
- Dashboard analytics tests
- Dashboard service tests

---

## Verification Steps

### Step 1: Verify @group Annotation

```bash
grep -r "@group {domain}" tests/Feature/ tests/Unit/ tests/Integration/
```

Expected: All test files for the domain should appear.

### Step 2: Verify Test Suite Execution

```bash
php artisan test --testsuite={domain}-{type}
```

Expected: All tests in the suite should run.

### Step 3: Verify Group Filtering

```bash
php artisan test --group={domain}
```

Expected: All tests with `@group {domain}` should run.

### Step 4: Verify Reproducibility

```bash
# Run test twice with same seed
php artisan test --group={domain} > /tmp/test1.log
php artisan test --group={domain} > /tmp/test2.log
diff /tmp/test1.log /tmp/test2.log
```

Expected: No differences (empty diff output).

---

## Troubleshooting

### Issue: Test Not Included in Suite

**Problem:** Test doesn't run when using `--testsuite={domain}-{type}`

**Solutions:**
1. Verify `@group {domain}` annotation is present in PHPDoc block
2. Check annotation format: `/** @group {domain} */` (not `/* @group {domain} */`)
3. Verify test file is in correct directory (Feature, Unit, or Integration)

### Issue: Test Data Not Reproducible

**Problem:** Same seed produces different results

**Solutions:**
1. Ensure `mt_srand($seed)` is called at start of seed method
2. Check that no other code calls `mt_srand()` with different values
3. Verify test isolation is working (use `DomainTestIsolation` trait)

### Issue: Test Fails After Migration

**Problem:** Test passes before migration but fails after

**Solutions:**
1. Check that seeded data matches what test expects
2. Verify relationships are properly set up in seed method
3. Check that test uses `getTestData()` to access seeded data
4. Review test assertions - they may need updating for new data structure

### Issue: DomainTestIsolation Trait Conflict

**Problem:** Property conflict with trait

**Solutions:**
1. Don't define `$domainSeed` or `$domainName` as class properties
2. Set them in `setUp()` method instead:
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       $this->domainSeed = 12345;
       $this->domainName = 'auth';
       $this->setupDomainIsolation();
   }
   ```

---

## Best Practices

1. **Always use fixed seeds** - Don't use random seeds in domain tests
2. **Use DomainTestIsolation trait** - Ensures proper test isolation
3. **Store test data** - Use `storeTestData()` and `getTestData()` for consistency
4. **Verify reproducibility** - Run tests twice with same seed to verify
5. **Document domain assignments** - Update work packages if domain changes
6. **Keep seed methods simple** - Focus on creating essential test data
7. **Use descriptive test data keys** - Makes `getTestData()` calls clearer

---

## Migration Timeline

### Phase 1: Core Infrastructure ✅
- DomainTestIsolation trait created
- Test suites configured
- Aggregate script created
- CI workflow updated

### Phase 2: Domain Support Materials ✅
- Audit files created for all 6 domains
- Helper guides created
- Quick start guides created
- Seed method templates added

### Phase 3: Test Migration (Future)
- Add `@group` annotations to all test files
- Implement seed methods in TestDataSeeder
- Migrate tests to use DomainTestIsolation trait
- Verify all tests pass with new structure

---

## Additional Resources

- **Test Groups Documentation:** [docs/TEST_GROUPS.md](docs/TEST_GROUPS.md)
- **Domain Work Packages:** [docs/work-packages/](docs/work-packages/)
- **DomainTestIsolation Trait:** [tests/Traits/DomainTestIsolation.php](tests/Traits/DomainTestIsolation.php)
- **TestDataSeeder:** [tests/Helpers/TestDataSeeder.php](tests/Helpers/TestDataSeeder.php)
- **Infrastructure Validation:** [docs/INFRASTRUCTURE_VALIDATION_REPORT.md](docs/INFRASTRUCTURE_VALIDATION_REPORT.md)

---

**Last Updated:** 2025-11-08  
**Created By:** Cursor Agent

