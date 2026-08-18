<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryExpenseApprovalsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_expense_approvals'));
        $this->assertTrue(Schema::hasColumns('treasury_expense_approvals', [
            'id', 'tenant_id', 'financial_document_id', 'event', 'from_status',
            'to_status', 'actor_id', 'note', 'context', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_expense_approvals', 'updated_at'));
    }
}
