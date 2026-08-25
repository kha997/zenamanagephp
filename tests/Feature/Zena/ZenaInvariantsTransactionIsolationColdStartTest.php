<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Depends;
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

    private const WRITER_TEST = 'test_a_writes_marker_after_proving_cold_start_invariant';
    private const VERIFIER_TEST = 'test_b_rolled_back_write_is_absent_via_independent_connection';

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        if ($this->name() === self::WRITER_TEST) {
            $this->forceGenuineColdStartForNextSetUp();
        }
        if ($this->name() === self::VERIFIER_TEST) {
            $this->captureDiscriminatingStateBeforeVerifierSetUp();
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
        $this->assertMarkerDisappearedViaRollbackNotMigrateFresh();
        $this->assertMarkerRowAbsentViaIndependentConnection($tenantId);
    }
}
