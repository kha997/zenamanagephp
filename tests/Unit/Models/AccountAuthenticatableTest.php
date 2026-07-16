<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuthenticatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_implements_authenticatable_and_can_be_used_with_the_client_guard(): void
    {
        $tenant = Tenant::factory()->create();
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang guard test',
            'email' => 'guardtest@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->assertInstanceOf(Authenticatable::class, $account);

        $this->actingAs($account, 'client');

        $this->assertTrue(auth('client')->check());
        $this->assertSame((string) $account->id, (string) auth('client')->id());
    }
}
