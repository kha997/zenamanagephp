<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EncryptCookies;
use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * GAP-051 regression contracts. Failures in this file mean Bearer-token
 * transport fidelity or its production-topology premise has regressed.
 */
class SanctumBearerTransportGuardContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])
            ->get('/__gap051_bearer_contract', function () {
                $user = request()->user();

                return response()->json([
                    'user_id' => $user?->getAuthIdentifier(),
                    'token_class' => $user?->currentAccessToken()::class,
                    'token_id' => $user?->currentAccessToken()?->getKey(),
                    'token_owner_type' => $user?->currentAccessToken()?->tokenable_type,
                    'token_owner_id' => $user?->currentAccessToken()?->tokenable_id,
                    'token_abilities' => $user?->currentAccessToken()?->abilities,
                ]);
            });
    }

    public function test_plain_acting_as_contamination_is_rejected_before_raw_bearer_dispatch(): void
    {
        $contaminatingUser = User::factory()->create();
        $tokenOwner = User::factory()->create();
        $plainTextToken = $tokenOwner->createToken('gap051-red-web')->plainTextToken;

        $this->actingAs($contaminatingUser);

        try {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$plainTextToken,
            ])->getJson('/__gap051_bearer_contract');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('GAP-051', $exception->getMessage());
            self::assertStringContainsString('guard [web]', $exception->getMessage());

            return;
        }

        self::fail(sprintf(
            'GAP-051 RED: raw real Bearer request was dispatched with cached web state; status=%d user_id=%s token_owner_id=%s token_class=%s.',
            $response->getStatusCode(),
            (string) $response->json('user_id'),
            (string) $tokenOwner->getKey(),
            (string) $response->json('token_class')
        ));
    }

    public function test_sanctum_acting_as_contamination_is_rejected_before_raw_bearer_dispatch(): void
    {
        $contaminatingUser = User::factory()->create();
        $tokenOwner = User::factory()->create();
        $plainTextToken = $tokenOwner->createToken('gap051-red-sanctum')->plainTextToken;

        Sanctum::actingAs($contaminatingUser, ['ability-from-cached-state']);

        try {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$plainTextToken,
            ])->getJson('/__gap051_bearer_contract');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('GAP-051', $exception->getMessage());
            self::assertStringContainsString('guard [sanctum]', $exception->getMessage());

            return;
        }

        self::fail(sprintf(
            'GAP-051 RED: raw real Bearer request was dispatched with cached sanctum state; status=%d user_id=%s token_owner_id=%s token_class=%s.',
            $response->getStatusCode(),
            (string) $response->json('user_id'),
            (string) $tokenOwner->getKey(),
            (string) $response->json('token_class')
        ));
    }

    public function test_real_bearer_helper_clears_prior_guard_state_and_proves_exact_token_lookup(): void
    {
        $contaminatingUser = User::factory()->create();
        $tokenOwner = User::factory()->create();
        $abilities = ['projects:read', 'tasks:update'];

        $this->actingAs($contaminatingUser);

        $response = $this
            ->actingAsSanctumBearerToken($tokenOwner, $abilities)
            ->getJson('/__gap051_bearer_contract');

        $issuedToken = $this->issuedSanctumBearerToken();

        self::assertInstanceOf(PersonalAccessToken::class, $issuedToken);
        $response->assertOk();
        $response->assertJsonPath('user_id', $tokenOwner->getKey());
        $response->assertJsonPath('token_class', PersonalAccessToken::class);
        $response->assertJsonPath('token_id', $issuedToken->getKey());
        $response->assertJsonPath('token_owner_type', User::class);
        $response->assertJsonPath('token_owner_id', $tokenOwner->getKey());
        $response->assertJsonPath('token_abilities', $abilities);
        self::assertTrue($issuedToken->tokenable->is($tokenOwner));
        self::assertSame($abilities, $issuedToken->abilities);
    }

    public function test_missing_bearer_token_without_cached_guard_state_is_rejected(): void
    {
        $this->getJson('/__gap051_bearer_contract')->assertUnauthorized();
    }

    public function test_invalid_bearer_token_without_cached_guard_state_is_rejected(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer this-is-not-a-real-token',
        ])->getJson('/__gap051_bearer_contract')->assertUnauthorized();
    }

    public function test_isolated_real_bearer_helper_proves_exact_personal_access_token(): void
    {
        $tokenOwner = User::factory()->create();
        $abilities = ['documents:read'];

        $response = $this
            ->actingAsSanctumBearerToken($tokenOwner, $abilities)
            ->getJson('/__gap051_bearer_contract');

        $issuedToken = $this->issuedSanctumBearerToken();

        self::assertInstanceOf(PersonalAccessToken::class, $issuedToken);
        $response->assertOk();
        $response->assertJsonPath('user_id', $tokenOwner->getKey());
        $response->assertJsonPath('token_class', PersonalAccessToken::class);
        $response->assertJsonPath('token_id', $issuedToken->getKey());
        $response->assertJsonPath('token_owner_type', User::class);
        $response->assertJsonPath('token_owner_id', $tokenOwner->getKey());
        $response->assertJsonPath('token_abilities', $abilities);
        self::assertTrue($issuedToken->tokenable->is($tokenOwner));
        self::assertSame($abilities, $issuedToken->abilities);
    }

    public function test_completed_real_bearer_dispatch_does_not_contaminate_the_next_dispatch(): void
    {
        $tokenOwner = User::factory()->create();
        $issuedToken = $tokenOwner->createToken('gap051-repeated-transport', ['documents:read']);
        $headers = ['Authorization' => 'Bearer '.$issuedToken->plainTextToken];

        for ($dispatchNumber = 1; $dispatchNumber <= 2; $dispatchNumber++) {
            $response = $this->getJson('/__gap051_bearer_contract', $headers);

            $response->assertOk();
            $response->assertJsonPath('token_class', PersonalAccessToken::class);
            $response->assertJsonPath('token_id', $issuedToken->accessToken->getKey());
            $response->assertJsonPath('token_owner_id', $tokenOwner->getKey());
            $response->assertJsonPath('token_abilities', ['documents:read']);
        }
    }

    public function test_sanctum_acting_as_ability_test_without_bearer_header_remains_supported(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['documents:read']);

        $response = $this->getJson('/__gap051_bearer_contract');

        $response->assertOk();
        $response->assertJsonPath('user_id', $user->getKey());
        self::assertTrue($user->tokenCan('documents:read'));
        self::assertFalse($user->tokenCan('documents:write'));
        self::assertInstanceOf(MockInterface::class, $user->currentAccessToken());
    }

    public function test_with_header_default_header_path_detects_case_insensitive_bearer_scheme(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertBearerDispatchRejected(
            fn () => $this->withHeader('authorization', 'bEaReR raw-header-token')
                ->get('/api/health'),
            'web'
        );
    }

    public function test_get_json_header_argument_detects_bearer_contamination(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertBearerDispatchRejected(
            fn () => $this->getJson('/api/health', [
                'Authorization' => 'Bearer raw-get-json-token',
            ]),
            'web'
        );
    }

    public function test_post_json_header_argument_detects_bearer_contamination(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertBearerDispatchRejected(
            fn () => $this->postJson('/api/health', [], [
                'Authorization' => 'Bearer raw-post-json-token',
            ]),
            'web'
        );
    }

    public function test_server_variable_default_header_path_detects_bearer_contamination(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertBearerDispatchRejected(
            fn () => $this->withServerVariables([
                'HTTP_AUTHORIZATION' => 'Bearer raw-server-variable-token',
            ])->get('/api/health'),
            'web'
        );
    }

    public function test_redirect_authorization_server_variable_path_detects_bearer_contamination(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertBearerDispatchRejected(
            fn () => $this->withServerVariables([
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer raw-redirect-server-variable-token',
            ])->get('/api/health'),
            'web'
        );
    }

    public function test_api_middleware_group_excludes_session_and_stateful_auth_enablers(): void
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        $this->assertGap051ApiMiddlewareRemainsStateless(
            $kernel->getMiddlewareGroups()['api'] ?? []
        );
    }

    public function test_topology_invariant_rejects_each_material_stateful_auth_enabler(): void
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        $apiMiddleware = $kernel->getMiddlewareGroups()['api'] ?? [];

        foreach ([StartSession::class, EnsureFrontendRequestsAreStateful::class] as $statefulMiddleware) {
            try {
                $this->assertGap051ApiMiddlewareRemainsStateless([
                    ...$apiMiddleware,
                    $statefulMiddleware,
                ]);
            } catch (AssertionFailedError $exception) {
                self::assertStringContainsString('GAP-051', $exception->getMessage());
                self::assertStringContainsString($statefulMiddleware, $exception->getMessage());

                continue;
            }

            self::fail(sprintf('GAP-051 topology invariant accepted [%s].', $statefulMiddleware));
        }
    }

    public function test_encrypt_cookies_alone_is_not_treated_as_stateful_auth_exposure(): void
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        $apiMiddleware = $kernel->getMiddlewareGroups()['api'] ?? [];

        $this->assertGap051ApiMiddlewareRemainsStateless([
            ...$apiMiddleware,
            EncryptCookies::class,
        ]);
    }

    public function test_real_api_stack_rejects_persisted_web_session_without_bearer_token(): void
    {
        $user = User::factory()->create();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32)),
            'session.driver' => 'file',
            'session.files' => storage_path('framework/sessions'),
        ]);
        $this->app->forgetInstance('encrypter');

        /** @var SessionManager $sessions */
        $sessions = $this->app->make('session');
        $sessions->forgetDrivers();

        /** @var AuthManager $auth */
        $auth = $this->app->make(AuthManager::class);
        $auth->forgetGuards();

        $webGuard = $auth->guard('web');
        $session = $sessions->driver();
        $session->start();
        $session->put($webGuard->getName(), $user->getAuthIdentifier());
        $session->save();
        $sessionId = $session->getId();

        $sessions->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $auth->forgetGuards();

        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withHeader('Referer', (string) config('app.url'))
            ->withCredentials()
            ->getJson('/api/admin/sidebar-configs')
            ->assertUnauthorized();
    }

    private function assertBearerDispatchRejected(callable $dispatch, string $guard): void
    {
        try {
            $dispatch();
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('GAP-051', $exception->getMessage());
            self::assertStringContainsString(sprintf('guard [%s]', $guard), $exception->getMessage());

            return;
        }

        self::fail(sprintf('Expected GAP-051 to reject cached guard [%s] before Bearer dispatch.', $guard));
    }

    /**
     * @param  array<int, class-string|string>  $apiMiddleware
     */
    private function assertGap051ApiMiddlewareRemainsStateless(array $apiMiddleware): void
    {
        foreach ([StartSession::class, EnsureFrontendRequestsAreStateful::class] as $statefulMiddleware) {
            self::assertNotContains(
                $statefulMiddleware,
                $apiMiddleware,
                sprintf(
                    'GAP-051: [%s] materially enables session/stateful authentication on API routes. '
                    .'Gate 1 no-production-exposure evidence is no longer valid; open a follow-up Work ID '
                    .'and reassess before changing this invariant.',
                    $statefulMiddleware
                )
            );
        }
    }
}
