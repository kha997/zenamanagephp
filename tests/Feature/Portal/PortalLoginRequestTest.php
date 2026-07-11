<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalLoginRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-test']);
        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang login test',
            'email' => 'realclient@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);
    }

    public function test_login_form_renders_for_a_valid_tenant_slug(): void
    {
        $this->get(route('portal.login', ['tenantSlug' => 'zena-test']))
            ->assertOk()
            ->assertSee('Đăng nhập');
    }

    public function test_login_form_404s_for_an_unknown_tenant_slug(): void
    {
        $this->get(route('portal.login', ['tenantSlug' => 'no-such-tenant']))
            ->assertNotFound();
    }

    public function test_sending_login_link_for_a_real_email_creates_a_token_and_sends_mail(): void
    {
        Mail::fake();

        $this->get(route('portal.login', ['tenantSlug' => 'zena-test']));

        $response = $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'realclient@example.com',
        ]);

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-test']));
        $response->assertSessionHas('status');

        $this->assertSame(1, PortalLoginToken::query()->where('account_id', (string) $this->account->id)->count());
        Mail::assertSent(\App\Mail\PortalLoginLinkEmail::class, 1);
    }

    public function test_sending_login_link_for_unknown_email_shows_same_generic_message_without_sending_mail(): void
    {
        Mail::fake();

        $this->get(route('portal.login', ['tenantSlug' => 'zena-test']));

        $response = $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'nobody-registered@example.com',
        ]);

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-test']));
        $response->assertSessionHas('status');

        $this->assertSame(0, PortalLoginToken::query()->count());
        Mail::assertNothingSent();
    }

    public function test_sending_login_link_for_an_email_registered_under_a_different_tenant_does_not_match(): void
    {
        Mail::fake();

        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        Account::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tenant khac',
            'email' => 'crosstenant@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->get(route('portal.login', ['tenantSlug' => 'zena-test']));

        $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'crosstenant@example.com',
        ]);

        $this->assertSame(0, PortalLoginToken::query()->count());
        Mail::assertNothingSent();
    }
}
