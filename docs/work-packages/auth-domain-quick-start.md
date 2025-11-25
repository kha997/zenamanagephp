# Auth Domain Quick Start Guide

**For:** Continue Agent  
**Purpose:** One-page quick reference for Auth Domain test organization  
**Full Guide:** See `docs/work-packages/auth-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all auth tests with fixed seed
php artisan test --group=auth --seed=12345

# Run by test suite
php artisan test --testsuite=auth-feature
php artisan test --testsuite=auth-unit
php artisan test --testsuite=auth-integration

# Verify annotations
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/ tests/Feature/Buttons/ tests/Browser/

# Verify reproducibility (should produce identical results)
php artisan test --group=auth --seed=12345 > /tmp/auth-test1.log
php artisan test --group=auth --seed=12345 > /tmp/auth-test2.log
diff /tmp/auth-test1.log /tmp/auth-test2.log
```

---

## File Checklist

### Add @group Annotations (6 files)
- [ ] `tests/Feature/Auth/AuthenticationTest.php`
- [ ] `tests/Feature/Auth/AuthenticationModuleTest.php`
- [ ] `tests/Feature/AuthTest.php`
- [ ] `tests/Feature/Buttons/ButtonAuthenticationTest.php`
- [ ] `tests/Feature/Integration/SecurityIntegrationTest.php`
- [ ] `tests/Browser/AuthenticationTest.php`

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedAuthDomain()` method
- [ ] `tests/fixtures/domains/auth/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `12345` for auth domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **Test Suites:** Already configured in `phpunit.xml` (Core Infrastructure)
- **Reference:** See `docs/work-packages/auth-domain-audit.md` for file inventory

---

## Quick Example

### Adding @group Annotation

```php
/**
 * @group auth
 * Feature tests cho Authentication endpoints
 */
class AuthenticationTest extends TestCase
{
    // ...
}
```

### Using DomainTestIsolation Trait

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

### Using seedAuthDomain Method

```php
$data = TestDataSeeder::seedAuthDomain(12345);
$tenant = $data['tenant'];
$users = $data['users'];
$roles = $data['roles'];
$permissions = $data['permissions'];
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group auth" tests/Feature/Auth/ ...`
2. **Run test suites:** `php artisan test --testsuite=auth-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedAuthDomain:** `php artisan test --group=auth --seed=12345`

---

## Resources

- **Main Work Package:** `docs/work-packages/auth-domain.md`
- **Helper Guide:** `docs/work-packages/auth-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/auth-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08

