# RBAC Middleware Friendly Web Error Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `RoleBasedAccessControlMiddleware` currently returns a raw JSON error body for every denial, even on full-page browser navigation — reproduced live on `operator/materials/create` and `operator/vendors/create`. Make it content-negotiate: JSON responses stay byte-for-byte identical for API/AJAX callers, while plain browser navigation gets a friendly redirect with a flash error (already rendered by the existing `x-ui.toast` component).

**Architecture:** Single new private helper method `deny()` in `RoleBasedAccessControlMiddleware`, used at all 4 existing denial call sites. No other files change except tests. No new routes, no new views, no new permissions.

**Tech Stack:** Laravel 12, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-22-rbac-web-friendly-error-design.md`

## Global Constraints

- JSON response bodies for requests where `$request->expectsJson()` is `true` must remain **byte-for-byte identical** to current behavior: same status code, same envelope shape (`ErrorEnvelopeService::error()`), same `code`, same `message` text. Do not change any string literal currently passed as a JSON message.
- Non-JSON (browser navigation) requests get `redirect()->back()->with('error', $webMessage)` for the 3 "denied" branches, and `redirect()->guest(route('login'))->with('error', $webMessage)` for the "not authenticated" branch (matches Laravel's own `Authenticate` middleware convention).
- Web-facing flash messages are Vietnamese, matching the exact phrase already used across the codebase's web controllers: `'Bạn không có quyền thực hiện thao tác này.'` for authorization denials.
- Do not touch `auth`, `tenant.isolation`, or any other middleware. Do not touch any controller using `$this->authorize()` (already correct — Laravel's own exception handler renders a proper 403 page for those).
- Test invocation: `./vendor/bin/phpunit <path>` directly, never `php artisan test`.
- **The final verification step is the full test suite, not a sample** — this middleware gates roughly 300 routes across `routes/web.php` and `routes/api.php`. A small diff with this much blast radius is only safe to ship after a full green run.
- CI is the source of truth for PHPStan; if new findings appear, add surgical `phpstan-baseline.neon` entries (single quotes inside single-quoted neon strings must be doubled `''`).

---

### Task 1: Add the `deny()` helper and use it at all 4 denial call sites

**Files:**
- Modify: `app/Http/Middleware/RoleBasedAccessControlMiddleware.php`
- Test: `tests/Feature/Zena/OperatorRbacWebFriendlyErrorTest.php` (create)

**Interfaces:**
- Produces: private method `deny(Request $request, string $code, string $jsonMessage, int $statusCode, string $webMessage, ?string $requestId = null, array $details = []): Response` on `RoleBasedAccessControlMiddleware`. Not consumed by any other class — internal to this middleware only.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Zena/OperatorRbacWebFriendlyErrorTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-07-22: manual browser check found materials/create and vendors/create
 * dump raw JSON on a plain page navigation when the user lacks permission —
 * RoleBasedAccessControlMiddleware returns JSON unconditionally on denial,
 * unlike routes gated via $this->authorize() (which get Laravel's normal
 * styled 403 page). This test locks in the content-negotiated fix: JSON
 * stays identical for API/AJAX callers, browser navigation gets a friendly
 * redirect + flash instead of a raw JSON dump.
 */
class OperatorRbacWebFriendlyErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    private function userWithoutPermission(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        // Role with zero permissions — same tenant, so auth/tenant.isolation pass,
        // but the specific rbac:vendor.create check must fail.
        $role = Role::factory()->create(['name' => 'No Permissions ' . uniqid()]);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_web_navigation_to_permission_gated_page_gets_friendly_redirect_not_raw_json(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userWithoutPermission($tenant);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        // Plain ->get() — no Accept: application/json header — simulates a real
        // browser page navigation exactly like typing the URL or clicking a link.
        $response = $this->actingAs($user)->get(route('operator.vendors.create'), $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Bạn không có quyền thực hiện thao tác này.');
        $this->assertStringNotContainsString('"code":"E403.AUTHORIZATION"', $response->getContent() ?: '');
    }

    public function test_api_call_to_same_permission_gate_still_gets_unchanged_json_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userWithoutPermission($tenant);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->getJson(route('operator.vendors.create'), $headers);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'E403.AUTHORIZATION');
        $response->assertJsonPath('error.message', 'You do not have permission to access this resource');
        $response->assertJsonPath('success', false);
    }

    public function test_web_navigation_denied_by_bare_rbac_role_check_gets_friendly_redirect(): void
    {
        // handleGeneralAccess() branch: bare `rbac` middleware (no :permission param),
        // denies users with none of the allowed role names at all.
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        // Deliberately no role assignment at all.
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->get(route('api.accessibility.preferences'), $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Bạn không có quyền thực hiện thao tác này.');
    }

    public function test_api_call_denied_by_bare_rbac_role_check_still_gets_unchanged_json_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $response = $this->actingAs($user)->getJson(route('api.accessibility.preferences'), $headers);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'RBAC_ACCESS_DENIED');
        $response->assertJsonPath('success', false);
    }
}
```

**Fixture caveat for the implementer:** `route('operator.vendors.create')` requires the `vendors.create` route to exist with name `operator.vendors.create` (confirmed in `routes/web.php`, gated by `rbac:vendor.create`) — verify with `php artisan route:list | grep vendors.create` if anything seems off. `route('api.accessibility.preferences')` is the bare-`rbac`-gated route at `routes/web.php:143-144`. If either route name has changed since this plan was written, find the current equivalent (any route still gated by `rbac:<permission>` for the first two tests, any route still gated by bare `rbac` for the last two) rather than guessing.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorRbacWebFriendlyErrorTest.php`
Expected: `test_web_navigation_to_permission_gated_page_gets_friendly_redirect_not_raw_json` and `test_web_navigation_denied_by_bare_rbac_role_check_gets_friendly_redirect` FAIL (currently get a 403 JSON response, not a redirect). The two `_still_gets_unchanged_json_envelope` tests should already PASS (documenting current correct behavior for JSON callers, which must not change).

- [ ] **Step 3: Implement the `deny()` helper and rewire all 4 call sites**

In `app/Http/Middleware/RoleBasedAccessControlMiddleware.php`, add this private method (place it near the bottom of the class, alongside other private helpers like `handleGeneralAccess`):

```php
    private function deny(Request $request, string $code, string $jsonMessage, int $statusCode, string $webMessage, ?string $requestId = null, array $details = []): Response
    {
        if ($request->expectsJson()) {
            return ErrorEnvelopeService::error($code, $jsonMessage, $details, $statusCode, $requestId);
        }

        return redirect()->back()->with('error', $webMessage);
    }
```

Replace the 4 call sites:

**Call site 1** (lines 32-37, `!$user` branch):

```php
        if (!$user) {
            if ($request->expectsJson()) {
                return ErrorEnvelopeService::authenticationError(
                    'User not authenticated',
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            return redirect()->guest(route('login'))->with('error', 'Vui lòng đăng nhập để tiếp tục.');
        }
```

(This branch keeps its own `if/else` rather than calling `deny()` because it needs `redirect()->guest(route('login'))`, not `redirect()->back()` — `deny()` is only for the 3 "back()" branches.)

**Call site 2** (lines 46-53, `TENANT_REQUIRED` branch):

```php
            if ($headerTenantId === '') {
                return $this->deny(
                    $request,
                    'TENANT_REQUIRED',
                    'X-Tenant-ID header is required',
                    400,
                    'Yêu cầu không hợp lệ, vui lòng thử lại.',
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }
```

**Call site 3** (lines 84-87, inside `handle()`, specific-permission denial):

```php
            return $this->deny(
                $request,
                'E403.AUTHORIZATION',
                'You do not have permission to access this resource',
                403,
                'Bạn không có quyền thực hiện thao tác này.',
                ErrorEnvelopeService::getCurrentRequestId()
            );
```

**Call site 4** (lines 271-277, inside `handleGeneralAccess()`, bare-role denial):

```php
            return $this->deny(
                $request,
                'RBAC_ACCESS_DENIED',
                'You do not have sufficient RBAC assignments to access this resource',
                403,
                'Bạn không có quyền thực hiện thao tác này.',
                ErrorEnvelopeService::getCurrentRequestId()
            );
```

Verify the class still imports everything it needs — `redirect()` and `route()` are global helpers, no new `use` statements required.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/OperatorRbacWebFriendlyErrorTest.php`
Expected: ALL 4 PASS.

- [ ] **Step 5: Run the full test suite (mandatory — not a sample)**

Run: `./vendor/bin/phpunit`
Expected: same pass/fail counts as the pre-existing baseline (no new failures). This middleware gates ~300 routes; this is the real verification step for this task. If ANY test fails, read the failure carefully — it is either (a) a test asserting on a raw JSON 403 body from a route that should now redirect (meaning that test was itself exercising the bug and needs its assertion updated to match the new correct behavior), or (b) a genuine regression in this change. Do not mass-update failing assertions without reading each one — some may be legitimately catching a real problem with this diff.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/RoleBasedAccessControlMiddleware.php tests/Feature/Zena/OperatorRbacWebFriendlyErrorTest.php
git commit -m "fix(rbac): content-negotiate middleware denial response

RoleBasedAccessControlMiddleware returned a raw JSON error body
unconditionally on every denial, including full-page browser navigation —
reproduced live on operator/materials/create and operator/vendors/create
(materials/vendors gate via bare rbac: middleware; material-requests/create
gates via \$this->authorize() and got Laravel's normal styled 403 page
instead). Add a expectsJson()-based branch, following the exact pattern
already used elsewhere in this codebase (RolePermission, AdminOnlyMiddleware,
Authenticate, TenantScope): JSON stays byte-for-byte identical for API/AJAX
callers, browser navigation gets redirect()->back()->with('error', ...),
rendered by the existing x-ui.toast component already on the operator layout."
```

---

## Final verification (after the task)

- [ ] `./vendor/bin/phpunit` full suite green (already run in Step 5, re-confirm if any fix-ups were made after).
- [ ] Manual browser walkthrough (mandatory — this whole bug class survived because nobody opened a browser): log in as a user without `vendor.create`/`material.create`, visit `operator/vendors/create` and `operator/materials/create` — confirm a friendly toast error appears instead of raw JSON. Then visit as a user WITH permission — confirm the page still loads normally (no regression on the happy path).
- [ ] CI green, including `Zena RBAC/Tenant Invariants` and `Zena RBAC/Tenant Invariants (MySQL parity)` jobs specifically (most likely to catch a regression in this middleware).

## Out of Scope

- `material-requests/create` and any other route gated via `$this->authorize()` — already correct, not touched.
- Changing the JSON envelope shape, error codes, or message text for API/AJAX callers.
- Any middleware other than `RoleBasedAccessControlMiddleware`.
