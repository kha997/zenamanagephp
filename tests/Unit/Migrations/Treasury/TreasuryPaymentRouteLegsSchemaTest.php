<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryPaymentRouteLegsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_payment_route_legs'));
        $this->assertTrue(Schema::hasColumns('treasury_payment_route_legs', [
            'id', 'tenant_id', 'payment_route_id', 'sequence_no', 'from_wallet_id',
            'to_wallet_id', 'amount', 'status', 'occurred_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_positive_amount_and_allowed_status_are_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
        $route = \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);

        // Happy path: prove the trait is wired correctly for a valid leg first.
        $leg = \App\Models\Treasury\TreasuryPaymentRouteLeg::create([
            'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 1, 'to_wallet_id' => $wallet->id,
            'amount' => 100, 'status' => 'in_transit',
        ]);
        $this->assertNotNull($leg->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be > 0/');

        \App\Models\Treasury\TreasuryPaymentRouteLeg::create([
            'tenant_id' => $tenant->id, 'payment_route_id' => $route->id,
            'sequence_no' => 2, 'to_wallet_id' => $wallet->id,
            'amount' => 0, 'status' => 'in_transit',
        ]);
    }
}
