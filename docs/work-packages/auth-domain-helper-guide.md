# Auth Domain Helper Guide

**For:** Continue Agent  
**Purpose:** Comprehensive implementation guide for Auth Domain test organization  
**Reference:** `docs/work-packages/auth-domain.md` (main work package)  
**Audit:** `docs/work-packages/auth-domain-audit.md` (file inventory)

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [File Inventory](#file-inventory)
4. [Step-by-Step Implementation](#step-by-step-implementation)
5. [Common Pitfalls](#common-pitfalls)
6. [Verification Steps](#verification-steps)
7. [Troubleshooting](#troubleshooting)

---

## Overview

This guide will help you implement the Auth Domain test organization work package. The goal is to:

1. Add `@group auth` annotations to all auth-related test files
2. Verify test suites are working (already done in Core Infrastructure)
3. Implement `seedAuthDomain()` method in `TestDataSeeder`
4. Create fixtures file structure
5. Add Playwright projects (if applicable)
6. Add NPM scripts

**Fixed Seed:** `12345` (must be used consistently for reproducibility)

---

## Prerequisites

Before starting, ensure:

- [ ] Core Infrastructure work is complete and reviewed by Codex
- [ ] `phpunit.xml` contains `auth-unit`, `auth-feature`, `auth-integration` test suites
- [ ] `DomainTestIsolation` trait is available in `tests/Traits/DomainTestIsolation.php`
- [ ] `TestDataSeeder` class exists and is accessible
- [ ] You have read `docs/work-packages/auth-domain-audit.md` for file inventory

**Check Core Infrastructure Status:**
```bash
# Verify test suites exist
grep -A 3 "auth-unit\|auth-feature\|auth-integration" phpunit.xml

# Verify trait exists
ls -la tests/Traits/DomainTestIsolation.php
```

---

## File Inventory

### Files to Add @group Annotations (6 files)

Based on `auth-domain-audit.md`, these files need `@group auth`:

1. `tests/Feature/Auth/AuthenticationTest.php` - Missing annotation
2. `tests/Feature/Auth/AuthenticationModuleTest.php` - Missing annotation
3. `tests/Feature/AuthTest.php` - Missing annotation
4. `tests/Feature/Buttons/ButtonAuthenticationTest.php` - Missing annotation
5. `tests/Feature/Integration/SecurityIntegrationTest.php` - Missing annotation
6. `tests/Browser/AuthenticationTest.php` - Missing annotation

### Files Already Annotated (3 files)

These already have `@group auth` - no action needed:

- `tests/Feature/Auth/PasswordChangeTest.php` ✅
- `tests/Feature/Auth/EmailVerificationTest.php` ✅
- `tests/Unit/AuthServiceTest.php` ✅
- `tests/Integration/SecurityIntegrationTest.php` ✅

### Files to Modify

1. `tests/Helpers/TestDataSeeder.php` - Add `seedAuthDomain()` method
2. `tests/fixtures/domains/auth/fixtures.json` - Create new file
3. `playwright.config.ts` - Add auth project (if exists)
4. `package.json` - Add NPM scripts

---

## Step-by-Step Implementation

### Phase 1: Add @group Annotations

**Goal:** Add `@group auth` annotation to all auth test files.

#### Example: Adding Annotation

**Before:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Feature tests cho Authentication endpoints
 */
class AuthenticationTest extends TestCase
{
    // ...
}
```

**After:**
```php
<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * @group auth
 * Feature tests cho Authentication endpoints
 */
class AuthenticationTest extends TestCase
{
    // ...
}
```

#### Files to Update

1. **`tests/Feature/Auth/AuthenticationTest.php`**
   - Add `@group auth` to PHPDoc block (around line 11-13)
   - Format: `/**\n * @group auth\n * Feature tests cho Authentication endpoints\n */`

2. **`tests/Feature/Auth/AuthenticationModuleTest.php`**
   - Add `@group auth` to PHPDoc block (around line 13-18)
   - Format: `/**\n * @group auth\n * Authentication Module Test\n * ...\n */`

3. **`tests/Feature/AuthTest.php`**
   - Add `@group auth` to PHPDoc block (around line 11-13)
   - Format: `/**\n * @group auth\n * Feature tests for Authentication endpoints\n */`

4. **`tests/Feature/Buttons/ButtonAuthenticationTest.php`**
   - Add `@group auth` to PHPDoc block (around line 14-18)
   - Format: `/**\n * @group auth\n * Button Authentication Test\n * ...\n */`

5. **`tests/Feature/Integration/SecurityIntegrationTest.php`**
   - Add `@group auth` to PHPDoc block (around line 14)
   - Format: `/**\n * @group auth\n */`

6. **`tests/Browser/AuthenticationTest.php`**
   - Add `@group auth` to PHPDoc block (around line 11-13)
   - Format: `/**\n * @group auth\n * Browser testing cho Authentication\n */`

#### Verification

After adding annotations, verify:
```bash
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/ tests/Feature/Buttons/ tests/Browser/
```

Expected: All 9 files should appear in the output.

---

### Phase 2: Verify Test Suites

**Goal:** Ensure test suites are working (already configured in Core Infrastructure).

#### Test Suites (Already Configured)

The following test suites should already exist in `phpunit.xml`:

```xml
<testsuite name="auth-unit">
    <directory>tests/Unit</directory>
    <group>auth</group>
</testsuite>

<testsuite name="auth-feature">
    <directory>tests/Feature</directory>
    <group>auth</group>
</testsuite>

<testsuite name="auth-integration">
    <directory>tests/Integration</directory>
    <group>auth</group>
</testsuite>
```

#### Verification Commands

```bash
# Test each suite
php artisan test --testsuite=auth-unit
php artisan test --testsuite=auth-feature
php artisan test --testsuite=auth-integration

# Test with fixed seed
php artisan test --group=auth --seed=12345
```

**Expected:** All tests should run successfully.

---

### Phase 3: Implement seedAuthDomain Method

**Goal:** Add `seedAuthDomain()` method to `TestDataSeeder` class.

#### Method Signature

```php
/**
 * Seed authentication domain test data with fixed seed for reproducibility
 * 
 * This method creates a complete auth domain test setup including:
 * - Tenant
 * - Roles (admin, member, client, etc.)
 * - Permissions (auth-related permissions)
 * - Users with different roles
 * - Role-permission assignments
 * - User-role assignments
 * 
 * @param int $seed Fixed seed value (default: 12345)
 * @return array{
 *     tenant: \App\Models\Tenant,
 *     users: \App\Models\User[],
 *     roles: \App\Models\Role[],
 *     permissions: \App\Models\Permission[]
 * }
 */
public static function seedAuthDomain(int $seed = 12345): array
```

#### Implementation Template

```php
public static function seedAuthDomain(int $seed = 12345): array
{
    // Set fixed seed for reproducibility
    mt_srand($seed);
    
    // Create tenant
    $tenant = self::createTenant([
        'name' => 'Auth Test Tenant',
        'slug' => 'auth-test-tenant',
        'status' => 'active',
    ]);
    
    // Create roles
    $roles = [];
    $roleNames = ['admin', 'member', 'client', 'project_manager'];
    
    foreach ($roleNames as $roleName) {
        $roles[$roleName] = \App\Models\Role::create([
            'name' => $roleName,
            'scope' => 'system',
            'allow_override' => false,
            'description' => "Auth test role: {$roleName}",
            'is_active' => true,
            'tenant_id' => $tenant->id,
        ]);
    }
    
    // Create permissions (auth-related)
    $permissions = [];
    $permissionData = [
        ['module' => 'auth', 'action' => 'login', 'description' => 'Can login'],
        ['module' => 'auth', 'action' => 'logout', 'description' => 'Can logout'],
        ['module' => 'auth', 'action' => 'register', 'description' => 'Can register'],
        ['module' => 'auth', 'action' => 'reset_password', 'description' => 'Can reset password'],
        ['module' => 'auth', 'action' => 'change_password', 'description' => 'Can change password'],
        ['module' => 'auth', 'action' => 'verify_email', 'description' => 'Can verify email'],
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
    
    // Member gets basic permissions
    $memberPerms = collect($permissions)
        ->whereIn('action', ['login', 'logout', 'change_password', 'verify_email'])
        ->pluck('id')
        ->toArray();
    $roles['member']->permissions()->attach($memberPerms);
    
    // Create users with different roles
    $users = [];
    
    // Admin user
    $users['admin'] = self::createUserWithRole('admin', $tenant, [
        'name' => 'Auth Admin User',
        'email' => 'admin@auth-test.test',
        'password' => 'password',
    ]);
    $users['admin']->roles()->attach($roles['admin']->id);
    
    // Member user
    $users['member'] = self::createUserWithRole('member', $tenant, [
        'name' => 'Auth Member User',
        'email' => 'member@auth-test.test',
        'password' => 'password',
    ]);
    $users['member']->roles()->attach($roles['member']->id);
    
    // Client user
    $users['client'] = self::createUserWithRole('client', $tenant, [
        'name' => 'Auth Client User',
        'email' => 'client@auth-test.test',
        'password' => 'password',
    ]);
    $users['client']->roles()->attach($roles['client']->id);
    
    // Project manager user
    $users['project_manager'] = self::createUserWithRole('project_manager', $tenant, [
        'name' => 'Auth PM User',
        'email' => 'pm@auth-test.test',
        'password' => 'password',
    ]);
    $users['project_manager']->roles()->attach($roles['project_manager']->id);
    
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
- Use existing `TestDataSeeder` methods (`createTenant()`, `createUserWithRole()`) where possible
- Create standard roles: admin, member, client, project_manager
- Create auth-related permissions: login, logout, register, reset_password, change_password, verify_email
- Attach permissions to roles appropriately
- Create users with different roles for testing
- Return structured array with all created entities

#### Location

Add this method to `tests/Helpers/TestDataSeeder.php` after the existing methods (around line 202).

#### Verification

```bash
# Test the method directly (if you create a test)
php artisan test --filter seedAuthDomain

# Or test via auth tests
php artisan test --group=auth --seed=12345
```

---

### Phase 4: Create Fixtures File

**Goal:** Create `tests/fixtures/domains/auth/fixtures.json` for reference data.

#### File Structure

```json
{
  "seed": 12345,
  "domain": "auth",
  "tenants": [
    {
      "name": "Auth Test Tenant",
      "slug": "auth-test-tenant",
      "status": "active"
    }
  ],
  "roles": [
    {
      "name": "admin",
      "scope": "system",
      "description": "Auth test role: admin"
    },
    {
      "name": "member",
      "scope": "system",
      "description": "Auth test role: member"
    },
    {
      "name": "client",
      "scope": "system",
      "description": "Auth test role: client"
    },
    {
      "name": "project_manager",
      "scope": "system",
      "description": "Auth test role: project_manager"
    }
  ],
  "permissions": [
    {
      "code": "auth.login",
      "module": "auth",
      "action": "login",
      "description": "Can login"
    },
    {
      "code": "auth.logout",
      "module": "auth",
      "action": "logout",
      "description": "Can logout"
    },
    {
      "code": "auth.register",
      "module": "auth",
      "action": "register",
      "description": "Can register"
    },
    {
      "code": "auth.reset_password",
      "module": "auth",
      "action": "reset_password",
      "description": "Can reset password"
    },
    {
      "code": "auth.change_password",
      "module": "auth",
      "action": "change_password",
      "description": "Can change password"
    },
    {
      "code": "auth.verify_email",
      "module": "auth",
      "action": "verify_email",
      "description": "Can verify email"
    }
  ],
  "users": [
    {
      "name": "Auth Admin User",
      "email": "admin@auth-test.test",
      "role": "admin"
    },
    {
      "name": "Auth Member User",
      "email": "member@auth-test.test",
      "role": "member"
    },
    {
      "name": "Auth Client User",
      "email": "client@auth-test.test",
      "role": "client"
    },
    {
      "name": "Auth PM User",
      "email": "pm@auth-test.test",
      "role": "project_manager"
    }
  ]
}
```

#### File Location

Create directory if needed:
```bash
mkdir -p tests/fixtures/domains/auth
```

Then create `tests/fixtures/domains/auth/fixtures.json` with the content above.

#### Purpose

This file serves as:
- Reference documentation for test data structure
- Template for other domains
- Validation reference for test data

---

### Phase 5: Playwright Projects (Optional)

**Goal:** Add Playwright project configuration for auth E2E tests.

**Note:** This may be handled by Codex Agent in the Frontend E2E Organization work package. Check `docs/work-packages/frontend-e2e-organization.md` for details.

#### If Needed

Add to `playwright.config.ts` (if file exists):

```typescript
{
  name: 'auth-e2e-chromium',
  testMatch: '**/E2E/auth/**/*.spec.ts',
  use: { ...devices['Desktop Chrome'] },
}
```

#### Verification

```bash
npm run test:auth:e2e
```

---

### Phase 6: NPM Scripts

**Goal:** Add NPM scripts to `package.json` for running auth tests.

#### Scripts to Add

Add to `package.json` `scripts` section:

```json
{
  "scripts": {
    "test:auth": "php artisan test --group=auth",
    "test:auth:unit": "php artisan test --testsuite=auth-unit",
    "test:auth:feature": "php artisan test --testsuite=auth-feature",
    "test:auth:integration": "php artisan test --testsuite=auth-integration",
    "test:auth:e2e": "playwright test --project=auth-e2e-chromium"
  }
}
```

#### Verification

```bash
npm run test:auth
npm run test:auth:unit
npm run test:auth:feature
npm run test:auth:integration
# npm run test:auth:e2e  # If Playwright is configured
```

---

## Common Pitfalls

### 1. Forgetting Fixed Seed

**Problem:** Not using fixed seed `12345` consistently.

**Solution:** Always use `mt_srand(12345)` at the start of `seedAuthDomain()`.

### 2. Missing @group Annotation

**Problem:** Forgetting to add `@group auth` to some test files.

**Solution:** Use the verification command after Phase 1:
```bash
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/
```

### 3. Incorrect Test Suite Configuration

**Problem:** Test suites not working after Core Infrastructure changes.

**Solution:** Verify test suites exist in `phpunit.xml`:
```bash
grep -A 3 "auth-unit\|auth-feature\|auth-integration" phpunit.xml
```

### 4. Role-Permission Mismatch

**Problem:** Permissions not attached to roles correctly.

**Solution:** Verify role-permission relationships in `seedAuthDomain()`:
```php
// Check that admin has all permissions
$adminRole = $roles['admin'];
$adminPerms = $adminRole->permissions()->pluck('code')->toArray();
// Should contain all permission codes
```

### 5. User-Role Assignment Issues

**Problem:** Users not assigned to roles correctly.

**Solution:** Use `attach()` method correctly:
```php
$user->roles()->attach($role->id);
// Not: $user->roles = $role;
```

### 6. Test Isolation Issues

**Problem:** Tests interfering with each other.

**Solution:** Use `DomainTestIsolation` trait in test classes:
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

---

## Verification Steps

### Step 1: Verify Annotations

```bash
grep -r "@group auth" tests/Feature/Auth/ tests/Unit/ tests/Integration/ tests/Feature/Buttons/ tests/Browser/
```

**Expected:** 9 files should appear.

### Step 2: Verify Test Suites

```bash
php artisan test --testsuite=auth-feature
php artisan test --testsuite=auth-unit
php artisan test --testsuite=auth-integration
```

**Expected:** All tests should run successfully.

### Step 3: Verify Reproducibility

```bash
# Run tests twice with same seed
php artisan test --group=auth --seed=12345 > /tmp/auth-test1.log
php artisan test --group=auth --seed=12345 > /tmp/auth-test2.log

# Compare results (should be identical)
diff /tmp/auth-test1.log /tmp/auth-test2.log
```

**Expected:** No differences (empty diff output).

### Step 4: Verify seedAuthDomain Method

```bash
# Run auth tests to verify seeding works
php artisan test --group=auth --seed=12345
```

**Expected:** All tests pass with consistent data.

### Step 5: Verify NPM Scripts

```bash
npm run test:auth
npm run test:auth:unit
npm run test:auth:feature
npm run test:auth:integration
```

**Expected:** All scripts execute successfully.

---

## Troubleshooting

### Issue: Tests Not Grouped Correctly

**Symptoms:** `php artisan test --group=auth` doesn't run expected tests.

**Solutions:**
1. Verify `@group auth` annotations are present in all test files
2. Check PHPDoc format (must be `/**` not `/*`)
3. Verify test suites in `phpunit.xml` have correct `<group>auth</group>` filter

### Issue: seedAuthDomain Method Not Found

**Symptoms:** `Call to undefined method TestDataSeeder::seedAuthDomain()`

**Solutions:**
1. Verify method is added to `tests/Helpers/TestDataSeeder.php`
2. Check method signature matches template exactly
3. Clear any cached files: `php artisan clear-compiled`

### Issue: Test Data Not Reproducible

**Symptoms:** Same seed produces different results.

**Solutions:**
1. Ensure `mt_srand($seed)` is called at the start of `seedAuthDomain()`
2. Check that no other code is calling `mt_srand()` with different values
3. Verify test isolation is working (use `DomainTestIsolation` trait)

### Issue: Role-Permission Relationships Not Working

**Symptoms:** Users don't have expected permissions.

**Solutions:**
1. Verify `attach()` is called correctly: `$role->permissions()->attach($permissionIds)`
2. Check pivot table `role_permissions` exists and has correct structure
3. Verify permissions are created before attaching to roles

### Issue: NPM Scripts Not Working

**Symptoms:** `npm run test:auth` fails.

**Solutions:**
1. Verify scripts are added to `package.json` correctly
2. Check JSON syntax is valid
3. Ensure PHP and Laravel are accessible from NPM scripts

---

## Completion Checklist

Before marking work as complete, verify:

- [ ] All 6 files have `@group auth` annotation
- [ ] Test suites (`auth-unit`, `auth-feature`, `auth-integration`) run successfully
- [ ] `seedAuthDomain()` method exists and works correctly
- [ ] Fixtures file created at `tests/fixtures/domains/auth/fixtures.json`
- [ ] NPM scripts added to `package.json` (if applicable)
- [ ] Reproducibility verified (same seed = same results)
- [ ] All tests pass with fixed seed `12345`
- [ ] Documentation updated (if needed)

---

## Additional Resources

- **Main Work Package:** `docs/work-packages/auth-domain.md`
- **File Audit:** `docs/work-packages/auth-domain-audit.md`
- **Test Groups Documentation:** `docs/TEST_GROUPS.md`
- **DomainTestIsolation Trait:** `tests/Traits/DomainTestIsolation.php`
- **TestDataSeeder Class:** `tests/Helpers/TestDataSeeder.php`

---

**Last Updated:** 2025-11-08  
**Prepared By:** Cursor Agent  
**For:** Continue Agent

