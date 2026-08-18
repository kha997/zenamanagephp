<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Independent schema-introspection proof (GAP-038 Gate 2 Option B, per the
 * Owner's REQUEST CHANGES correction): confirms every approved CHECK
 * constraint physically exists in the database's own schema metadata --
 * not merely that a violating write happens to be rejected, which a
 * trigger would also achieve. This test does not accept
 * TreasuryCheckConstraint's own claim about what it created; it queries
 * the database engine's system catalog directly.
 */
class TreasuryCheckConstraintIntrospectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<string>> table => expected CHECK constraint names */
    private function expectedChecks(): array
    {
        return [
            'treasury_financial_documents' => [
                'tfd_amount_positive_chk', 'tfd_source_mutex_chk', 'tfd_destination_mutex_chk',
            ],
            'treasury_payment_routes' => [
                'tpr_amount_positive_chk', 'tpr_link_exactly_one_chk', 'tpr_contract_wallet_conullable_chk',
            ],
            'treasury_payment_route_legs' => ['tprl_amount_positive_chk'],
            'treasury_ledger_entries' => ['tle_amount_positive_chk', 'tle_source_exactly_one_chk'],
            'treasury_cost_settlement_allocations' => [
                'tcsa_amount_positive_chk', 'tcsa_source_exactly_one_chk', 'tcsa_cost_source_exactly_one_chk',
            ],
            'treasury_advances' => ['ta_amount_positive_chk'],
            'treasury_advance_settlements' => ['tas_amount_positive_chk'],
            'treasury_fund_chain_members' => ['tfcm_member_exactly_one_chk'],
        ];
    }

    public function test_every_approved_check_constraint_physically_exists_in_sqlite_schema(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific schema introspection; see the MySQL-path equivalent verified via CI SHOW CREATE TABLE / information_schema.');
        }

        foreach ($this->expectedChecks() as $table => $checkNames) {
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
            $this->assertNotNull($row, "table {$table} does not exist");

            foreach ($checkNames as $name) {
                $this->assertMatchesRegularExpression(
                    '/CONSTRAINT ["\']'.preg_quote($name, '/').'["\']\s+CHECK/',
                    $row->sql,
                    "expected a real CHECK constraint named {$name} in {$table}'s CREATE TABLE SQL, found none"
                );
            }
        }
    }

    public function test_no_trigger_based_enforcement_exists_on_any_treasury_table(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific: confirms Option B correction removed the earlier trigger-based substitute.');
        }

        foreach (array_keys($this->expectedChecks()) as $table) {
            $count = DB::selectOne(
                "SELECT COUNT(*) AS c FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ?",
                [$table]
            )->c;

            $this->assertSame(
                0,
                (int) $count,
                "expected zero triggers on {$table} -- native CHECK constraints must be the enforcement mechanism, not triggers"
            );
        }
    }

    public function test_mysql_check_constraints_are_visible_in_information_schema(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-specific information_schema introspection; runs for real under this repository\'s automated-testing.yml CI (DB_CONNECTION=mysql).');
        }

        foreach ($this->expectedChecks() as $table => $checkNames) {
            foreach ($checkNames as $name) {
                $row = DB::selectOne(
                    'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '.
                    'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
                    [$name]
                );

                $this->assertNotNull(
                    $row,
                    "expected a real MySQL CHECK constraint named {$name} on {$table}, found none in information_schema.CHECK_CONSTRAINTS"
                );
            }
        }
    }
}
