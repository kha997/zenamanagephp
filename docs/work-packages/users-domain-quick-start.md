# Users Domain Quick Start Guide

**For:** Future Agent (Builder)  
**Purpose:** One-page quick reference for Users Domain test organization  
**Full Guide:** See `docs/work-packages/users-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all users tests with fixed seed
php artisan test --group=users --seed=56789

# Run by test suite
php artisan test --testsuite=users-feature
php artisan test --testsuite=users-unit
php artisan test --testsuite=users-integration

# Verify annotations
grep -r "@group users" tests/Feature/ tests/Unit/ tests/Integration/ tests/e2e/
```

---

## File Checklist

### Add @group Annotations (9 files)
- [ ] All 5 Feature test files (see audit)
- [ ] All 3 Unit test files (see audit)
- [ ] All 1 E2E test file (see audit)

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedUsersDomain()` method
- [ ] `tests/fixtures/domains/users/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `56789` for users domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **User Roles:** admin, project_manager, member, client
- **User Status:** is_active (boolean: true/false)
- **Required Fields:** Users need `tenant_id`, `name`, `email`, `password`
- **Preferences:** Store as JSON array (theme, language, notifications)
- **Avatar Uploads:** Use `Storage::fake('avatars')` in tests

---

## Quick Example

### Using seedUsersDomain Method

```php
$data = TestDataSeeder::seedUsersDomain(56789);
$tenant = $data['tenant'];
$users = $data['users'];
$roles = $data['roles'];
$permissions = $data['permissions'];
```

---

## Resources

- **Main Work Package:** `docs/work-packages/users-domain.md`
- **Helper Guide:** `docs/work-packages/users-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/users-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08
