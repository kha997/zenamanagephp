<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;

/**
 * GAP-044 DISPOSABLE DISCRIMINATING HARNESS.
 *
 * NOT a fix. NOT implementation. NOT for merge. Never touches any GAP-040
 * file. Self-contained: forces its own cold start via the same public
 * Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated mechanism
 * GAP-040's own trait uses, without depending on or modifying that trait.
 *
 * Purpose: discriminate between two possible explanations for why GAP-040's
 * already-Owner-approved cold-start rollback proof reports the writer's
 * marker row absent at the verifier stage:
 *
 *   (a) VALID — RefreshDatabase's real transaction rollback removed it, or
 *   (b) FALSE-GREEN — ensureInteractionLogsTable()/ensureProjectPhasesTable()/
 *       ensureProjectTasksTable() (unfixed GAP-044 siblings, already present
 *       at the GAP-040 baseline) implicit-committed the writer's transaction,
 *       RefreshDatabase's own self-healing check then set
 *       RefreshDatabaseState::$migrated = false, and the *verifier's* own
 *       parent::setUp() ran migrate:fresh, which wiped the marker via schema
 *       reset — not via any real rollback.
 *
 * All state needed to make this determination is captured via independent,
 * non-Laravel-managed raw PDO reads (env-var-driven, not config()-driven,
 * so they work even before this test's own setUp() has booted the Laravel
 * app) at each of the boundaries the Owner's review specified.
 *
 * @group mysql-parity
 */
class GAP044Gap040ProofDiscriminatorTest extends TestCase
{
    use RefreshDatabase;

    private static ?bool $migratedBeforeVerifierSetUp = null;
    private static ?bool $markerVisibleBeforeVerifierSetUp = null;

    protected function setUp(): void
    {
        if ($this->name() === 'test_a_writer' || $this->name() === 'test_c_capture_masked_exception') {
            // Force genuine cold start, identical technique to GAP-040's own
            // forceGenuineColdStartForNextSetUp() — but implemented here
            // independently so no GAP-040 file is touched. Forcing this
            // deterministically reproduces the implicit-commit condition
            // regardless of true process-level test ordering (migrate:fresh
            // drops ALL tables, including the migration-less
            // interaction_logs/project_phases/project_tasks, so they are
            // missing again on entry to this test's own setUp()).
            RefreshDatabaseState::$migrated = false;
        }

        if ($this->name() === 'test_b_verifier') {
            // Capture state BEFORE this test's own parent::setUp() runs —
            // this is the exact boundary the Owner's review requires: what
            // is true right before the verifier's setUp (and therefore
            // right before any migrate:fresh Laravel's own RefreshDatabase
            // trait might decide to run) executes.
            self::$migratedBeforeVerifierSetUp = RefreshDatabaseState::$migrated;

            $tenantId = $this->readMarkerFile();
            self::$markerVisibleBeforeVerifierSetUp = $tenantId !== null
                ? $this->independentPdoSeesTenant($tenantId)
                : null;

            $this->rawLog(sprintf(
                'label=before_verifier_parent_setUp migrated=%s marker_visible=%s',
                self::$migratedBeforeVerifierSetUp === null ? 'null' : (self::$migratedBeforeVerifierSetUp ? 'true' : 'false'),
                self::$markerVisibleBeforeVerifierSetUp === null ? 'null' : (self::$markerVisibleBeforeVerifierSetUp ? 'true' : 'false')
            ));
        }

        parent::setUp();

        if ($this->name() === 'test_b_verifier') {
            $this->probe('after_verifier_parent_setUp');
        }
    }

    public function test_a_writer(): string
    {
        $this->probe('after_full_writer_parent_setUp');

        $tenant = Tenant::factory()->create([
            'name' => 'gap044-disc-' . (string) Str::uuid(),
        ]);
        $tenantId = (string) $tenant->id;

        $this->probe('after_marker_insert');

        $visibleImmediately = $this->independentPdoSeesTenant($tenantId);
        $this->rawLog(sprintf(
            'label=independent_pdo_before_writer_teardown tenant_id=%s marker_visible=%s',
            $tenantId,
            $visibleImmediately ? 'true' : 'false'
        ));

        $this->writeMarkerFile($tenantId);

        return $tenantId;
    }

    #[Depends('test_a_writer')]
    public function test_b_verifier(string $tenantId): void
    {
        $visibleAfterVerifierSetUp = $this->independentPdoSeesTenant($tenantId);

        $migratedBefore = self::$migratedBeforeVerifierSetUp;
        $markerVisibleBefore = self::$markerVisibleBeforeVerifierSetUp;

        $conclusion = 'INDETERMINATE';
        if ($markerVisibleBefore === false) {
            // Already gone before the verifier's own parent::setUp() ran at
            // all (i.e. before any migrate:fresh the verifier's own setUp
            // could trigger) -> disappearance is attributable to something
            // that happened during/after the WRITER's own teardown, not to
            // the verifier's migrate:fresh.
            $conclusion = 'DISAPPEARED_BEFORE_VERIFIER_SETUP_ie_WRITER_TEARDOWN_OR_EARLIER';
        } elseif ($markerVisibleBefore === true && $visibleAfterVerifierSetUp === false) {
            $conclusion = $migratedBefore === false
                ? 'DISAPPEARED_DURING_VERIFIER_SETUP_WITH_MIGRATED_FALSE_ie_LIKELY_MIGRATE_FRESH'
                : 'DISAPPEARED_DURING_VERIFIER_SETUP_WITH_MIGRATED_TRUE_ie_NOT_MIGRATE_FRESH';
        } elseif ($markerVisibleBefore === true && $visibleAfterVerifierSetUp === true) {
            $conclusion = 'STILL_VISIBLE_AFTER_VERIFIER_SETUP_ie_NEVER_REMOVED_BY_EITHER_MECHANISM';
        }

        $this->rawLog(sprintf(
            'label=verifier_summary tenant_id=%s migrated_before_verifier_setUp=%s marker_visible_before_verifier_setUp=%s marker_visible_after_verifier_setUp=%s CONCLUSION=%s',
            $tenantId,
            $migratedBefore === null ? 'null' : ($migratedBefore ? 'true' : 'false'),
            $markerVisibleBefore === null ? 'null' : ($markerVisibleBefore ? 'true' : 'false'),
            $visibleAfterVerifierSetUp ? 'true' : 'false',
            $conclusion
        ));

        @unlink($this->markerFilePath());

        $this->assertTrue(true, 'This harness only emits evidence; it does not assert GAP-040 pass/fail.');
    }

    /**
     * PART B — capture the masked original exception.
     *
     * Reproduces the exact TenantUserFactoryTrait::createTenantUser() ->
     * assignApiRoles() -> ensurePermissionAttached() -> Permission::
     * firstOrCreate() sequence (App\Models\User/Role/Permission — same
     * models, same table names, same call shape), but WITHOUT delegating to
     * Eloquent's built-in firstOrCreate()/createOrFirst()/
     * withSavepointIfNeeded() (which we cannot instrument without editing
     * vendor code). Instead this method manually opens the same savepoint
     * Eloquent would (via DB::transaction()) and, critically, catches the
     * ORIGINAL closure exception in its own try/catch BEFORE attempting any
     * rollback — so the true original Throwable is captured unmasked,
     * before Laravel's own rollback-to-savepoint attempt would normally
     * replace it with the secondary "SAVEPOINT trans2 does not exist"
     * PDOException. A second, separate try/catch then attempts the same
     * rollback Laravel would, purely to confirm the masking would indeed
     * occur under the standard path — for transparency, not to hide it.
     */
    public function test_c_capture_masked_exception(): void
    {
        $this->probe('capture_exception_after_full_setUp');

        $user = \App\Models\User::factory()->create([
            'tenant_id' => (string) \App\Models\Tenant::factory()->create()->id,
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_active' => true,
        ]);

        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'system', 'description' => 'Super Admin', 'is_active' => true]
        );
        $user->roles()->syncWithoutDetaching($role->id);

        // Exact same permission set TenantUserFactoryTrait::assignApiRoles()
        // uses by default: ['project.read', 'project.write'].
        foreach (['project.read', 'project.write'] as $permissionName) {
            $parts = explode('.', $permissionName);
            $attributes = ['name' => $permissionName];
            $values = [
                'code' => $permissionName,
                'module' => $parts[0] ?? $permissionName,
                'action' => $parts[1] ?? '*',
                'description' => ucfirst(str_replace('.', ' ', $permissionName)),
            ];

            $existing = \App\Models\Permission::where($attributes)->first();

            if ($existing !== null) {
                $this->rawLog(sprintf(
                    'label=permission_already_existed permission=%s (createOrFirst path would NOT have been entered here)',
                    $permissionName
                ));
                continue;
            }

            $originalExceptionClass = null;
            $originalExceptionMessage = null;
            $rollbackExceptionClass = null;
            $rollbackExceptionMessage = null;

            // Mirror Eloquent's withSavepointIfNeeded(): only wrap in a
            // transaction (savepoint, since transactionLevel() > 0 here) if
            // already inside a transaction — identical precondition check.
            $useSavepoint = \Illuminate\Support\Facades\DB::transactionLevel() > 0;

            if ($useSavepoint) {
                \Illuminate\Support\Facades\DB::connection()->beginTransaction();
            }

            try {
                \App\Models\Permission::create(array_merge($attributes, $values));
                if ($useSavepoint) {
                    \Illuminate\Support\Facades\DB::connection()->commit();
                }
                $this->rawLog(sprintf('label=permission_create_succeeded permission=%s', $permissionName));
                continue;
            } catch (\Throwable $original) {
                // ORIGINAL, UNMASKED exception — captured before any
                // rollback attempt, exactly as required.
                $originalExceptionClass = get_class($original);
                $originalExceptionMessage = $original->getMessage();
            }

            if ($useSavepoint) {
                try {
                    \Illuminate\Support\Facades\DB::connection()->rollBack();
                } catch (\Throwable $rollbackError) {
                    $rollbackExceptionClass = get_class($rollbackError);
                    $rollbackExceptionMessage = $rollbackError->getMessage();
                }
            }

            $this->rawLog(sprintf(
                'label=masked_exception_capture permission=%s original_class=%s original_message=%s rollback_class=%s rollback_message=%s',
                $permissionName,
                $originalExceptionClass ?? 'null',
                str_replace("\n", ' ', (string) $originalExceptionMessage),
                $rollbackExceptionClass ?? 'null',
                str_replace("\n", ' ', (string) $rollbackExceptionMessage)
            ));
        }

        $this->assertTrue(true, 'This harness only emits evidence; it does not assert GAP-044 pass/fail.');
    }

    private function probe(string $label): void
    {
        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $connId = (int) \Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->rawLog(sprintf(
                'label=%s transactionLevel=%d pdoInTransaction=%s connectionId=%d tenants_exists=%s interaction_logs_exists=%s migrated_flag=%s',
                $label,
                \Illuminate\Support\Facades\DB::transactionLevel(),
                $pdo->inTransaction() ? 'true' : 'false',
                $connId,
                \Illuminate\Support\Facades\Schema::hasTable('tenants') ? 'true' : 'false',
                \Illuminate\Support\Facades\Schema::hasTable('interaction_logs') ? 'true' : 'false',
                RefreshDatabaseState::$migrated ? 'true' : 'false'
            ));
        } catch (\Throwable $e) {
            $this->rawLog(sprintf('label=%s PROBE_ERROR=%s', $label, $e->getMessage()));
        }
    }

    /**
     * Independent, non-Laravel-managed raw PDO connection, driven purely by
     * env vars (not config()), so it works even from inside setUp() before
     * this test's own Laravel app instance has been (re)booted.
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

    private function markerFilePath(): string
    {
        return sys_get_temp_dir() . '/gap044-disc-marker.txt';
    }

    private function writeMarkerFile(string $tenantId): void
    {
        file_put_contents($this->markerFilePath(), $tenantId);
    }

    private function readMarkerFile(): ?string
    {
        $path = $this->markerFilePath();

        if (!file_exists($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        return $value === '' ? null : $value;
    }

    private function rawLog(string $message): void
    {
        fwrite(STDERR, '[GAP044-DISC] test=' . static::class . '::' . $this->name() . ' ' . $message . "\n");
    }
}
