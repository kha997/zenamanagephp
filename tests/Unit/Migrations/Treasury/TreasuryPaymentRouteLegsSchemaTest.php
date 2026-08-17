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
}
