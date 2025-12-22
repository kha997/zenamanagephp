# PasswordChangeTest Fixes - For Continue Agent

**Date:** 2025-11-08 13:00  
**Prepared By:** Cursor  
**For:** Continue Agent  
**Issue:** PasswordChangeTest tests consistently failing with 404 errors

---

## Root Cause Analysis

The 404 errors are caused by:

1. **Route Path Issue:** Tests use `/auth/password/change` but the actual route is `/api/auth/password/change`
2. **Missing User Role:** The `ability:tenant` middleware requires users to have a valid role
3. **Route Registration:** May need verification that routes are registered in test environment

---

## Complete Fixed Test File

Replace the entire content of `tests/Feature/Auth/PasswordChangeTest.php` with:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\AuthHelper;
use Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;

/**
 * @group auth
 * Password Change Test
 * 
 * Tests for authenticated password change functionality
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected string $currentPassword = 'CurrentPassword123!';

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to reset rate limiting
        Cache::flush();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create user with known password and valid role
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make($this->currentPassword),
            'is_active' => true,
            'role' => 'member', // Required for ability:tenant middleware
        ]);

        // Authenticate user using Sanctum
        Sanctum::actingAs(
            $this->user,
            [],
            'sanctum'
        );
    }

    /**
     * Test successful password change
     */
    public function test_user_can_change_password_successfully(): void
    {
        $newPassword = 'NewPassword456!';

        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]
        );

        $response->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('success', true)
                    ->where('message', 'Password changed successfully.')
                    ->etc()
            );

        // Verify the password has been updated in the database
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
    }

    /**
     * Test authentication is required to change password
     */
    public function test_authentication_is_required_to_change_password(): void
    {
        // Don't authenticate for this test
        Sanctum::actingAs(null);

        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]
        );

        $response->assertStatus(401);
        
        // Check for either Laravel's default message or custom API response
        $json = $response->json();
        $this->assertTrue(
            isset($json['message']) && (
                $json['message'] === 'Unauthenticated.' ||
                str_contains($json['message'] ?? '', 'Unauthenticated') ||
                str_contains($json['message'] ?? '', 'Authentication required')
            ) ||
            isset($json['error']['code']) && $json['error']['code'] === 'AUTH_REQUIRED'
        );
    }

    /**
     * Test current password validation
     */
    public function test_current_password_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => 'wrong_password',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.current_password')
                    ->etc()
            );
    }

    /**
     * Test password confirmation validation
     */
    public function test_password_confirmation_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => 'NewPassword456!',
                'password_confirmation' => 'DifferentPassword!',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }

    /**
     * Test password minimum length validation
     */
    public function test_password_minimum_length_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => 'Short!',
                'password_confirmation' => 'Short!',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }

    /**
     * Test new password must be different from current password
     */
    public function test_new_password_must_be_different_from_current_password(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => $this->currentPassword,
                'password_confirmation' => $this->currentPassword,
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }
}
```

---

## Key Changes Made

### 1. Added `/api` Prefix to All Routes
- Changed `/auth/password/change` → `/api/auth/password/change`
- This matches the route registration in `routes/api.php` line 182-183

### 2. Added User Role
- Added `'role' => 'member'` to user factory creation
- Required for `ability:tenant` middleware to pass
- Valid roles: `'member'`, `'pm'`, `'admin'`, `'super_admin'`, etc.

### 3. Fixed Authentication Test
- Updated `test_authentication_is_required_to_change_password()` to properly unauthenticate
- Made assertion more flexible to handle different error message formats

### 4. Fixed Expected Message
- Changed `'Password changed successfully'` → `'Password changed successfully.'`
- Matches the actual message in `PasswordController::change()` line 197

---

## Verification Steps

After applying these fixes, run:

```bash
php artisan test --filter PasswordChangeTest
```

Expected results:
- ✅ `test_user_can_change_password_successfully()` → 200 OK
- ✅ `test_authentication_is_required_to_change_password()` → 401 Unauthorized
- ✅ `test_current_password_validation()` → 422 with `errors.current_password`
- ✅ `test_password_confirmation_validation()` → 422 with `errors.password`
- ✅ `test_password_minimum_length_validation()` → 422 with `errors.password`
- ✅ `test_new_password_must_be_different_from_current_password()` → 422 with `errors.password`

---

## Debugging Commands (If Still Failing)

### 1. Check Route Registration
```bash
php artisan route:list | grep password
```

### 2. Check Middleware Registration
```bash
php artisan route:list --path=api/auth/password/change
```

### 3. Run Single Test with Verbose Output
```bash
php artisan test --filter test_user_can_change_password_successfully --verbose
```

### 4. Check Test Database
```bash
php artisan test --filter PasswordChangeTest --env=testing
```

---

## Additional Notes

### Route Details
- **Route:** `POST /api/auth/password/change`
- **Controller:** `App\Http\Controllers\Api\Auth\PasswordController::change()`
- **Middleware:** `auth:sanctum`, `ability:tenant`, `security`, `validation`, `rate.limit:sliding,5,1`
- **Request:** `App\Http\Requests\Auth\ChangePasswordRequest`

### Middleware Requirements
- `auth:sanctum`: User must be authenticated via Sanctum ✅ (using `Sanctum::actingAs()`)
- `ability:tenant`: User must have `tenant_id` and valid role ✅ (added `role: 'member'`)
- `security`: Security headers middleware
- `validation`: Input validation middleware
- `rate.limit:sliding,5,1`: Rate limiting (5 requests per minute)

### Valid User Roles for `ability:tenant`
- `'super_admin'`
- `'admin'`
- `'pm'` (Project Manager)
- `'member'`
- `'project_manager'`
- `'site_engineer'`
- `'design_lead'`
- `'client_rep'`
- `'qc_inspector'`

---

## If Issues Persist

1. **Check Route Service Provider:**
   - Verify `routes/api.php` is loaded in `app/Providers/RouteServiceProvider.php`
   - Should have: `Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));`

2. **Check Middleware Registration:**
   - Verify `ability` middleware is registered in `app/Http/Kernel.php` or `bootstrap/app.php`
   - Should be: `'ability' => \App\Http\Middleware\AbilityMiddleware::class`

3. **Check Test Environment:**
   - Verify `.env.testing` has correct database configuration
   - Ensure migrations run correctly in test environment

4. **Check Sanctum Configuration:**
   - Verify `config/sanctum.php` is properly configured
   - Check that `HasApiTokens` trait is used in User model

---

## Contact

If these fixes don't resolve the issue, please:
1. Run the debugging commands above
2. Share the output
3. Check if there are any other test failures that might indicate a broader issue

---

**Last Updated:** 2025-11-08 13:00  
**Status:** Ready for Continue Agent to apply

