<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaAuditSchemaInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_table_has_required_audit_columns(): void
    {
        $tableName = 'audit_logs';
        $requiredColumns = ['route', 'method', 'status_code', 'meta'];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn($tableName, $column),
                "Audit log table must include the '{$column}' column; run the latest migrations if it is missing."
            );
        }
    }
}
