# Documents Domain Quick Start Guide

**For:** Future Agent (Builder)  
**Purpose:** One-page quick reference for Documents Domain test organization  
**Full Guide:** See `docs/work-packages/documents-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all documents tests with fixed seed
php artisan test --group=documents --seed=45678

# Run by test suite
php artisan test --testsuite=documents-feature
php artisan test --testsuite=documents-unit
php artisan test --testsuite=documents-integration

# Verify annotations
grep -r "@group documents" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

---

## File Checklist

### Add @group Annotations (11 files)
- [ ] All 9 Feature test files (see audit)
- [ ] All 1 Unit test file (see audit)
- [ ] All 1 Browser test file (see audit)

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedDocumentsDomain()` method
- [ ] `tests/fixtures/domains/documents/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `45678` for documents domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **Document Visibility:** internal, client
- **Required Fields:** Documents need `project_id`, `name`, `tenant_id`, `file_path`
- **Versions:** Use `DocumentVersion` model for document versioning
- **File Storage:** Ensure test storage is configured (use `Storage::fake()` if needed)

---

## Quick Example

### Using seedDocumentsDomain Method

```php
$data = TestDataSeeder::seedDocumentsDomain(45678);
$tenant = $data['tenant'];
$users = $data['users'];
$projects = $data['projects'];
$documents = $data['documents'];
$document_versions = $data['document_versions'];
```

---

## Resources

- **Main Work Package:** `docs/work-packages/documents-domain.md`
- **Helper Guide:** `docs/work-packages/documents-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/documents-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08

