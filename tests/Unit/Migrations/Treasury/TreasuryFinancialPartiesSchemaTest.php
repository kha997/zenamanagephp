<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFinancialPartiesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_financial_parties'));
        $this->assertTrue(Schema::hasColumns('treasury_financial_parties', [
            'id', 'tenant_id', 'party_type', 'name', 'linked_account_id',
            'linked_user_id', 'created_at', 'updated_at',
        ]));
    }
}
