<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLoginVerifyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-verify']);
        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang verify test',
            'email' => 'verifyme@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);
    }

    private function createToken(string $rawToken, array $overrides = []): PortalLoginToken
    {
        return PortalLoginToken::query()->create(array_merge([
            'account_id' => (string) $this->account->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(20),
        ], $overrides));
    }

    public function test_valid_unexpired_unused_token_authenticates_and_redirects_to_dashboard(): void
    {
        $this->createToken('valid-raw-token');

        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'valid-raw-token']));

        $response->assertRedirect(route('portal.dashboard', ['tenantSlug' => 'zena-verify']));
        $this->assertTrue(auth('client')->check());
        $this->assertSame((string) $this->account->id, (string) auth('client')->id());
    }

    public function test_token_is_marked_used_after_successful_verification(): void
    {
        $this->createToken('single-use-token');

        $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'single-use-token']));

        $token = PortalLoginToken::query()->where('token_hash', hash('sha256', 'single-use-token'))->first();
        $this->assertNotNull($token->used_at);
    }

    public function test_second_click_on_the_same_link_does_not_authenticate(): void
    {
        $this->createToken('reused-token');

        $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'reused-token']));
        auth('client')->logout();

        $second = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'reused-token']));

        $second->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_expired_token_does_not_authenticate(): void
    {
        $this->createToken('expired-token', ['expires_at' => now()->subMinute()]);

        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'expired-token']));

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_unknown_token_does_not_authenticate(): void
    {
        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'never-issued']));

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_protected_portal_route_redirects_to_login_when_not_authenticated(): void
    {
        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
    }

    public function test_protected_portal_route_rejects_an_account_authenticated_under_a_different_tenant_slug(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'zena-other']);
        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tenant khac',
            'email' => 'other@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($otherAccount, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_logout_ends_the_session(): void
    {
        // Establish a session (and therefore a CSRF token) before the POST,
        // matching the pattern used by Task 2's PortalLoginRequestTest —
        // this repo's TestCase::ensureCsrfToken() only auto-appends a
        // `_token` when a session already exists.
        $this->get(route('portal.login', ['tenantSlug' => 'zena-verify']));

        $this->actingAs($this->account, 'client');
        $this->assertTrue(auth('client')->check());

        $this->post(route('portal.logout', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));

        $this->assertFalse(auth('client')->check());
    }
}
