<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GAP-130 S-1 — a stale CSRF token on the login form no longer ends on the "419 Page Expired" page, and CSRF protection
 * stays: a request with a bad token never signs anyone in.
 *
 * Production 2026-11-19: a double-submitted login form signed the Owner in with the first POST; the second POST carried
 * the token the sign-in had just regenerated and got 419.
 */
final class LoginCsrfRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@zena.example',
            'password' => Hash::make('correct-horse-9'),
            'is_active' => true,
        ]);
    }

    public function test_double_submit_lands_in_the_app_instead_of_419(): void
    {
        $this->get('/login')->assertOk();
        $staleToken = csrf_token();

        $this->post('/login', ['_token' => $staleToken, 'email' => 'owner@zena.example', 'password' => 'correct-horse-9'])
            ->assertRedirect('/app/today');
        $this->assertAuthenticatedAs($this->user);

        // The sign-in regenerated the token; the second submit still carries the old one.
        $this->assertNotSame($staleToken, csrf_token());
        $this->post('/login', ['_token' => $staleToken, 'email' => 'owner@zena.example', 'password' => 'correct-horse-9'])
            ->assertRedirect('/app/today');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_guest_with_an_expired_token_is_sent_back_to_login_and_not_signed_in(): void
    {
        $this->get('/login')->assertOk();

        $response = $this->post('/login', ['_token' => 'expired-token', 'email' => 'owner@zena.example', 'password' => 'correct-horse-9']);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertSame('owner@zena.example', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertGuest();
    }

    public function test_other_routes_and_json_keep_the_419(): void
    {
        $this->get('/login')->assertOk();
        $this->post('/logout', ['_token' => 'expired-token'])->assertStatus(419);
        $this->postJson('/login', ['_token' => 'expired-token', 'email' => 'owner@zena.example', 'password' => 'correct-horse-9'])
            ->assertStatus(419);
        $this->assertGuest();
    }

    public function test_signed_in_user_opening_login_goes_to_the_app(): void
    {
        $this->actingAs($this->user)->get('/login')->assertRedirect('/app/today');
    }

    public function test_login_form_blocks_a_second_submit(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-single-submit', false);
    }
}
