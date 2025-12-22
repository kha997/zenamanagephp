# Test Organization Work Instructions

**For:** Continue Agent Test and other agents  
**Purpose:** Standardized instructions for organizing test suites by domain

## Getting Started

1. **Read the assignment file:** `docs/TEST_ORGANIZATION_ASSIGNMENT.md`
2. **Pick an unassigned package** (check status in `docs/TEST_ORGANIZATION_PROGRESS.md`)
3. **Read the package file** in `docs/work-packages/[domain]-domain.md`
4. **Create a branch:** `git checkout -b test-org/[domain]-domain`
5. **Update progress tracker** to mark package as "In Progress"

## Workflow

### Step 1: Add @group Annotations

For each test file in your domain:
```php
/**
 * @group auth  // or projects, tasks, etc.
 * @group feature  // or unit, integration
 */
class YourTest extends TestCase
{
    // ...
}
```

### Step 2: Create Test Suites

Add to `phpunit.xml:
```xml
<testsuite name="auth-feature">
    <directory>tests/Feature</directory>
    <group>auth</group>
</testsuite>
```

### Step 3: Extend TestDataSeeder

Add domain-specific seeding method:
```php
public static function seedAuthDomain(int $seed = 12345): array
{
    mt_srand($seed); // Fixed seed for reproducibility
    
    $tenant = self::createTenant(['name' => 'Auth Test Tenant']);
    $user = self::createUser($tenant, ['email' => 'auth-test@example.com']);
    
    // ... create other entities
    
    return [
        'tenant' => $tenant,
        'user' => $user,
        // ... other entities
    ];
}
```

### Step 4: Create Fixtures

Create JSON file at `tests/fixtures/domains/[domain]/fixtures.json`:
```json
{
  "version": "1.0",
  "seed": 12345,
  "data": {
    "tenants": [...],
    "users": [...]
  }
}
```

### Step 5: Add Playwright Project

Add to `playwright.config.ts`:
```typescript
{
  name: 'auth-e2e-chromium',
  testMatch: '**/E2E/auth/**/*.spec.ts',
  use: { ...devices['Desktop Chrome'] },
}
```

### Step 6: Add NPM Scripts

Add to `package.json`:
```json
"test:auth": "php artisan test --group=auth",
"test:auth:unit": "php artisan test --testsuite=auth-unit",
"test:auth:feature": "php artisan test --testsuite=auth-feature",
"test:auth:e2e": "playwright test --project=auth-e2e-chromium"
```

## Reproducibility Requirements

### Critical Rules:

1. **Always use fixed seeds** - Never use random seeds
2. **Use TestDataSeeder** - Don't create test data manually
3. **Test isolation** - Each test should clean up after itself
4. **Verify reproducibility** - Run tests twice with same seed, results must be identical

### Example Verification:

```bash
# First run
php artisan test --group=auth --seed=12345 > /tmp/test1.log

# Second run (should be identical)
php artisan test --group=auth --seed=12345 > /tmp/test2.log

# Compare (should output nothing)
diff /tmp/test1.log /tmp/test2.log
```

## Conflict Resolution

If you encounter conflicts:

1. **Check progress tracker** - See if another agent is working on same files
2. **Coordinate** - Update package status in progress tracker
3. **Use branches** - Each domain should have its own branch
4. **Merge order** - Core Infrastructure first, then domain packages

## Completion Checklist

Before marking package as complete:

- [ ] All @group annotations added
- [ ] Test suites created and tested
- [ ] TestDataSeeder method implemented with fixed seed
- [ ] Fixtures file created
- [ ] Playwright project added
- [ ] NPM scripts added and tested
- [ ] Reproducibility verified (same seed = same results)
- [ ] All verification commands pass
- [ ] Progress tracker updated
- [ ] Branch committed and ready for merge

## Troubleshooting

### Tests fail after adding annotations
- Check that test suites are correctly defined in phpunit.xml
- Verify @group annotations are correct

### Seed data not reproducible
- Ensure mt_srand() is called at start of seed method
- Check that no random data is created outside seed method
- Verify TestDataSeeder uses fixed seeds

### Playwright tests not found
- Check testMatch pattern in playwright.config.ts
- Verify test files are in correct directory

## Questions?

- Check `docs/TEST_GROUPS.md` (after Core Infrastructure is complete)
- Review `TEST_SUITE_SUMMARY.md` for patterns
- Update progress tracker with blockers

