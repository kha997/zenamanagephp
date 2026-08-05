<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Proves DocumentWorkflowService::decide() serializes concurrent decisions via a
 * real row lock (SELECT ... FOR UPDATE on the documents row) — not merely an
 * application-level state check. Must run against real MySQL with two genuinely
 * independent OS processes; sequential in-process calls against sqlite (see
 * DocumentWorkflowServiceTest::test_sequential_double_decide_second_call_rejected_first_decision_persists)
 * cannot exercise cross-connection row locking and would be a false proof — this
 * test skips itself with an explicit message if the "mysql" connection is
 * unreachable, mirroring RfiEscalationConcurrencyTest.php.
 *
 * @group stress
 */
class DocumentWorkflowConcurrencyTest extends TestCase
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
                . 'with MySQL configured before treating concurrency as verified.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                DB::connection('mysql')->table('documents')->delete();
                DB::connection('mysql')->table('projects')->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment — nothing to clean up.
        }
        parent::tearDown();
    }

    public function test_two_concurrent_decide_calls_on_the_same_submitted_document_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $uploader = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));

        $document = Document::on('mysql')->create(array_merge(
            Document::factory()->raw([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'uploaded_by' => $uploader->id,
                'created_by' => $uploader->id,
                'updated_by' => $uploader->id,
            ]),
            ['status' => 'submitted', 'metadata' => ['status' => 'submitted']]
        ));

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'approved',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'rejected',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame(
            [0, 1],
            $exitCodes,
            'Exactly one of the two concurrent decide() attempts must succeed and the other must conflict. '
            . 'A: ' . $procA->getOutput() . ' B: ' . $procB->getOutput()
        );

        $loserOutput = $procA->getExitCode() === 1 ? $procA->getOutput() : $procB->getOutput();
        $this->assertStringContainsString(
            'CONFLICT',
            $loserOutput,
            'The losing process must fail with a clean DocumentWorkflowException (printed as "CONFLICT ..."), '
            . 'not an uncaught exception/deadlock: ' . $loserOutput
        );

        $finalRow = DB::connection('mysql')->table('documents')->where('id', $document->id)->first();
        $this->assertContains($finalRow->status, ['approved', 'rejected']);

        // Amended (per amendment #3): the final status and ALL decision audit
        // metadata must belong to the SAME winning process — not a mix where
        // one process's status write "won" but another's audit fields leaked
        // through, which would prove the two writes weren't properly
        // serialized even if the exit-code assertion above happened to pass.
        $finalMetadata = json_decode($finalRow->metadata, true);
        $this->assertSame($finalRow->status, $finalMetadata['decision'] ?? null);
        $this->assertSame((string) $uploader->id, $finalMetadata['decision_by'] ?? null);
        $this->assertNotNull($finalMetadata['decision_at'] ?? null);

        $third = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'approved',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $third->run();

        $this->assertSame(1, $third->getExitCode());
        $this->assertStringContainsString('CONFLICT', $third->getOutput());
    }
}
