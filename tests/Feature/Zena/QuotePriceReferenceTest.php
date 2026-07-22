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

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

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

    public function test_saving_a_line_with_benchmark_type_creates_one_reference_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                    'evidence_note' => 'Báo giá Công ty ABC',
                    'evidence_date' => '2026-07-10',
                ],
            ],
        ]);

        $this->assertDatabaseCount('price_reference_entries', 1);
        $this->assertDatabaseHas('price_reference_entries', [
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'benchmark_type' => 'vendor_quote',
            'evidence_note' => 'Báo giá Công ty ABC',
        ]);
    }

    public function test_saving_a_line_without_benchmark_type_creates_no_reference_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                ],
            ],
        ]);

        $this->assertDatabaseCount('price_reference_entries', 0);
    }

    public function test_saving_twice_with_evidence_appends_two_entries_not_upsert(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $payload = [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                    'evidence_date' => '2026-07-10',
                ],
            ],
        ];

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), $payload);
        $payload['lines'][0]['unit_price'] = 1800000;
        $payload['lines'][0]['evidence_date'] = '2026-07-15';
        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), $payload);

        $this->assertDatabaseCount('price_reference_entries', 2);
    }

    public function test_evidence_is_rejected_without_crm_manage_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $admin);
        $viewer = $this->createTenantUser($tenant, [], ['member'], ['crm.view']);

        $this->actingAs($viewer)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                ],
            ],
        ])->assertStatus(302);

        $this->assertDatabaseCount('price_reference_entries', 0);
    }

    private function makeDraftQuote(Tenant $tenant, \App\Models\User $user): \App\Models\Quote
    {
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        return \App\Models\Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => \App\Models\Quote::nextNumber((string) $tenant->id),
            'revision_no' => \App\Models\Quote::nextRevision((string) $opportunity->id),
            'status' => \App\Models\Quote::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);
    }
}
