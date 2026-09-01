<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuoteToContractTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    private function headersFor(User $user): array
    {
        $token = $user->createToken('quote-contract-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function makeOpportunity(Tenant $tenant, ?User $user = null): array
    {
        $user ??= $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage', 'crm.convert', 'contract.create']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'KH Test',
        ]);

        $opp = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Du an Test',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        // GAP-048 §12/§13 — createContract() is now gated on >=1 CONFIRMED
        // canonical Service Line.
        $opp->serviceLines()->create([
            'service_line' => \App\Support\ServiceLine::DESIGN,
            'provenance' => \App\Support\ServiceLineProvenance::CONFIRMED,
        ]);

        return ['tenant' => $tenant, 'user' => $user, 'opportunity' => $opp, 'account' => $account];
    }

    private function makeAcceptedQuote(Tenant $tenant, Opportunity $opp, array $lines = []): Quote
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $quote = Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opp->id),
            'status' => Quote::STATUS_ACCEPTED,
            'subtotal' => 0,
            'decided_at' => now(),
            'created_by' => (string) $user->id,
        ]);

        $subtotal = 0.0;
        foreach ($lines as $i => $line) {
            $amount = ($line['quantity'] ?? 10) * ($line['unit_price'] ?? 100000);
            QuoteLineItem::query()->create([
                'tenant_id' => (string) $tenant->id,
                'quote_id' => (string) $quote->id,
                'sort_order' => $i + 1,
                'code' => $line['code'] ?? "L{$i}",
                'name' => $line['name'] ?? "Item {$i}",
                'unit' => $line['unit'] ?? 'm2',
                'quantity' => $line['quantity'] ?? 10,
                'unit_price' => $line['unit_price'] ?? 100000,
                'amount' => $amount,
                'price_note' => $line['price_note'] ?? null,
            ]);
            $subtotal += $amount;
        }

        $quote->update(['subtotal' => $subtotal]);

        // Set total = subtotal when no commercial fields (backward compat)
        if ((float) $quote->total === 0.0) {
            $quote->update(['total' => $subtotal]);
        }

        return $quote;
    }

    private function createContract(User $user, string $oppId): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headersFor($user))
            ->postJson('/api/zena/crm/opportunities/' . $oppId . '/create-contract');
    }

    // ─── Happy path: native quote → contract + BOQ ──────────────────

    public function test_native_accepted_quote_creates_contract_with_boq(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp, 'account' => $account] = $this->makeOpportunity($tenant);

        $quote = $this->makeAcceptedQuote($tenant, $opp, [
            ['code' => 'L001', 'name' => 'Son', 'unit' => 'm2', 'quantity' => 100, 'unit_price' => 200000],
            ['code' => 'L002', 'name' => 'Keo', 'unit' => 'kg', 'quantity' => 5, 'unit_price' => 1500000],
        ]);

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(201);

        $contract = Contract::query()
            ->where('source_opportunity_id', (string) $opp->id)
            ->first();

        $this->assertNotNull($contract);
        $this->assertSame((string) $quote->id, (string) $contract->source_quote_id);
        $this->assertSame($quote->revision_no, $contract->source_quote_revision);
        $this->assertEqualsWithDelta(27500000, $contract->total_value, 0.01);
        $this->assertSame('KH Test', $contract->client_name);

        // Check BOQ was created
        $boq = Boq::query()->where('contract_id', (string) $contract->id)->first();
        $this->assertNotNull($boq);
        $this->assertSame('KH Test', $boq->name);

        // Check BOQ lines match quote lines
        $boqLines = BoqLineItem::query()
            ->where('boq_id', (string) $boq->id)
            ->orderBy('code')
            ->get();

        $this->assertCount(2, $boqLines);

        $son = $boqLines->firstWhere('code', 'L001');
        $this->assertNotNull($son);
        $this->assertSame('Son', $son->name);
        $this->assertSame('m2', $son->unit);
        $this->assertEqualsWithDelta(100, $son->quantity, 0.01);
        $this->assertEqualsWithDelta(200000, $son->unit_price, 0.01);

        $keo = $boqLines->firstWhere('code', 'L002');
        $this->assertNotNull($keo);
        $this->assertSame('Keo', $keo->name);
        $this->assertSame('kg', $keo->unit);
        $this->assertEqualsWithDelta(5, $keo->quantity, 0.01);
        $this->assertEqualsWithDelta(1500000, $keo->unit_price, 0.01);
    }

    // ─── Idempotent: second call returns existing contract ──────────

    public function test_native_quote_contract_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $this->makeAcceptedQuote($tenant, $opp, [
            ['code' => 'L001', 'name' => 'A', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 100000],
        ]);

        $first = $this->createContract($user, (string) $opp->id);
        $first->assertStatus(201);

        $second = $this->createContract($user, (string) $opp->id);
        $second->assertOk(); // Returns 200 with existing contract message

        $this->assertCount(1, Contract::query()->where('source_opportunity_id', (string) $opp->id)->get());
    }

    // ─── External-only still works ──────────────────────────────────

    public function test_external_only_quote_still_works(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $opp->update([
            'external_quote_id' => 'ext_quote_1',
            'external_quote_snapshot' => [
                'revision' => 1,
                'total' => 50000000,
                'status' => 'ACCEPTED',
            ],
        ]);

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(201);

        $contract = Contract::query()
            ->where('source_opportunity_id', (string) $opp->id)
            ->first();

        $this->assertNotNull($contract);
        $this->assertSame('ext_quote_1', $contract->source_quote_id);
        $this->assertSame(50000000.0, (float) $contract->total_value);
    }

    // ─── Native takes priority over external ────────────────────────

    public function test_native_takes_priority_when_both_exist(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        // Set external quote
        $opp->update([
            'external_quote_id' => 'ext_quote_2',
            'external_quote_snapshot' => [
                'revision' => 1,
                'total' => 50000000,
                'status' => 'ACCEPTED',
            ],
        ]);

        // Also create native accepted quote
        $quote = $this->makeAcceptedQuote($tenant, $opp, [
            ['code' => 'N001', 'name' => 'Native Item', 'unit' => 'pcs', 'quantity' => 10, 'unit_price' => 1000000],
        ]);

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(201);

        $contract = Contract::query()
            ->where('source_opportunity_id', (string) $opp->id)
            ->first();

        $this->assertNotNull($contract);
        // Native quote should take priority
        $this->assertSame((string) $quote->id, (string) $contract->source_quote_id);
        $this->assertEqualsWithDelta(10000000, $contract->total_value, 0.01);

        // BOQ should exist (native path creates BOQ)
        $boq = Boq::query()->where('contract_id', (string) $contract->id)->first();
        $this->assertNotNull($boq);
    }

    // ─── Both missing → error ───────────────────────────────────────

    public function test_no_quote_no_external_returns_error(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(422);
    }

    // ─── Native quote in non-accepted status → error ───────────────

    public function test_draft_native_quote_returns_error(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $user2 = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 1000000,
            'created_by' => (string) $user2->id,
        ]);

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(422);
    }

    // ─── Contract total_value uses quote total (not subtotal) ──────

    public function test_contract_uses_quote_total_not_subtotal(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $quote = $this->makeAcceptedQuote($tenant, $opp, [
            ['code' => 'L001', 'name' => 'Son', 'unit' => 'm2', 'quantity' => 100, 'unit_price' => 200000],
            ['code' => 'L002', 'name' => 'Keo', 'unit' => 'kg', 'quantity' => 5, 'unit_price' => 1500000],
        ]);

        // subtotal=27500000, discount 10%, vat 8% → total=26730000
        $totals = Quote::computeTotals(27500000, 10, 8);
        $quote->update(array_merge([
            'discount_percent' => 10,
            'vat_percent' => 8,
        ], $totals));

        $response = $this->createContract($user, (string) $opp->id);
        $response->assertStatus(201);

        $contract = Contract::query()
            ->where('source_opportunity_id', (string) $opp->id)
            ->first();

        $this->assertNotNull($contract);
        // total_value must be 26,730,000 (grand total), NOT 27,500,000 (subtotal)
        $this->assertEqualsWithDelta(26730000, $contract->total_value, 0.01);
    }
}
