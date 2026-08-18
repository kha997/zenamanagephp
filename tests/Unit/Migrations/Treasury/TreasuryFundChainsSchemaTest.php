<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFundChainsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_fund_chains'));
        $this->assertTrue(Schema::hasColumns('treasury_fund_chains', [
            'id', 'tenant_id', 'project_id', 'chain_reference', 'description',
            'created_at', 'updated_at',
        ]));
    }
}
