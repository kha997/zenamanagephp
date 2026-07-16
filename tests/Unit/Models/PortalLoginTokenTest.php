<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLoginTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_read_a_portal_login_token(): void
    {
        $tenant = Tenant::factory()->create();
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang portal',
            'email' => 'client@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $token = PortalLoginToken::query()->create([
            'account_id' => (string) $account->id,
            'token_hash' => hash('sha256', 'raw-token-value'),
            'expires_at' => now()->addMinutes(20),
        ]);

        $token->refresh();

        $this->assertSame((string) $account->id, $token->account_id);
        $this->assertSame(hash('sha256', 'raw-token-value'), $token->token_hash);
        $this->assertNull($token->used_at);
    }
}
