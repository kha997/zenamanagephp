<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuotePriceReferenceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_lookup_returns_latest_matching_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1750000,
            'benchmark_type' => PriceReferenceEntry::BENCHMARK_VENDOR_QUOTE,
            'evidenced_at' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data.unit_price', 1750000);
        $response->assertJsonPath('data.benchmark_type', 'vendor_quote');
        $response->assertJsonPath('data.benchmark_type_label', 'Báo giá nhà cung cấp');
    }

    public function test_lookup_returns_null_data_when_no_match(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'NOPE', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }

    public function test_lookup_requires_crm_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], []);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertStatus(403);
    }

    public function test_history_returns_all_entries_newest_first(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1500000,
            'evidenced_at' => '2026-01-01',
        ]);
        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1750000,
            'evidenced_at' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.history', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data.0.unit_price', 1750000);
        $response->assertJsonPath('data.1.unit_price', 1500000);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_history_is_tenant_isolated(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $response = $this->actingAs($userB)->getJson(
            route('operator.crm.price-references.history', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
