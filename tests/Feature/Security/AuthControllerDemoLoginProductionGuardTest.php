<?php declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * GAP-049 Gate-3 Round-1 Owner correction item 2.
 *
 * AuthController::login() contains a hardcoded demo-credential fallback
 * (superadmin@zena.com / password123 / super_admin) that runs after normal
 * Auth::attempt() fails. GAP-049's own production:bootstrap command creates
 * a real super_admin role, which turns this pre-existing demo fallback into
 * an exploitable privileged production authentication path.
 *
 * This test proves:
 *   1. In production (and any non-local/testing environment), the demo
 *      credentials MUST NOT authenticate, MUST NOT create a User row, and
 *      MUST NOT attach any role.
 *   2. In local/testing, the demo fallback continues to work exactly as
 *      before (the Owner explicitly asked that this NOT be deleted, only
 *      scoped away from production).
 *
 * Both requests go through the real POST /login HTTP action. The route
 * itself is registered once at application bootstrap (as `testing`, since
 * that's the real APP_ENV under phpunit), so it stays registered for both
 * sub-tests; only the environment) as observed *inside* the controller via
 * app()->environment() is overridden per test using Laravel's
 * Application::detectEnvironment(), which is the same low-level mechanism
 * Laravel itself uses to determine APP_ENV. This isolates exactly the
 * runtime guard under test without relying on route-registration-time
 * environment (which cannot be changed after boot).
 */
class AuthControllerDemoLoginProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_EMAIL = 'superadmin@zena.com';
    private const DEMO_PASSWORD = 'password123';
    private const DEMO_ROLE = 'super_admin';

    public function test_demo_credentials_are_rejected_in_production(): void
    {
        // Make the super_admin role exist, exactly as GAP-049's
        // production:bootstrap command would have created it — this is the
        // condition that turns the pre-existing demo fallback into a
        // privileged production auth path if left unguarded.
        Role::firstOrCreate(
            ['name' => self::DEMO_ROLE],
            ['scope' => 'system', 'allow_override' => false, 'description' => 'Super Admin', 'is_active' => true]
        );

        $this->assertDatabaseMissing('users', ['email' => self::DEMO_EMAIL]);

        // Establish a real session + CSRF token first (while still in the
        // 'testing' env the route was registered under), exactly as the
        // TestCase convention requires for CSRF-protected POSTs.
        $this->get('/login');

        // Now override the environment observed at request time to
        // 'production', without touching route registration (already bound
        // as 'testing' at application bootstrap).
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame('production', $this->app->environment());

        $response = $this->post('/login', [
            'email' => self::DEMO_EMAIL,
            'password' => self::DEMO_PASSWORD,
        ]);

        // Must NOT be treated as a successful authenticated redirect.
        $response->assertRedirect();
        $this->assertNotEquals('/app/dashboard', $response->headers->get('Location'));

        // Must still be a guest — no session was established for the demo user.
        $this->assertTrue(Auth::guest(), 'Demo credentials must not authenticate in production.');

        // No demo user must have been created.
        $this->assertDatabaseMissing('users', ['email' => self::DEMO_EMAIL]);

        // No user anywhere must hold the super_admin role as a side effect
        // of this request (the role existed already; nobody should be
        // attached to it by the rejected login attempt).
        $superAdminRole = Role::where('name', self::DEMO_ROLE)->first();
        $this->assertNotNull($superAdminRole);
        $this->assertSame(0, $superAdminRole->systemUsers()->count());
    }

    public function test_demo_credentials_still_work_in_testing_environment(): void
    {
        Role::firstOrCreate(
            ['name' => self::DEMO_ROLE],
            ['scope' => 'system', 'allow_override' => false, 'description' => 'Super Admin', 'is_active' => true]
        );

        $this->get('/login');

        // Explicitly (re)affirm the 'testing' environment at request time,
        // proving the fix did not accidentally also break the intentionally
        // preserved local/testing behavior.
        $this->app->detectEnvironment(fn () => 'testing');
        $this->assertSame('testing', $this->app->environment());

        $response = $this->post('/login', [
            'email' => self::DEMO_EMAIL,
            'password' => self::DEMO_PASSWORD,
        ]);

        $response->assertRedirect('/app/dashboard');
        $this->assertTrue(Auth::check(), 'Demo credentials must still authenticate in local/testing.');

        $user = User::where('email', self::DEMO_EMAIL)->first();
        $this->assertNotNull($user, 'Demo user must still be created in local/testing.');
        $this->assertTrue($user->hasRole(self::DEMO_ROLE));
    }
}
