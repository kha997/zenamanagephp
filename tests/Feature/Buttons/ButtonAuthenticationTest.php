<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Http\Middleware\EnhancedRateLimitMiddleware;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteractsWithRbac;
use Tests\TestCase;

/**
 * Button Authentication Test
 * 
 * Tests authentication flows for all interactive elements
 */
class ButtonAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected $tenant;
    protected $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        $this->seedRolesAndPermissions();

        foreach (['super_admin', 'admin', 'pm', 'designer', 'engineer', 'guest'] as $role) {
            $this->users[$role] = $this->createUserWithRole($role, $this->tenant);
        }
    }

    /**
     * Test login button functionality
     */
    public function test_login_button_works(): void
    {
        $response = $this->post('/login', [
            'email' => $this->users['pm']->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/app/dashboard');
    }

    /**
     * Test logout button functionality
     */
    public function test_logout_button_works(): void
    {
        $this->actingAs($this->users['pm']);
        
        $response = $this->post('/logout');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Test authentication required for protected routes
     */
    public function test_protected_routes_require_authentication(): void
    {
        $protectedRoutes = [
            route('app.dashboard'),
            route('app.projects'),
            route('app.tasks'),
            route('app.documents'),
            route('app.settings'),
        ];

        foreach ($protectedRoutes as $route) {
            $this->assertNotEmpty($route, 'Protected route resolved to an empty value');
            $response = $this->get($route, ['Accept' => 'text/html']);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Test API authentication
     */
    public function test_api_authentication(): void
    {
        $candidates = [
            '/api/analytics/projects',
            '/api/v1/projects',
            '/api/zena/projects',
            '/api-simple/test-auth',
            '/api-simple/projects-with-auth',
        ];

        $statusLog = [];
        $endpoint = null;

        foreach ($candidates as $candidate) {
            $response = $this->getJson($candidate);
            $status = $response->getStatusCode();
            $statusLog[$candidate] = $status;

            if ($status === 404) {
                continue;
            }

            if ($status !== 401) {
                continue;
            }

            $endpoint = $candidate;
            break;
        }

        if (!$endpoint) {
            $details = [];

            foreach ($statusLog as $candidate => $status) {
                $details[] = "{$candidate} => {$status}";
            }

            $this->fail('Could not find an auth-protected JSON endpoint. Tried: ' . implode(', ', $details));
        }

        $this->getJson($endpoint)->assertStatus(401);

        Sanctum::actingAs($this->users['pm'] ?? $this->users['super_admin'], ['*']);

        $authResponse = $this->getJson($endpoint);
        $this->assertNotEquals(404, $authResponse->getStatusCode(), "Endpoint not found: {$endpoint}");
        $this->assertTrue(
            in_array($authResponse->getStatusCode(), [200, 403], true),
            "Expected 200 or 403, got {$authResponse->getStatusCode()} for {$endpoint}"
        );
    }

    /**
     * Test session management
     */
    public function test_session_management(): void
    {
        $pm = $this->users['pm'];

        $this->actingAs($pm, 'web');

        $this->get('/app/dashboard', ['Accept' => 'text/html'])->assertStatus(200);

        auth('web')->logout();
        $this->app['session']->flush();
        $this->app['session']->save();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/app/dashboard', ['Accept' => 'text/html']);
        $response->assertRedirect('/login');
        $this->assertGuest('web');
    }

    /**
     * Test password reset functionality
     */
    public function test_password_reset_button(): void
    {
        $this->withoutExceptionHandling();
        Notification::fake();

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->users['pm']->email
        ]);

        if ($response->getStatusCode() >= 500) {
            fwrite(STDERR, "\n== password reset 500 body ==\n" . $response->getContent() . "\n");
            fwrite(STDERR, "\n== headers ==\n" . json_encode($response->headers->all(), JSON_PRETTY_PRINT) . "\n");
            $this->fail('Password reset endpoint returned 500; see dumped body above.');
        }

        $response->assertStatus(200);
    }

    /**
     * Test invalid credentials handling
     */
    public function test_invalid_credentials_handling(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => $this->users['pm']->email,
            'password' => 'wrong_password'
        ], ['Accept' => 'text/html']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test rate limiting on login attempts
     */
    public function test_login_rate_limiting(): void
    {
        $middleware = new EnhancedRateLimitMiddleware();
        $configAccessor = \Closure::bind(function (string $type) {
            return $this->getConfig($type);
        }, $middleware, EnhancedRateLimitMiddleware::class);

        $config = $configAccessor('auth');
        $burstLimit = $config['burst_limit'] ?? $config['requests_per_minute'] ?? 20;
        $maxAttempts = $burstLimit + 1;
        $ipAddress = '127.0.0.1';
        $cacheKey = "rate_limit:ip:{$ipAddress}:auth";

        Cache::forget($cacheKey);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $this->users['pm']->email,
                'password' => 'wrong_password'
            ], ['REMOTE_ADDR' => $ipAddress]);

            if ($attempt === $maxAttempts) {
                $response->assertStatus(429);
            }
        }

        Cache::forget($cacheKey);
    }
}
