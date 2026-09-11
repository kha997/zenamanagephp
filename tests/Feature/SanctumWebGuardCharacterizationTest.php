<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-051 framework-characterization canary, not remediation evidence.
 *
 * This test intentionally documents Sanctum's web-first guard behavior. A
 * change to RED signals an upstream Laravel/Sanctum behavior change to
 * investigate; its 200 result does not prove the GAP-051 guard is working.
 */
class SanctumWebGuardCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])
            ->get('/__gap051_web_guard_characterization', function () {
                return response()->json([
                    'user_id' => request()->user()?->getAuthIdentifier(),
                ]);
            });
    }

    public function test_web_guard_acting_as_authenticates_sanctum_route_without_a_token_by_design(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/__gap051_web_guard_characterization');

        $response->assertOk();
        $response->assertJsonPath('user_id', $user->getKey());
    }
}
