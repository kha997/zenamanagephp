<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

/**
 * @group mysql-parity
 *
 * Covers routes-guardrails.yml's `--group=mysql-parity` surface only. A
 * second file, ZenaInvariantsTransactionIsolationColdStartTest, covers
 * zena-invariants-mysql — PHPUnit's group-exclude mechanism only cancels
 * an exclude for a group actually passed via --group, so a single test
 * carrying both `mysql-parity` (excluded by default in phpunit.xml) and
 * `zena-invariants` (not excluded) is still dropped by --group=zena-invariants
 * runs, which never mentions mysql-parity to cancel that exclude.
 */
class ZenaTransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        $this->forceGenuineColdStartForNextSetUp();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_cold_start_bootstrap_does_not_break_transaction_isolation(): void
    {
        $this->assertColdStartInvariantHeld();
        $this->writeMarkerRow();
    }

    public function test_b_rolled_back_write_is_absent_via_independent_connection(): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection();
    }
}
