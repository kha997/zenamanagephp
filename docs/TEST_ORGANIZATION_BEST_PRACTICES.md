# Test Organization Best Practices

**Date:** 2025-11-08  
**Purpose:** Best practices guide for domain-based test organization  
**Status:** Ready for use

---

## Overview

This document outlines best practices for organizing and writing tests using the domain-based test organization structure. Following these practices ensures:

- **Reproducibility:** Tests produce consistent results
- **Isolation:** Tests don't interfere with each other
- **Maintainability:** Tests are easy to understand and modify
- **Performance:** Tests run efficiently
- **Reliability:** Tests are stable and don't flake

---

## Core Principles

### 1. Always Use Fixed Seeds

**Why:** Ensures test reproducibility and consistency.

**How:**
```php
protected ?int $domainSeed = 12345; // Fixed seed for auth domain

protected function setUp(): void
{
    parent::setUp();
    $this->setupDomainIsolation(); // Uses $this->domainSeed
}
```

**Don't:**
```php
mt_srand(time()); // ❌ Random seed - not reproducible
mt_srand(rand()); // ❌ Random seed - not reproducible
```

**Do:**
```php
protected ?int $domainSeed = 12345; // ✅ Fixed seed
```

---

### 2. Use DomainTestIsolation Trait

**Why:** Provides consistent test isolation and data management.

**How:**
```php
use Tests\Traits\DomainTestIsolation;

class AuthFeatureTest extends TestCase
{
    use DomainTestIsolation;
    
    protected ?int $domainSeed = 12345;
    protected ?string $domainName = 'auth';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
    }
}
```

**Benefits:**
- Automatic seed management
- Test data cleanup
- Domain tracking
- Reproducibility verification

---

### 3. Use TestDataSeeder for Test Data

**Why:** Centralizes test data creation and ensures consistency.

**How:**
```php
$data = TestDataSeeder::seedAuthDomain(12345);
$tenant = $data['tenant'];
$users = $data['users'];
```

**Don't:**
```php
$tenant = Tenant::factory()->create(); // ❌ Not using domain seed
$user = User::factory()->create(['tenant_id' => $tenant->id]); // ❌ Inconsistent
```

**Do:**
```php
$data = TestDataSeeder::seedAuthDomain($this->domainSeed); // ✅ Domain-specific
$tenant = $data['tenant'];
$user = $data['users'][0];
```

---

### 4. Store and Retrieve Test Data Consistently

**Why:** Makes test data accessible and traceable.

**How:**
```php
protected function setUp(): void
{
    parent::setUp();
    $this->setupDomainIsolation();
    
    $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
    $this->storeTestData('tenant', $data['tenant']);
    $this->storeTestData('users', $data['users']);
}

public function test_something()
{
    $tenant = $this->getTestData('tenant');
    $user = $this->getTestData('users')[0];
    
    // Use test data
}
```

**Benefits:**
- Clear data flow
- Easy debugging
- Consistent access patterns

---

### 5. Use Descriptive Test Data Keys

**Why:** Makes test code more readable and maintainable.

**How:**
```php
$this->storeTestData('tenant', $tenant);
$this->storeTestData('admin_user', $adminUser);
$this->storeTestData('member_users', $memberUsers);
$this->storeTestData('active_projects', $activeProjects);
```

**Don't:**
```php
$this->storeTestData('t', $tenant); // ❌ Too short
$this->storeTestData('data1', $user); // ❌ Not descriptive
```

**Do:**
```php
$this->storeTestData('tenant', $tenant); // ✅ Clear
$this->storeTestData('admin_user', $adminUser); // ✅ Descriptive
```

---

## Domain-Specific Best Practices

### Auth Domain

**Seed:** 12345

**Best Practices:**
- Create users with different roles (admin, member, client, project_manager)
- Create roles and permissions for RBAC testing
- Test tenant isolation explicitly
- Use `AuthHelper` for API authentication

**Example:**
```php
$data = TestDataSeeder::seedAuthDomain(12345);
$adminUser = collect($data['users'])->firstWhere('email', 'admin@auth-test.test');
$token = AuthHelper::getAuthToken($this, $adminUser->email, 'password');
```

---

### Projects Domain

**Seed:** 23456

**Best Practices:**
- Create projects with different statuses (active, planning, on_hold, completed)
- Create components for projects
- Create clients for project relationships
- Test project-user role assignments

**Example:**
```php
$data = TestDataSeeder::seedProjectsDomain(23456);
$activeProject = collect($data['projects'])->firstWhere('status', 'active');
$components = $data['components'];
```

---

### Tasks Domain

**Seed:** 34567

**Best Practices:**
- Create tasks with different statuses (backlog, in_progress, blocked, done)
- Create task assignments
- Create task dependencies
- Test task-project relationships

**Example:**
```php
$data = TestDataSeeder::seedTasksDomain(34567);
$inProgressTask = collect($data['tasks'])->firstWhere('status', 'in_progress');
$assignments = $data['task_assignments'];
```

---

### Documents Domain

**Seed:** 45678

**Best Practices:**
- Create documents with different visibility (internal, client)
- Create document versions for versioning tests
- Test file storage (use `Storage::fake()`)
- Test document-project relationships

**Example:**
```php
Storage::fake('documents');
$data = TestDataSeeder::seedDocumentsDomain(45678);
$versionedDocument = collect($data['documents'])->firstWhere('name', 'Versioned Test Document');
```

---

### Users Domain

**Seed:** 56789

**Best Practices:**
- Create users with different roles and statuses
- Set user preferences (theme, language, notifications)
- Set user profile data (first_name, last_name, job_title)
- Test user-role relationships

**Example:**
```php
$data = TestDataSeeder::seedUsersDomain(56789);
$adminUser = collect($data['users'])->firstWhere('email', 'admin@users-test.test');
$preferences = $adminUser->preferences; // ['theme' => 'dark', ...]
```

---

### Dashboard Domain

**Seed:** 67890

**Best Practices:**
- Create dashboard widgets with different types
- Create user dashboards with different layouts
- Create dashboard metrics and values
- Test dashboard caching (use `Cache::flush()`)

**Example:**
```php
Cache::flush(); // Clear cache before tests
$data = TestDataSeeder::seedDashboardDomain(67890);
$adminDashboard = collect($data['user_dashboards'])->firstWhere('name', 'Admin Dashboard');
```

---

## Test Writing Best Practices

### 1. One Assertion Per Test (When Possible)

**Why:** Makes failures easier to diagnose.

**How:**
```php
public function test_user_has_correct_role()
{
    $user = $this->getTestData('users')[0];
    $this->assertTrue($user->hasRole('admin'));
}

public function test_user_has_correct_permissions()
{
    $user = $this->getTestData('users')[0];
    $this->assertTrue($user->hasPermission('users.view'));
}
```

**Exception:** Related assertions can be grouped if they test the same behavior.

---

### 2. Use Descriptive Test Names

**Why:** Makes test purpose clear.

**How:**
```php
public function test_user_can_login_with_valid_credentials() // ✅ Clear
public function test_login_fails_with_invalid_password() // ✅ Clear
public function test_login() // ❌ Too vague
```

---

### 3. Test Edge Cases

**Why:** Ensures robustness.

**Examples:**
- Empty data sets
- Null values
- Boundary conditions
- Invalid inputs
- Missing relationships

---

### 4. Clean Up After Tests

**Why:** Prevents test interference.

**How:**
The `DomainTestIsolation` trait handles cleanup automatically. If you create additional test data, clean it up:

```php
protected function tearDown(): void
{
    // Clean up any additional test data
    // DomainTestIsolation handles standard cleanup
    parent::tearDown();
}
```

---

### 5. Avoid Test Interdependencies

**Why:** Tests should be independent.

**Don't:**
```php
public function test_create_user()
{
    $user = User::create([...]);
    $this->userId = $user->id; // ❌ Shared state
}

public function test_update_user()
{
    User::find($this->userId)->update([...]); // ❌ Depends on previous test
}
```

**Do:**
```php
public function test_create_user()
{
    $data = TestDataSeeder::seedUsersDomain(56789);
    $newUser = User::create([...]);
    $this->assertNotNull($newUser->id);
}

public function test_update_user()
{
    $data = TestDataSeeder::seedUsersDomain(56789);
    $user = $data['users'][0];
    $user->update([...]);
    // ✅ Independent test
}
```

---

## Performance Best Practices

### 1. Use RefreshDatabase Sparingly

**Why:** Database refreshes are slow.

**How:**
```php
use RefreshDatabase; // Only when needed

// For tests that modify database structure
use RefreshDatabase;

// For tests that only read data
// Don't use RefreshDatabase, use existing seeded data
```

---

### 2. Minimize Test Data Creation

**Why:** Less data = faster tests.

**How:**
```php
// Create only what you need
$data = TestDataSeeder::seedAuthDomain(12345);
$user = $data['users'][0]; // Use existing user

// Don't create unnecessary data
// $extraUser = User::create([...]); // ❌ Unnecessary
```

---

### 3. Use Database Transactions When Possible

**Why:** Faster than full database refresh.

**How:**
```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FastTest extends TestCase
{
    use DatabaseTransactions; // Faster than RefreshDatabase
}
```

---

## Reproducibility Best Practices

### 1. Always Use Fixed Seeds

**Why:** Ensures consistent test results.

**How:**
```php
protected ?int $domainSeed = 12345; // ✅ Fixed
```

---

### 2. Verify Reproducibility

**Why:** Catches non-deterministic behavior.

**How:**
```bash
# Run test twice with same seed
php artisan test --group=auth > /tmp/test1.log
php artisan test --group=auth > /tmp/test2.log
diff /tmp/test1.log /tmp/test2.log
# Should be empty
```

---

### 3. Document Seed Values

**Why:** Makes seed values discoverable.

**How:**
- Document in work packages
- Document in helper guides
- Use consistent values across documentation

---

## Common Pitfalls and Solutions

### Pitfall 1: Forgetting @group Annotation

**Problem:** Test not included in domain suite.

**Solution:** Always add `@group {domain}` to PHPDoc block.

---

### Pitfall 2: Using Random Seeds

**Problem:** Tests not reproducible.

**Solution:** Always use fixed seeds from domain configuration.

---

### Pitfall 3: Not Using DomainTestIsolation

**Problem:** Test isolation issues.

**Solution:** Always use `DomainTestIsolation` trait for domain tests.

---

### Pitfall 4: Creating Test Data in Test Methods

**Problem:** Inconsistent test data.

**Solution:** Use `TestDataSeeder::seed{Domain}Domain()` in `setUp()`.

---

### Pitfall 5: Hardcoding Test Data

**Problem:** Tests break when data changes.

**Solution:** Use seeded data from `TestDataSeeder`.

---

## Verification Checklist

Before marking a test as migrated, verify:

- [ ] `@group {domain}` annotation present
- [ ] `DomainTestIsolation` trait used
- [ ] `$domainSeed` and `$domainName` set correctly
- [ ] `setupDomainIsolation()` called in `setUp()`
- [ ] Test data created using `TestDataSeeder::seed{Domain}Domain()`
- [ ] Test data stored using `storeTestData()`
- [ ] Test data retrieved using `getTestData()`
- [ ] Test passes with domain-specific suite
- [ ] Test passes with group filter
- [ ] Test is reproducible (same seed = same results)

---

## Additional Resources

- **Test Groups Documentation:** [docs/TEST_GROUPS.md](docs/TEST_GROUPS.md)
- **Migration Guide:** [docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md](docs/TEST_ORGANIZATION_MIGRATION_GUIDE.md)
- **Domain Work Packages:** [docs/work-packages/](docs/work-packages/)
- **DomainTestIsolation Trait:** [tests/Traits/DomainTestIsolation.php](tests/Traits/DomainTestIsolation.php)
- **TestDataSeeder:** [tests/Helpers/TestDataSeeder.php](tests/Helpers/TestDataSeeder.php)

---

**Last Updated:** 2025-11-08  
**Created By:** Cursor Agent

