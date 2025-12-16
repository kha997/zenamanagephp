# Users Domain Helper Guide

**For:** Future Agent (Builder)  
**Purpose:** Comprehensive implementation guide for Users Domain test organization  
**Reference:** `docs/work-packages/users-domain.md` (main work package)  
**Audit:** `docs/work-packages/users-domain-audit.md` (file inventory)

---

## Overview

This guide will help you implement the Users Domain test organization work package. The goal is to:

1. Add `@group users` annotations to all users-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedUsersDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `56789` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `users-unit`, `users-feature`, `users-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/users-domain-audit.md` for file inventory

---

## File Inventory

### Files to Add @group Annotations (9 files)

**Feature Tests (5 files):**
1. `tests/Feature/Users/ProfileManagementTest.php`
2. `tests/Feature/Users/AccountManagementTest.php`
3. `tests/Feature/Users/AvatarManagementTest.php`
4. `tests/Feature/UserManagementSimpleTest.php`
5. `tests/Feature/UserManagementAuthenticationTest.php`

**Unit Tests (3 files):**
1. `tests/Unit/Models/UserTest.php`
2. `tests/Unit/Repositories/UserRepositoryTest.php`
3. `tests/Unit/Policies/UserPolicyTest.php`

**E2E Tests (1 file):**
1. `tests/e2e/CriticalUserFlowsE2ETest.php`

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group users` annotation to all users test files.

#### Example: Adding Annotation

**Before:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature\Users;

use Tests\TestCase;

/**
 * Profile Management Test
 * Tests for user profile management functionality
 */
class ProfileManagementTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature\Users;

use Tests\TestCase;

/**
 * @group users
 * Profile Management Test
 * Tests for user profile management functionality
 */
class ProfileManagementTest extends TestCase
{
    // ...
}
```

#### Verification

After adding annotations, verify:
```bash
grep -r "@group users" tests/Feature/ tests/Unit/ tests/Integration/ tests/e2e/
```

Expected: All 9 test files should appear.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Verification Commands

```bash
php artisan test --testsuite=users-unit
php artisan test --testsuite=users-feature
php artisan test --testsuite=users-integration
php artisan test --group=users --seed=56789
```

---

### Phase 3: Implement seedUsersDomain Method

**Goal:** Add `seedUsersDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed users domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete users domain test setup including:
 * - Tenant
 * - Users with different roles and statuses
 * - User profiles with different data
 * - User preferences
 * - User roles and permissions
 * 
 * @param int $seed Fixed seed value (default: 56789)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     roles: \App\Models\Role[],
 *     permissions: \App\Models\Permission[]
 * }
 */
public static function seedUsersDomain(int $seed = 56789): array
```

#### Implementation Template

```php
public static function seedUsersDomain(int $seed = 56789): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Users Test Tenant',
        'slug' => 'users-test-tenant',
        'status' => 'active',
    ]);
    
    // Create roles
    $roles = [];
    $roleNames = ['admin', 'project_manager', 'member', 'client'];
    
    foreach ($roleNames as $roleName) {
        $roles[$roleName] = \App\Models\Role::create([
            'name' => $roleName,
            'scope' => 'system',
            'allow_override' => false,
            'description' => "Users test role: {$roleName}",
            'is_active' => true,
            'tenant_id' => $tenant->id,
        ]);
    }
    
    // Create permissions (user-related)
    $permissions = [];
    $permissionData = [
        ['module' => 'users', 'action' => 'view', 'description' => 'Can view users'],
        ['module' => 'users', 'action' => 'create', 'description' => 'Can create users'],
        ['module' => 'users', 'action' => 'update', 'description' => 'Can update users'],
        ['module' => 'users', 'action' => 'delete', 'description' => 'Can delete users'],
        ['module' => 'users', 'action' => 'manage_profile', 'description' => 'Can manage own profile'],
        ['module' => 'users', 'action' => 'manage_avatar', 'description' => 'Can manage avatar'],
    ];
    
    foreach ($permissionData as $permData) {
        $permissions[] = \App\Models\Permission::create([
            'code' => \App\Models\Permission::generateCode($permData['module'], $permData['action']),
            'module' => $permData['module'],
            'action' => $permData['action'],
            'description' => $permData['description'],
        ]);
    }
    
    // Attach permissions to roles
    // Admin gets all permissions
    $roles['admin']->permissions()->attach(
        collect($permissions)->pluck('id')->toArray()
    );
    
    // Project manager gets view, update, manage_profile
    $pmPerms = collect($permissions)
        ->whereIn('action', ['view', 'update', 'manage_profile'])
        ->pluck('id')
        ->toArray();
    $roles['project_manager']->permissions()->attach($pmPerms);
    
    // Member gets manage_profile, manage_avatar
    $memberPerms = collect($permissions)
        ->whereIn('action', ['manage_profile', 'manage_avatar'])
        ->pluck('id')
        ->toArray();
    $roles['member']->permissions()->attach($memberPerms);
    
    // Create users with different roles and statuses
    $users = [];
    
    // Admin user
    $users['admin'] = self::createUserWithRole('admin', $tenant, [
        'name' => 'Users Admin User',
        'email' => 'admin@users-test.test',
        'password' => 'password',
        'is_active' => true,
        'preferences' => [
            'theme' => 'dark',
            'language' => 'en',
            'notifications' => true,
        ],
        'first_name' => 'Admin',
        'last_name' => 'User',
        'job_title' => 'System Administrator',
        'department' => 'IT',
    ]);
    $users['admin']->roles()->attach($roles['admin']->id);
    
    // Project manager user
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Users PM User',
        'email' => 'pm@users-test.test',
        'password' => 'password',
        'is_active' => true,
        'preferences' => [
            'theme' => 'light',
            'language' => 'en',
            'notifications' => true,
        ],
        'first_name' => 'Project',
        'last_name' => 'Manager',
        'job_title' => 'Project Manager',
        'department' => 'Project Management',
    ]);
    $users['project_manager']->roles()->attach($roles['project_manager']->id);
    
    // Member user
    $users['member'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Users Member User',
        'email' => 'member@users-test.test',
        'password' => 'password',
        'is_active' => true,
        'preferences' => [
            'theme' => 'auto',
            'language' => 'en',
            'notifications' => false,
        ],
        'first_name' => 'Member',
        'last_name' => 'User',
        'job_title' => 'Team Member',
        'department' => 'Development',
    ]);
    $users['member']->roles()->attach($roles['member']->id);
    
    // Inactive user
    $users['inactive'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Users Inactive User',
        'email' => 'inactive@users-test.test',
        'password' => 'password',
        'is_active' => false,
        'preferences' => [],
    ]);
    $users['inactive']->roles()->attach($roles['member']->id);
    
    // Client user
    $users['client'] = self::createUserWithRole('client', $tenant, [
        'name' => 'Users Client User',
        'email' => 'client@users-test.test',
        'password' => 'password',
        'is_active' => true,
        'preferences' => [
            'theme' => 'light',
            'language' => 'en',
        ],
        'first_name' => 'Client',
        'last_name' => 'User',
    ]);
    $users['client']->roles()->attach($roles['client']->id);
    
    return [
        'tenant' => $tenant,
        'users' => array_values($users),
        'roles' => array_values($roles),
        'permissions' => $permissions,
    ];
}
```

#### Key Points

- Use `mt_srand($seed)` at the start for reproducibility
- Create users with different roles: admin, project_manager, member, client
- Create users with different statuses: active, inactive
- Set user preferences (theme, language, notifications)
- Set user profile data (first_name, last_name, job_title, department)
- Attach roles and permissions to users
- Return structured array with all created entities

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/users/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 56789,
  "domain": "users",
  "user_roles": ["admin", "project_manager", "member", "client"],
  "user_statuses": ["active", "inactive"],
  "users": [
    {
      "name": "Users Admin User",
      "email": "admin@users-test.test",
      "role": "admin",
      "status": "active"
    },
    {
      "name": "Users PM User",
      "email": "pm@users-test.test",
      "role": "project_manager",
      "status": "active"
    },
    {
      "name": "Users Member User",
      "email": "member@users-test.test",
      "role": "member",
      "status": "active"
    },
    {
      "name": "Users Inactive User",
      "email": "inactive@users-test.test",
      "role": "member",
      "status": "inactive"
    },
    {
      "name": "Users Client User",
      "email": "client@users-test.test",
      "role": "client",
      "status": "active"
    }
  ],
  "preferences": {
    "themes": ["dark", "light", "auto"],
    "languages": ["en", "vi"],
    "notification_settings": ["enabled", "disabled"]
  }
}
```

---

### Phase 5: Playwright Projects (Optional)

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package.

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running users tests.

#### Scripts to Add

```json
{
  "scripts": {
    "test:users": "php artisan test --group=users",
    "test:users:unit": "php artisan test --testsuite=users-unit",
    "test:users:feature": "php artisan test --testsuite=users-feature",
    "test:users:integration": "php artisan test --testsuite=users-integration",
    "test:users:e2e": "playwright test --project=users-e2e-chromium"
  }
}
```

---

## Common Pitfalls

### 1. User Status Values

**Problem:** Using invalid user status values.

**Solution:** Use valid status: `is_active` (boolean: true/false)

### 2. User Preferences

**Problem:** User preferences not properly formatted.

**Solution:** Ensure preferences are stored as JSON array:
```php
'preferences' => [
    'theme' => 'dark',
    'language' => 'en',
    'notifications' => true,
]
```

### 3. User Roles

**Problem:** Roles not properly attached to users.

**Solution:** Use the roles relationship:
```php
$user->roles()->attach($roleId);
```

### 4. Avatar Uploads

**Problem:** Avatar uploads may require file storage configuration.

**Solution:** Use mock storage in tests:
```php
Storage::fake('avatars');
```

### 5. Tenant Isolation

**Problem:** Users must belong to a tenant.

**Solution:** Always set `tenant_id` when creating users:
```php
$user = User::create([
    'tenant_id' => $tenant->id, // Required
    'name' => 'User Name',
    // ...
]);
```

---

## Verification Steps

1. **Check annotations:** `grep -r "@group users" tests/Feature/ tests/Unit/ ...`
2. **Run test suites:** `php artisan test --testsuite=users-feature`
3. **Verify reproducibility:** Run same seed twice, compare results
4. **Test seedUsersDomain:** `php artisan test --group=users --seed=56789`

---

## Completion Checklist

- [ ] All 9 files have `@group users` annotation
- [ ] Test suites run successfully
- [ ] `seedUsersDomain()` method exists and works correctly
- [ ] Fixtures file created
- [ ] NPM scripts added (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `56789`

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/users-domain.md`
- **File Audit:** `docs/work-packages/users-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Future Agent (Builder)
