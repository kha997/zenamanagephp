<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Depends;
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

    private const WRITER_TEST = 'test_a_writes_marker_after_proving_cold_start_invariant';

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        if ($this->name() === self::WRITER_TEST) {
            $this->forceGenuineColdStartForNextSetUp();
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_writes_marker_after_proving_cold_start_invariant(): string
    {
        return $this->proveColdStartAndWriteMarker();
    }

    #[Depends(self::WRITER_TEST)]
    public function test_b_rolled_back_write_is_absent_via_independent_connection(string $tenantId): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection($tenantId);
    }
}
