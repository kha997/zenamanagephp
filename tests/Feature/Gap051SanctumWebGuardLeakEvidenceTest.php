<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GAP-051 Gate 1 — disposable evidence harness (NOT a regression test, NOT
 * remediation). Proves, with real execution against this repo's actual
 * config/auth.php + config/sanctum.php + app/Http/Kernel.php, exactly which
 * guard authenticates a request to an `auth:sanctum` route under three
 * scenarios. See docs/audits/2026-09-09-gap-051-sanctum-bearer-token-test-fidelity-evidence.md
 * for the full write-up. This file registers its own ad-hoc probe route so
 * it does not depend on (or risk mutating) any production route/controller.
 *
 * Do not extend this file's pattern into new tests without Gate-2 direction —
 * it exists solely to generate Gate-1 evidence.
 */
class Gap051SanctumWebGuardLeakEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])
            ->get('/__gap051_probe', function () {
                return response()->json([
                    'ok' => true,
                    'user_id' => auth()->id(),
                    'guard_used_default' => auth()->getDefaultDriver(),
                ]);
            });
    }

    /**
     * SCENARIO 1 (the inherited lead, CONFIRMED): a test method that calls
     * Laravel's own `$this->actingAs($user)` (web guard, per config/auth.php
     * defaults.guard = 'web') and then sends a request to an `auth:sanctum`
     * route with NO Authorization header at all currently gets 200, not 401 —
     * because Laravel\Sanctum\Guard::__invoke() (vendor/laravel/sanctum/src/Guard.php:32)
     * loops config('sanctum.guard') (this repo: config/sanctum.php:36 -> ['web'])
     * BEFORE ever inspecting the Bearer token, and `actingAs()` has already
     * cached a user on the 'web' guard instance for the remainder of this
     * test method's lifetime (Illuminate SessionGuard caches $this->user in
     * memory once set, independent of any session cookie).
     */
    public function test_web_guard_actingAs_leak_authenticates_sanctum_route_with_no_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/__gap051_probe');

        // Documenting CURRENT (defective from a test-fidelity standpoint)
        // behavior: this passes today. If Gate 2 hardens this, the assertion
        // below should start failing loudly, which is the intended trip-wire.
        $response->assertStatus(200);
        $response->assertJsonPath('user_id', $user->id);
    }

    /**
     * SCENARIO 2 (negative control, no web-guard leak): a fresh test method,
     * no actingAs() anywhere, no Authorization header at all. Sanctum's guard
     * has nothing to fall back to on 'web' (no user set on that guard in this
     * test's container) and no bearer token to look up, so this MUST be 401.
     */
    public function test_missing_bearer_token_without_any_web_guard_state_is_rejected(): void
    {
        $response = $this->getJson('/__gap051_probe');

        $response->assertStatus(401);
    }

    /**
     * SCENARIO 3 (negative control, invalid token, no web-guard leak): an
     * obviously-garbage Bearer token, again with no actingAs() anywhere in
     * this test method. Sanctum's PersonalAccessToken::findToken() must fail
     * to resolve a row, so this MUST be 401.
     */
    public function test_invalid_bearer_token_without_any_web_guard_state_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer this-is-not-a-real-token',
        ])->getJson('/__gap051_probe');

        $response->assertStatus(401);
    }

    /**
     * SCENARIO 4 (positive control, real token, isolated): create a genuine
     * Sanctum personal access token via HasApiTokens::createToken() (the same
     * mechanism app/Services/AuthenticationService.php:114 uses for the real
     * POST /api/auth/login endpoint) and present it as a real Bearer header,
     * with no actingAs() / no session state at all. This MUST succeed and
     * MUST be attributable to the real token row, not a guard leak.
     */
    public function test_valid_bearer_token_authenticates_through_sanctum_itself(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('gap051-evidence')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/__gap051_probe');

        $response->assertStatus(200);
        $response->assertJsonPath('user_id', $user->id);
    }

    /**
     * SCENARIO 5 (Sanctum's own official testing helper): Sanctum::actingAs()
     * is itself documented by Laravel as the recommended way to test
     * ability/authorization logic — it also bypasses the real HTTP bearer
     * header / token-lookup path (vendor/laravel/sanctum/src/Sanctum.php:70-93
     * mocks a PersonalAccessToken and calls guard('sanctum')->setUser()
     * directly). Recorded here so the Gate-1 doc can cite it with an actual
     * execution result rather than by inspection only.
     */
    public function test_sanctum_acting_as_also_bypasses_real_token_lookup_by_design(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/__gap051_probe');

        $response->assertStatus(200);
        $response->assertJsonPath('user_id', $user->id);
    }
}
