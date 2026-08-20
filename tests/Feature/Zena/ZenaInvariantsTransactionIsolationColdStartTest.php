<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

/**
 * @group zena-invariants
 *
 * Covers automated-testing.yml's zena-invariants-mysql surface. See
 * ZenaTransactionIsolationColdStartTest's docblock for why this is a
 * separate file rather than a second @group on that class.
 */
class ZenaInvariantsTransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
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
