<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * GAP-126 — the web login must exist in production.
 *
 * Every other test boots with APP_ENV=testing, where routes/web.php used to
 * register the login routes, so none of them could see that production had
 * no `login` route at all. The route test here asks a fresh `php artisan`
 * process booted with APP_ENV=production (same mechanism as
 * RouteNameCollisionInvariantTest).
 */
final class ProductionWebLoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_ROUTES = ['login', 'login.post', 'password.reset', 'logout', 'logout.post'];

    private const DEV_ONLY_ROUTE = 'local.dev-login.operator';

    public function test_login_routes_exist_in_production_and_dev_login_does_not(): void
    {
        $names = $this->routeNamesUnder('production');

        foreach (self::LOGIN_ROUTES as $name) {
            $this->assertContains($name, $names, "Route [{$name}] is missing under APP_ENV=production.");
        }

        $this->assertNotContains(self::DEV_ONLY_ROUTE, $names, 'The password-less dev login must never exist in production.');
    }

    public function test_dev_login_still_exists_in_local(): void
    {
        $this->assertContains(self::DEV_ONLY_ROUTE, $this->routeNamesUnder('local'));
    }

    public function test_active_user_signs_in_with_the_right_password(): void
    {
        $user = $this->makeUser(isActive: true);

        $this->get('/login');
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-9']);

        $response->assertRedirect('/app/today');
        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_user_cannot_sign_in_and_gets_the_same_error_as_a_wrong_password(): void
    {
        $user = $this->makeUser(isActive: false);

        $this->get('/login');
        $deactivated = $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-9']);

        $this->assertGuest();
        $deactivated->assertSessionHasErrors('email');
        $deactivatedError = session('errors')->first('email');

        $this->get('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-1']);
        $this->assertGuest();

        $this->assertSame(session('errors')->first('email'), $deactivatedError);
    }

    public function test_guest_opening_the_app_is_sent_to_the_login_page(): void
    {
        $this->get('/app/today')->assertRedirect('/login');
    }

    /**
     * @return list<string>
     */
    private function routeNamesUnder(string $env): array
    {
        $result = Process::path(base_path())
            ->env(['APP_ENV' => $env])
            ->timeout(60)
            ->run(['php', 'artisan', 'route:list', '--json']);

        $this->assertTrue($result->successful(), "route:list failed under APP_ENV={$env}: " . $result->errorOutput());

        $this->assertSame(1, preg_match('/\[.*\]/s', $result->output(), $matches), "route:list --json under APP_ENV={$env} printed no JSON.");
        $decoded = json_decode($matches[0], true);
        $this->assertIsArray($decoded);

        return array_values(array_filter(array_map(fn (array $r) => $r['name'] ?? null, $decoded)));
    }

    private function makeUser(bool $isActive): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('correct-horse-9'),
            'is_active' => $isActive,
        ]);
    }
}
