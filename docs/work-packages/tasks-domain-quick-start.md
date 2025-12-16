# Tasks Domain Quick Start Guide

**For:** Future Agent (Builder)  
**Purpose:** One-page quick reference for Tasks Domain test organization  
**Full Guide:** See `docs/work-packages/tasks-domain-helper-guide.md`

---

## Essential Commands

```bash
# Run all tasks tests with fixed seed
php artisan test --group=tasks --seed=34567

# Run by test suite
php artisan test --testsuite=tasks-feature
php artisan test --testsuite=tasks-unit
php artisan test --testsuite=tasks-integration

# Verify annotations
grep -r "@group tasks" tests/Feature/ tests/Unit/ tests/Integration/ tests/Browser/
```

---

## File Checklist

### Add @group Annotations (19 files)
- [ ] All 12 Feature test files (see audit)
- [ ] All 4 Unit test files (see audit)
- [ ] All 3 Browser test files (see audit)

### Modify Files
- [ ] `tests/Helpers/TestDataSeeder.php` - Implement `seedTasksDomain()` method
- [ ] `tests/fixtures/domains/tasks/fixtures.json` - Create fixtures file
- [ ] `package.json` - Add NPM scripts (if applicable)

---

## Key Reminders

- **Fixed Seed:** Always use `34567` for tasks domain
- **Trait Usage:** Use `DomainTestIsolation` trait in test classes
- **Task Statuses:** backlog, in_progress, blocked, done, canceled
- **Required Fields:** Tasks need `project_id`, `name`, `tenant_id`, `status`
- **Dependencies:** Use `TaskDependency` model for task dependencies
- **Assignments:** Use `TaskAssignment` model for user-task assignments

---

## Quick Example

### Using seedTasksDomain Method

```php
$data = TestDataSeeder::seedTasksDomain(34567);
$tenant = $data['tenant'];
$users = $data['users'];
$projects = $data['projects'];
$tasks = $data['tasks'];
$task_assignments = $data['task_assignments'];
$task_dependencies = $data['task_dependencies'];
```

---

## Resources

- **Main Work Package:** `docs/work-packages/tasks-domain.md`
- **Helper Guide:** `docs/work-packages/tasks-domain-helper-guide.md`
- **File Audit:** `docs/work-packages/tasks-domain-audit.md`
- **Test Groups Docs:** `docs/TEST_GROUPS.md`

---

**Last Updated:** 2025-11-08

