<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Proves that RfiEscalationService::escalate()/resolveEscalation() serialize
 * concurrent access via a real row lock (SELECT ... FOR UPDATE on the parent
 * `rfis` row) — not merely an application-level state check.
 *
 * This MUST run against a real MySQL connection with two genuinely independent
 * OS processes/PDO connections racing against each other. Sequential in-process
 * calls against sqlite (the default test DB) cannot exercise cross-connection
 * row locking and would be a false proof of blocker #5. If the `mysql`
 * connection defined in config/database.php is not reachable in this
 * environment, both tests below skip themselves with an explicit message
 * rather than silently passing on sqlite.
 *
 * @group stress
 */
class RfiEscalationConcurrencyTest extends TestCase
{
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove real row-locking behavior, '
                . 'not sqlite. The "mysql" connection in config/database.php is not reachable in '
                . 'this environment (' . $e->getMessage() . '). Run this suite in an environment '
                . 'with MySQL configured before treating concurrency as verified — a passing '
                . 'sqlite/sequential-call test is NOT evidence per the plan\'s blocker #5.'
            );
        }
    }

    protected function tearDown(): void
    {
        // Guarded with try/catch (not just a getPdo() truthiness check): when MySQL is
        // unreachable the test method already skipped itself in skipUnlessMysqlAvailable(),
        // but PHPUnit still runs tearDown() afterwards — without the guard, attempting to
        // connect here would throw and turn a clean SKIP into a misleading ERROR.
        try {
            if (DB::connection('mysql')->getPdo()) {
                DB::connection('mysql')->table('rfi_escalations')->delete();
                DB::connection('mysql')->table('rfis')->delete();
                DB::connection('mysql')->table('projects')->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment — nothing to clean up.
        }
        parent::tearDown();
    }

    private function makeRfiOnMysql(Tenant $tenant, Project $project, User $creator, string $rfiNumber): Rfi
    {
        return Rfi::on('mysql')->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Concurrency test',
            'subject' => 'Concurrency test subject',
            'description' => 'd',
            'question' => 'What is the answer?',
            'priority' => 'medium',
            'status' => 'open',
            'asked_by' => $creator->id,
            'created_by' => $creator->id,
            'rfi_number' => $rfiNumber,
        ]);
    }

    /**
     * NOTE on test design: an earlier draft of this test held the RFI row lock open on an
     * external raw-PDO connection, started ONE subprocess, and asserted it was still
     * `isRunning()` after a short sleep before releasing the lock. A deliberate sabotage run
     * (temporarily dropping the initial `lockForUpdate()` in `RfiEscalationService::escalate()`
     * and re-running this test) showed that design was NOT a reliable proof: the sabotaged
     * subprocess still measured as "blocked" for the full hold duration, because the trailing
     * `$lockedRfi->update([...])` write at the end of escalate() always needs to acquire an
     * exclusive lock on the row regardless of whether the initial read used `lockForUpdate()` —
     * so timing alone couldn't distinguish "the documented read-lock mechanism is working" from
     * "some other write in the method happens to serialize against this specific external lock".
     *
     * This test instead proves the property that actually matters — exactly one of two
     * genuinely concurrent `escalate()` calls against the SAME unescalated RFI succeeds — by
     * starting two real, independent OS subprocesses at the same time and asserting on the
     * outcome (one OK, one CONFLICT, exactly one escalation row), which cannot hold under a
     * true race without correct locking, independent of any timing assumptions.
     */
    public function test_two_concurrent_escalate_calls_on_the_same_rfi_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $escalator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $target = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id, 'is_active' => true]));

        $rfi = $this->makeRfiOnMysql($tenant, $project, $escalator, 'CONC-RFI-0001');

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([
            $php, 'artisan', 'rfi:concurrency-test-escalate', $rfi->id, $target->id, $escalator->id, 'From process A',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([
            $php, 'artisan', 'rfi:concurrency-test-escalate', $rfi->id, $target->id, $escalator->id, 'From process B',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);

        // Start both subprocesses without waiting in between, so they race for the row lock.
        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame(
            [0, 1],
            $exitCodes,
            'Exactly one of the two concurrent escalate attempts must succeed and the other must conflict. '
            . 'A: ' . $procA->getOutput() . ' B: ' . $procB->getOutput()
        );

        // Exit-code-and-row-count alone is NOT sufficient here: verified via sabotage that MySQL's
        // own deadlock detector can rescue an unlocked race by killing one transaction outright
        // (SQLSTATE 40001), which rolls back that process's INSERT and yields the exact same
        // outward signature (exit code 1, final row count 1) as a clean, intentional
        // RfiEscalationConflictException — silently masking the missing lockForUpdate(). The
        // losing process's stdout must contain the literal "CONFLICT" the command only prints on
        // a caught RfiEscalationConflictException; an uncaught deadlock/QueryException prints a
        // stack trace instead, which this assertion would catch.
        $loserOutput = $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput();
        $this->assertStringContainsString(
            'CONFLICT',
            $loserOutput,
            'The losing process must fail with a clean RfiEscalationConflictException (printed as "CONFLICT ..."), '
            . 'not an uncaught exception/deadlock: ' . $loserOutput
        );

        $this->assertSame(1, RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->count());

        // Now that an active escalation exists, a further attempt must also conflict.
        $third = new Process([
            $php, 'artisan', 'rfi:concurrency-test-escalate', $rfi->id, $target->id, $escalator->id, 'Should conflict',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $third->run();

        $this->assertSame(1, $third->getExitCode());
        $this->assertStringContainsString('CONFLICT', $third->getOutput());
        $this->assertSame(1, RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->count(), 'Still exactly one escalation row after the conflicting attempt.');
    }

    public function test_two_concurrent_resolve_calls_on_the_same_escalation_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $escalator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $target = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id, 'is_active' => true]));

        $rfi = $this->makeRfiOnMysql($tenant, $project, $escalator, 'CONC-RFI-0002');

        app(\App\Services\RfiEscalationService::class)->escalate($rfi->setConnection('mysql'), $target->id, $escalator->id, 'Urgent');

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', 'rfi:concurrency-test-resolve', $rfi->id, $target->id, 'Resolved by A'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', 'rfi:concurrency-test-resolve', $rfi->id, $escalator->id, 'Resolved by B'], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one of the two concurrent resolve attempts must succeed and the other must conflict. A: ' . $procA->getOutput() . ' B: ' . $procB->getOutput());

        // See the equivalent comment in test_two_concurrent_escalate_calls_on_the_same_rfi_only_one_succeeds():
        // exit code alone can't distinguish a clean RfiEscalationConflictException from an uncaught
        // deadlock/QueryException that MySQL's deadlock detector happens to resolve to the same exit code.
        $loserOutput = $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput();
        $this->assertStringContainsString(
            'CONFLICT',
            $loserOutput,
            'The losing process must fail with a clean RfiEscalationConflictException/RfiEscalationNotFoundException '
            . '(printed as "CONFLICT ..."), not an uncaught exception/deadlock: ' . $loserOutput
        );

        $escalation = RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($escalation->resolved_at);
        $this->assertNotNull($escalation->resolved_by);
    }
}
