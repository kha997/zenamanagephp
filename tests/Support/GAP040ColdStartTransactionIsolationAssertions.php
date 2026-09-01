<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared cold-start transaction-isolation regression proof. Originally
 * built for GAP-040; permanently strengthened under GAP-044 (Gate 1
 * §H1: docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md)
 * after discovering the original proof was itself a false-green — the
 * writer/verifier marker's disappearance could be explained by the
 * *verifier's own* migrate:fresh (triggered by RefreshDatabaseState::$migrated
 * being reset by the very implicit-commit defect under test), not by a
 * genuine RefreshDatabase rollback. Consumed by one test class per
 * Gate-1-approved real-MySQL surface.
 *
 * Ordering/value-passing between writer and verifier is guaranteed by
 * PHPUnit's #[Depends] attribute on the consuming test classes, not by
 * method-name convention or discovery order.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    /**
     * GAP-044: state captured by captureDiscriminatingStateBeforeVerifierSetUp(),
     * read by assertMarkerDisappearedViaRollbackNotMigrateFresh(). Reset at
     * the start of every writer run so no state leaks between consuming
     * classes sharing this trait within the same PHPUnit process.
     */
    protected static ?bool $migratedBeforeVerifierSetUp = null;
    protected static ?bool $markerVisibleBeforeVerifierSetUp = null;
    protected static ?string $lastWrittenMarkerId = null;

    /**
     * Forces the next parent::setUp() to genuinely re-run migrate:fresh
     * before opening this test's RefreshDatabase transaction, so the
     * cold-start case is deterministically observed. Call this ONLY from
     * the writer test's setUp() — never from the verifier's — per the
     * class-level doc comment above. Safe to call unconditionally: it only
     * has an effect when the active connection is MySQL, and reads the
     * connection via getenv() (set by tests/bootstrap.php before any test
     * runs) rather than config(), since the app container does not exist
     * yet at the point this must run (before parent::setUp()).
     */
    protected function forceGenuineColdStartForNextSetUp(): void
    {
        self::$migratedBeforeVerifierSetUp = null;
        self::$markerVisibleBeforeVerifierSetUp = null;
        self::$lastWrittenMarkerId = null;

        if (getenv('DB_CONNECTION') === 'mysql') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    /**
     * Writer half of the proof. Must run under a setUp() that called
     * forceGenuineColdStartForNextSetUp() first. Proves the cold-start
     * invariant with hard, non-skippable assertions on real MySQL — a
     * failure to observe genuine cold start is a real defect (the forcing
     * mechanism itself broken), not a legitimate alternate state, so it is
     * NOT swallowed as a skip. Returns the written row's id for the
     * verifier to consume via #[Depends].
     *
     * GAP-044: also asserts the same invariant across the three
     * transaction-breaking TestCase helpers fixed under GAP-044 Surface 1
     * (ensureInteractionLogsTable/ensureProjectPhasesTable/ensureProjectTasksTable),
     * using the probes added in tests/TestCase.php's gap044ProbeBeforeHelper()/
     * gap044ProbeAfterHelper().
     *
     * @group stress
     */
    protected function proveColdStartAndWriteMarker(): string
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('dependency: this proof only exercises the invariant against a real MySQL connection; this test class is also reachable from the default SQLite suite (no excluded @group), so a legitimate skip is correct there.');
        }

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        fwrite(STDERR, "\n[GAP-040/GAP-044 probe] " . json_encode($probe) . "\n");

        // GAP-042: tests/TestCase.php::ensureSqliteZenaRbacTables() — the RBAC
        // compat-schema bootstrap this proof originally instrumented — was
        // deleted as part of GAP-042's approved remediation (it manufactured
        // a production-impossible zena_roles/zena_permissions schema on top
        // of the same isolated-connection mechanism proven here). The
        // dedicated RBAC-bootstrap probe keys (table_existed_before_bootstrap,
        // transaction_level_before/after_bootstrap, bootstrap_connection_id,
        // pdo_in_transaction_before/after_bootstrap) no longer exist. The
        // underlying GAP-040/GAP-044 implicit-commit invariant remains proven
        // below via the three still-live sibling helpers
        // (interaction_logs/project_phases/project_tasks), which still use
        // the same isolated zenaRbacBootstrapSchema() second-connection
        // mechanism — GAP-042 did not remove that shared helper, only the
        // RBAC-schema-manufacturing call site (§0.4 of the GAP-042
        // implementation plan).

        // GAP-044 Surface 1: the three previously-unfixed sibling helpers
        // must exhibit the identical invariant now.
        foreach (['interaction_logs', 'project_phases', 'project_tasks'] as $helperTable) {
            $this->assertArrayHasKey(
                $helperTable,
                $probe['helpers'] ?? [],
                "No GAP-044 probe data recorded for the {$helperTable} bootstrap helper on this cold-start run — either the table already existed (proof did not exercise cold start for it) or instrumentation is missing."
            );

            $this->assertTrue(
                $probe['helpers'][$helperTable]['pdo_in_transaction_before'],
                "PDO::inTransaction() was already false before the {$helperTable} bootstrap ran."
            );

            $this->assertTrue(
                $probe['helpers'][$helperTable]['pdo_in_transaction_after'],
                "PDO::inTransaction() is false after the {$helperTable} bootstrap ran — GAP-044 Surface 1 implicit-commit defect present for this helper."
            );
        }

        $tenant = Tenant::factory()->create([
            'name' => 'gap040-cold-start-' . (string) Str::uuid(),
        ]);

        self::$lastWrittenMarkerId = (string) $tenant->id;

        return $tenant->id;
    }

    /**
     * GAP-044: must be called from the verifier test class's own setUp()
     * BEFORE parent::setUp() runs. Captures, via RefreshDatabaseState::$migrated
     * and an independent PDO read, whether a migrate:fresh is about to
     * occur in the verifier's own parent::setUp() and whether the marker
     * is already gone at this exact boundary — the discriminator the
     * original GAP-040 proof lacked (confirmed false-green in GAP-044 Gate
     * 1 §H1: docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md).
     */
    protected function captureDiscriminatingStateBeforeVerifierSetUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            return;
        }

        self::$migratedBeforeVerifierSetUp = RefreshDatabaseState::$migrated;
        self::$markerVisibleBeforeVerifierSetUp = self::$lastWrittenMarkerId !== null
            ? $this->independentPdoSeesTenant(self::$lastWrittenMarkerId)
            : null;
    }

    /**
     * GAP-044: asserts the marker's eventual disappearance is attributable
     * to the writer's own RefreshDatabase rollback specifically, not to
     * the verifier's own parent::setUp() running migrate:fresh. Must be
     * called from the verifier test method, after
     * captureDiscriminatingStateBeforeVerifierSetUp() ran in that same
     * test's setUp(). Do NOT accept "marker absent after verifier setup"
     * alone as sufficient proof — that is exactly the false-green mode
     * this method exists to rule out.
     */
    protected function assertMarkerDisappearedViaRollbackNotMigrateFresh(): void
    {
        $this->assertNotNull(
            self::$migratedBeforeVerifierSetUp,
            'captureDiscriminatingStateBeforeVerifierSetUp() was not called from this test class\'s setUp() before parent::setUp() — cannot distinguish rollback from migrate:fresh without it.'
        );

        $this->assertTrue(
            self::$migratedBeforeVerifierSetUp,
            'RefreshDatabaseState::$migrated was false immediately before the verifier\'s own parent::setUp() ran — a migrate:fresh was about to execute and could explain the marker\'s disappearance via schema wipe rather than genuine rollback. This is the exact false-green mode confirmed in GAP-044 Gate 1 (docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md §H1).'
        );

        $this->assertFalse(
            self::$markerVisibleBeforeVerifierSetUp,
            'The marker row was still visible via independent PDO immediately before the verifier\'s own parent::setUp() ran, meaning it had not yet disappeared at that point — its later absence (if observed) cannot be attributed with confidence to the writer\'s teardown rollback.'
        );
    }

    /**
     * Verifier half of the proof. Must run under a setUp() that did NOT
     * call forceGenuineColdStartForNextSetUp() — no migrate:fresh, no
     * truncate, no reset of any kind may be *forced* between the writer's
     * teardown and this read (GAP-044's captureDiscriminatingStateBeforeVerifierSetUp()
     * independently verifies none *happened*, rather than merely not
     * forcing one). Queries via a brand-new, non-persistent PDO connection
     * — never a Laravel-managed connection — so the read cannot be
     * satisfied by in-process transaction visibility artifacts. A
     * missing/empty $tenantId is a hard failure (fail closed), never a
     * skip: PHPUnit's #[Depends] on the consuming test class is what
     * supplies it, and a broken dependency there is itself a defect worth
     * surfacing loudly, not hiding.
     *
     * @group stress
     */
    protected function assertMarkerRowAbsentViaIndependentConnection(string $tenantId): void
    {
        $this->assertNotSame('', $tenantId, 'No marker tenant id was supplied by the writer test — the #[Depends] value-passing itself is broken.');

        $this->assertFalse(
            $this->independentPdoSeesTenant($tenantId),
            'A fresh, independent PDO connection (not reusing any Laravel-managed connection) still finds the cold-start test row — RefreshDatabase rollback did not take effect.'
        );
    }

    /**
     * GAP-044: reads connection parameters via getenv() rather than
     * config(), because captureDiscriminatingStateBeforeVerifierSetUp()
     * must call this BEFORE parent::setUp() runs — at which point the
     * Laravel application container does not exist yet, so config() is
     * unavailable (BindingResolutionException: Target class [config] does
     * not exist). Same reasoning as forceGenuineColdStartForNextSetUp()'s
     * own use of getenv() above. Falls back to the same defaults the CI
     * jobs' MySQL service containers use.
     */
    private function independentPdoSeesTenant(string $tenantId): bool
    {
        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_DATABASE') ?: 'zenamanage_test'
            ),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') ?: '',
            [\PDO::ATTR_PERSISTENT => false, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE id = ?');
        $stmt->execute([$tenantId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
