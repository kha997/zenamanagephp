<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuoteModelTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private User $user;

    private function makeOpportunity(Tenant $tenant): Opportunity
    {
        $this->user = $this->createTenantUser($tenant, [], ['admin'], []);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        return Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);
    }

    private function createQuote(Tenant $tenant, Opportunity $opp): Quote
    {
        return Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);
    }

    public function test_quote_and_line_items_use_ulid_and_tenant_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $opp = $this->makeOpportunity($tenant);

        $quote = $this->createQuote($tenant, $opp);

        $this->assertNotEmpty($quote->id);
        $this->assertSame((string) $tenant->id, (string) $quote->tenant_id);
    }

    public function test_lines_relation_ordered_by_sort_order(): void
    {
        $tenant = Tenant::factory()->create();
        $opp = $this->makeOpportunity($tenant);

        $quote = $this->createQuote($tenant, $opp);

        // Insert out of order — lines should come back sorted
        QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 3,
            'name' => 'Line C',
            'unit' => 'm2',
            'quantity' => 10,
            'unit_price' => 50000,
            'amount' => 500000,
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 1,
            'name' => 'Line A',
            'unit' => 'm',
            'quantity' => 5,
            'unit_price' => 100000,
            'amount' => 500000,
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 2,
            'name' => 'Line B',
            'unit' => 'pcs',
            'quantity' => 2,
            'unit_price' => 200000,
            'amount' => 400000,
        ]);

        $lines = $quote->lines()->get();

        $this->assertCount(3, $lines);
        $this->assertSame('Line A', $lines[0]->name);
        $this->assertSame('Line B', $lines[1]->name);
        $this->assertSame('Line C', $lines[2]->name);
    }

    public function test_next_number_is_sequential_per_tenant_and_year(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $num1 = Quote::nextNumber((string) $tenantA->id);
        $this->assertSame('BG-' . date('Y') . '-0001', $num1);

        // Create a quote for tenantA, then next number should increment
        $opp = $this->makeOpportunity($tenantA);
        Quote::query()->create([
            'tenant_id' => (string) $tenantA->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => $num1,
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        $num2 = Quote::nextNumber((string) $tenantA->id);
        $this->assertSame('BG-' . date('Y') . '-0002', $num2);

        // Different tenant starts at 0001
        $num3 = Quote::nextNumber((string) $tenantB->id);
        $this->assertSame('BG-' . date('Y') . '-0001', $num3);
    }

    public function test_next_revision_increments_per_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $opp = $this->makeOpportunity($tenant);

        $rev1 = Quote::nextRevision((string) $opp->id);
        $this->assertSame(1, $rev1);

        // Create a quote with revision 1
        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        $rev2 = Quote::nextRevision((string) $opp->id);
        $this->assertSame(2, $rev2);
    }

    public function test_can_transition_truth_table(): void
    {
        // draft can go to sent, superseded
        $this->assertTrue(Quote::canTransition(Quote::STATUS_DRAFT, Quote::STATUS_SENT));
        $this->assertTrue(Quote::canTransition(Quote::STATUS_DRAFT, Quote::STATUS_SUPERSEDED));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_DRAFT, Quote::STATUS_ACCEPTED));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_DRAFT, Quote::STATUS_REJECTED));

        // sent can go to accepted, rejected, superseded
        $this->assertTrue(Quote::canTransition(Quote::STATUS_SENT, Quote::STATUS_ACCEPTED));
        $this->assertTrue(Quote::canTransition(Quote::STATUS_SENT, Quote::STATUS_REJECTED));
        $this->assertTrue(Quote::canTransition(Quote::STATUS_SENT, Quote::STATUS_SUPERSEDED));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_SENT, Quote::STATUS_SENT));

        // rejected can go to superseded
        $this->assertTrue(Quote::canTransition(Quote::STATUS_REJECTED, Quote::STATUS_SUPERSEDED));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_REJECTED, Quote::STATUS_ACCEPTED));

        // accepted and superseded are terminal
        $this->assertFalse(Quote::canTransition(Quote::STATUS_ACCEPTED, Quote::STATUS_SENT));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_ACCEPTED, Quote::STATUS_SUPERSEDED));
        $this->assertFalse(Quote::canTransition(Quote::STATUS_SUPERSEDED, Quote::STATUS_DRAFT));
    }

    public function test_quote_belongs_to_opportunity(): void
    {
        $tenant = Tenant::factory()->create();
        $opp = $this->makeOpportunity($tenant);

        $quote = $this->createQuote($tenant, $opp);

        $this->assertInstanceOf(Opportunity::class, $quote->opportunity);
        $this->assertSame((string) $opp->id, (string) $quote->opportunity->id);
    }

    public function test_quote_line_item_belongs_to_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $opp = $this->makeOpportunity($tenant);

        $quote = $this->createQuote($tenant, $opp);

        $line = QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 1,
            'name' => 'Test Item',
            'unit' => 'm2',
            'quantity' => 10,
            'unit_price' => 50000,
            'amount' => 500000,
        ]);

        $this->assertInstanceOf(Quote::class, $line->quote);
        $this->assertSame((string) $quote->id, (string) $line->quote->id);
    }
}
