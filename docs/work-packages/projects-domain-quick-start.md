# Projects Domain Quick Start Guide

**For:** Future Agent (Builder)  
**Purpose:** One-page quick reference for Projects Domain test organization  
**Full Guide:** See `docs/work-packages/projects-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all projects tests with fixed seed
php artisan test --group=projects --seed=23456

# Run by test suite
php artisan test --testsuite=projects-feature
php artisan test --testsuite=projects-unit
php artisan test --testsuite=projects-integration

# Verify annotations
grep -r "@group projects" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/

# Verify reproducibility (should produce identical results)
php artisan test --group=projects --seed=23456 > /tmp/projects-test1.log
php artisan test --group=projects --seed=23456 > /tmp/projects-test2.log
diff /tmp/projects-test1.log /tmp/projects-test2.log
```

---

## File Checklist

### Add @group Annotations (31 files)
- [ ] All 22 Feature test files (see audit)
- [ ] All 7 Unit test files (see audit)
- [ ] All 2 Browser test files (see audit)

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedProjectsDomain()` method
- [ ] `tests/fixtures/domains/projects/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `23456` for projects domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **Test Suites:** Already configured in `phpunit.xml` (Core Infrastructure)
- **Reference:** See `docs/work-packages/projects-domain-audit.md` for file inventory
- **Required Fields:** Projects need `code` (unique), `name`, `tenant_id`, `status`

---

## Quick Example

### Adding @group Annotation

```php
/**
 * @group projects
 * Feature tests cho Project API endpoints
 */
class ProjectApiTest extends TestCase
{
    // ...
}
```

### Using DomainTestIsolation Trait

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

### Using seedProjectsDomain Method

```php
$data = TestDataSeeder::seedProjectsDomain(23456);
$tenant = $data['tenant'];
$users = $data['users'];
$projects = $data['projects'];
$components = $data['components'];
$clients = $data['clients'];
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group projects" tests/Feature/ tests/Unit/ ...`
2. **Run test suites:** `php artisan test --testsuite=projects-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedProjectsDomain:** `php artisan test --group=projects --seed=23456`

---

## Resources

- **Main Work Package:** `docs/work-packages/projects-domain.md`
- **Helper Guide:** `docs/work-packages/projects-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/projects-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08

