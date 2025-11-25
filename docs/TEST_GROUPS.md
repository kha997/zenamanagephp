# Test Groups Documentation

**Last Updated:** 2025-11-08  
**Purpose:** Documentation for test organization by domain and test type

## Overview

Tests are organized into **domains** (auth, projects, tasks, documents, users, dashboard) and **test types** (unit, feature, integration, e2e). This organization enables:

- **Parallel execution** of tests by domain
- **Reproducible test data** using fixed seeds
- **Isolated test runs** for specific domains
- **Better CI/CD performance** with matrix strategies

## Test Organization Structure

### Domains

Each domain represents a functional area of the application:

| Domain | Seed | Description |
|--------|------|-------------|
| `auth` | 12345 | Authentication, authorization, user sessions |
| `projects` | 23456 | Project management, project CRUD operations |
| `tasks` | 34567 | Task management, task assignments, task workflows |
| `documents` | 45678 | Document management, file uploads, document sharing |
| `users` | 56789 | User management, user profiles, user settings |
| `dashboard` | 67890 | Dashboard widgets, metrics, KPI displays |

### Test Types

| Type | Location | Purpose |
|------|----------|---------|
| `unit` | `tests/Unit/` | Fast, isolated unit tests |
| `feature` | `tests/Feature/` | Feature-level integration tests |
| `integration` | `tests/Integration/` | System integration tests |
| `e2e` | `tests/e2e/` | End-to-end tests with Playwright |

## Using Test Groups

### PHPUnit Groups

Add `@group` annotation to test classes or methods:

```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * @group auth
 */
class AuthenticationTest extends TestCase
{
    /**
     * @group auth
     */
    public function test_user_can_login(): void
    {
        // Test implementation
    }
}
```

### Running Tests by Group

```bash
# Run all auth tests
php artisan test --group=auth

# Run auth tests with fixed seed
php artisan test --group=auth --seed=12345

# Run specific test suite
php artisan test --testsuite=auth-feature

# Run multiple groups
php artisan test --group=auth,projects
```

### Test Suites

Test suites are defined in `phpunit.xml`:

```xml
<testsuite name="auth-feature">
    <directory>tests/Feature</directory>
    <group>auth</group>
</testsuite>
```

Available test suites:
- `auth-unit`, `auth-feature`, `auth-integration`
- `projects-unit`, `projects-feature`, `projects-integration`
- `tasks-unit`, `tasks-feature`, `tasks-integration`
- `documents-unit`, `documents-feature`, `documents-integration`
- `users-unit`, `users-feature`, `users-integration`
- `dashboard-unit`, `dashboard-feature`, `dashboard-integration`

## Test Data Seeding

### Fixed Seeds

Each domain uses a **fixed seed** for reproducibility:

```php
use Tests\Helpers\TestDataSeeder;

// Auth domain uses seed 12345
$data = TestDataSeeder::seedAuthDomain(12345);

// Projects domain uses seed 23456
$data = TestDataSeeder::seedProjectsDomain(23456);
```

### Domain-Specific Seed Methods

Each domain has a dedicated seed method in `TestDataSeeder`:

```php
public static function seedAuthDomain(int $seed = 12345): array
{
    mt_srand($seed);
    // Create tenants, users, roles, permissions
    return [
        'tenant' => $tenant,
        'users' => $users,
        'roles' => $roles,
    ];
}
```

## Test Isolation

### DomainTestIsolation Trait

Use the `DomainTestIsolation` trait for test isolation:

```php
use Tests\Traits\DomainTestIsolation;

class AuthFeatureTest extends TestCase
{
    use DomainTestIsolation;

    protected int $domainSeed = 12345;
    protected string $domainName = 'auth';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
    }
}
```

### Benefits

- **Reproducible tests**: Same seed = same test data
- **Isolated runs**: Tests don't interfere with each other
- **Parallel execution**: Tests can run in parallel safely
- **Easy debugging**: Fixed seeds make failures reproducible

## CI/CD Integration

### Matrix Strategy

The CI workflow uses a matrix strategy to run tests by domain and type:

```yaml
strategy:
  matrix:
    domain: [auth, projects, tasks, documents, users, dashboard]
    type: [unit, feature, integration]
```

This creates **18 parallel jobs** (6 domains × 3 types).

### Test Results Aggregation

Use the aggregation script to combine results:

```bash
# Aggregate all results
./scripts/aggregate-test-results.sh

# Aggregate by domain
./scripts/aggregate-test-results.sh --domain auth

# Aggregate by type
./scripts/aggregate-test-results.sh --type unit

# Custom output
./scripts/aggregate-test-results.sh --domain projects --type feature --output results.json --format json
```

## NPM Scripts

Domain-specific NPM scripts (to be added):

```json
{
  "scripts": {
    "test:auth": "php artisan test --group=auth",
    "test:auth:unit": "php artisan test --testsuite=auth-unit",
    "test:auth:feature": "php artisan test --testsuite=auth-feature",
    "test:auth:integration": "php artisan test --testsuite=auth-integration",
    "test:auth:e2e": "npx playwright test --project=auth-e2e-chromium"
  }
}
```

## Best Practices

### 1. Always Use Fixed Seeds

```php
// ✅ Good
$data = TestDataSeeder::seedAuthDomain(12345);

// ❌ Bad
$data = TestDataSeeder::seedAuthDomain(rand());
```

### 2. Use DomainTestIsolation Trait

```php
// ✅ Good
use DomainTestIsolation;

// ❌ Bad
// Manual setup without isolation
```

### 3. Group Related Tests

```php
// ✅ Good
/**
 * @group auth
 */
class AuthenticationTest extends TestCase
{
}

// ❌ Bad
// No group annotation
```

### 4. Verify Reproducibility

```bash
# Run test twice with same seed - should produce identical results
php artisan test --group=auth --seed=12345 > /tmp/test1.log
php artisan test --group=auth --seed=12345 > /tmp/test2.log
diff /tmp/test1.log /tmp/test2.log  # Should be empty
```

## Troubleshooting

### Tests Not Found

If tests aren't found when using `--group`:

1. Check that `@group` annotation is present
2. Verify group name matches exactly (case-sensitive)
3. Check `phpunit.xml` for correct test suite configuration

### Seed Not Working

If test data isn't reproducible:

1. Verify seed is set correctly: `mt_srand($seed)`
2. Check that `TestDataSeeder` uses the seed
3. Ensure no random data generation outside of seeded functions

### CI Matrix Failures

If matrix jobs fail:

1. Check individual domain test results
2. Verify seed values are correct
3. Check for test isolation issues
4. Review aggregation script output

## Examples

### Complete Example: Auth Domain Test

Here's a complete example of using DomainTestIsolation trait:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;

/**
 * Authentication Feature Tests
 * 
 * @group auth
 */
class AuthenticationTest extends TestCase
{
    use DomainTestIsolation;

    protected int $domainSeed = 12345;
    protected string $domainName = 'auth';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        
        // Seed domain-specific test data
        $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
        $this->storeTestData('tenant', $data['tenant']);
        $this->storeTestData('users', $data['users']);
    }

    public function test_user_can_login(): void
    {
        $user = $this->getTestData('users')[0];
        
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_seed_reproducibility(): void
    {
        // Verify seed was set correctly
        $this->assertTestDataSeed(12345);
        $this->assertTestDataDomain('auth');
    }
}
```

### Running Domain Tests

```bash
# Run all auth tests
php artisan test --group=auth

# Run auth feature tests only
php artisan test --testsuite=auth-feature

# Run with fixed seed for reproducibility
php artisan test --group=auth --seed=12345

# Verify reproducibility (should produce identical results)
php artisan test --group=auth --seed=12345 > /tmp/test1.log
php artisan test --group=auth --seed=12345 > /tmp/test2.log
diff /tmp/test1.log /tmp/test2.log  # Should be empty
```

### CI/CD Integration Example

The CI workflow automatically runs domain tests in parallel:

```yaml
# .github/workflows/ci.yml
domain-tests:
  strategy:
    matrix:
      domain: [auth, projects, tasks, documents, users, dashboard]
      type: [unit, feature, integration]
  steps:
    - name: Run domain tests
      run: |
        php artisan test --testsuite=${{ matrix.domain }}-${{ matrix.type }} \
          --group=${{ matrix.domain }} \
          --seed=${{ matrix.seed }}
    
    - name: Aggregate results
      run: |
        ./scripts/aggregate-test-results.sh \
          --domain ${{ matrix.domain }} \
          --type ${{ matrix.type }} \
          --output results.json
```

### Troubleshooting Example

**Problem:** Tests not found when using `--group=auth`

**Solution:**
```bash
# 1. Check if @group annotation exists
grep -r "@group auth" tests/Feature/Auth/

# 2. Verify test suite configuration
grep -A 5 "auth-feature" phpunit.xml

# 3. Run with verbose output
php artisan test --group=auth -v
```

**Problem:** Test data not reproducible

**Solution:**
```php
// Ensure seed is set before any random operations
protected function setUp(): void
{
    parent::setUp();
    $this->setupDomainIsolation(12345); // Set seed first
    
    // Now create test data (will use seed 12345)
    $data = TestDataSeeder::seedAuthDomain(12345);
}
```

## Migration Guide

### Adding a New Domain

1. Add domain to `phpunit.xml` test suites
2. Add seed method to `TestDataSeeder`
3. Add `@group` annotations to tests
4. Update CI workflow matrix
5. Add NPM scripts
6. Update this documentation

### Migrating Existing Tests

1. Add `@group` annotation to test class
2. Use `DomainTestIsolation` trait
3. Update `setUp()` to use `setupDomainIsolation()`
4. Use domain-specific seed method
5. Verify test still passes

**Example Migration:**

```php
// Before
class OldAuthTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }
}

// After
/**
 * @group auth
 */
class NewAuthTest extends TestCase
{
    use DomainTestIsolation;
    
    protected int $domainSeed = 12345;
    protected string $domainName = 'auth';
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDomainIsolation();
        $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
        $this->user = $data['users'][0];
    }
}
```

## References

- `phpunit.xml` - Test suite configuration
- `tests/Traits/DomainTestIsolation.php` - Isolation trait
- `tests/Helpers/TestDataSeeder.php` - Test data seeding
- `scripts/aggregate-test-results.sh` - Results aggregation
- `.github/workflows/ci.yml` - CI/CD configuration
- `tests/Unit/Traits/DomainTestIsolationTest.php` - Trait validation tests
