<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuoteCommercialEndpointTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    private function makeQuote(Tenant $tenant, array $lineAmounts = [1000000]): Quote
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Acme Corp',
        ]);

        $opp = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Commercial Test Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        $quote = Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opp->id),
            'status' => Quote::STATUS_DRAFT,
            'created_by' => (string) $user->id,
            'subtotal' => (float) array_sum($lineAmounts),
        ]);

        foreach ($lineAmounts as $i => $amount) {
            QuoteLineItem::query()->create([
                'tenant_id' => (string) $tenant->id,
                'quote_id' => (string) $quote->id,
                'sort_order' => $i + 1,
                'name' => "Item {$i}",
                'unit' => 'm2',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
            ]);
        }

        return $quote->fresh();
    }

    public function test_save_commercial_returns_200_and_updates_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, [20000000]);

        $response = $this->post(route('operator.crm.quotes.commercial', $quote->id), [
            'discount_percent' => 10,
            'vat_percent' => 8,
            'payment_terms' => 'Net 30',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $dbQuote = Quote::find($quote->id);
        $this->assertEqualsWithDelta(10, $dbQuote->discount_percent, 0.01);
        $this->assertEqualsWithDelta(8, $dbQuote->vat_percent, 0.01);
        $this->assertEqualsWithDelta(2000000, $dbQuote->discount_amount, 0.01);   // 10% of 20M
        $this->assertEqualsWithDelta(1440000, $dbQuote->vat_amount, 0.01);         // 8% of 18M
        $this->assertEqualsWithDelta(19440000, $dbQuote->total, 0.01);            // 18M + 1.44M
        $this->assertSame('Net 30', $dbQuote->payment_terms);
    }

    public function test_team_member_without_manage_cannot_save_commercial(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['team_member'], ['crm.view']);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant);

        $this->post(route('operator.crm.quotes.commercial', $quote->id), [
            'discount_percent' => 5,
        ])->assertStatus(302);
    }

    public function test_validation_rejects_discount_over_100(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant);

        $this->post(route('operator.crm.quotes.commercial', $quote->id), [
            'discount_percent' => 150,
        ])->assertSessionHasErrors('discount_percent');
    }

    public function test_send_recomputes_commercial_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, [10000000]);

        // Set commercial fields first
        $this->post(route('operator.crm.quotes.commercial', $quote->id), [
            'discount_percent' => 20,
            'vat_percent' => 10,
        ]);

        // Save lines (triggers recompute)
        $this->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                ['name' => 'New item', 'unit' => 'm2', 'quantity' => 1, 'unit_price' => 10000000],
            ],
        ]);

        $dbQuote = Quote::find($quote->id);
        // subtotal=10M, discount=20% → 2M, taxable=8M, vat=10% → 800K, total=8.8M
        $this->assertEqualsWithDelta(2000000, $dbQuote->discount_amount, 0.01);
        $this->assertEqualsWithDelta(800000, $dbQuote->vat_amount, 0.01);
        $this->assertEqualsWithDelta(8800000, $dbQuote->total, 0.01);
    }

    public function test_revise_copies_commercial_fields_and_recomputes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($user);

        $original = $this->makeQuote($tenant, [5000000]);

        $this->post(route('operator.crm.quotes.commercial', $original->id), [
            'discount_percent' => 10,
            'vat_percent' => 8,
            'payment_terms' => 'Net 45',
        ]);

        $this->post(route('operator.crm.quotes.send', $original->id));
        $this->post(route('operator.crm.quotes.revise', $original->id));

        $newQuote = Quote::query()
            ->where('opportunity_id', $original->opportunity_id)
            ->where('revision_no', 2)
            ->first();

        $this->assertNotNull($newQuote);
        $this->assertEqualsWithDelta(10, $newQuote->discount_percent, 0.01);
        $this->assertEqualsWithDelta(8, $newQuote->vat_percent, 0.01);
        $this->assertSame('Net 45', $newQuote->payment_terms);

        // Recomputed: subtotal=5M, discount=500K, taxable=4.5M, vat=360K, total=4.86M
        $this->assertEqualsWithDelta(500000, $newQuote->discount_amount, 0.01);
        $this->assertEqualsWithDelta(360000, $newQuote->vat_amount, 0.01);
        $this->assertEqualsWithDelta(4860000, $newQuote->total, 0.01);
    }

    public function test_cross_tenant_cannot_save_commercial(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $quote = $this->makeQuote($tenantA);

        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($userB);

        $this->post(route('operator.crm.quotes.commercial', $quote->id), [
            'discount_percent' => 5,
        ])->assertSessionHas('error');

        // Verify data unchanged via raw query (bypasses any model scopes)
        $raw = \Illuminate\Support\Facades\DB::table('quotes')->where('id', $quote->id)->first();
        $this->assertEqualsWithDelta(0, $raw->discount_percent, 0.01);
        $this->assertEqualsWithDelta(0, $raw->vat_percent, 0.01);
    }
}
