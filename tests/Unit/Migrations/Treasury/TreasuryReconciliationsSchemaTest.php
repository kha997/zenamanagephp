<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryReconciliationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_reconciliations'));
        $this->assertTrue(Schema::hasColumns('treasury_reconciliations', [
            'id', 'tenant_id', 'wallet_id', 'reconciliation_type', 'external_reference',
            'reconciled_at', 'reconciled_by', 'created_at', 'updated_at',
        ]));
    }
}
