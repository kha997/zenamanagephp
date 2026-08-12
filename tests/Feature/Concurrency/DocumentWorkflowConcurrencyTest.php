<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use App\Models\DocumentApprovalEvent;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
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
 */
#[Group('stress')]
class DocumentWorkflowConcurrencyTest extends TestCase
{
    private ?string $originalDefaultConnection = null;

    /** @group stress */
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
                DB::connection('mysql')->table('document_approval_events')->delete();
                DB::connection('mysql')->table('document_versions')->delete();
                DB::connection('mysql')->table('documents')->delete();
                DB::connection('mysql')->table('projects')->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment — nothing to clean up.
        }
        if ($this->originalDefaultConnection !== null) {
            DB::setDefaultConnection($this->originalDefaultConnection);
        }
        parent::tearDown();
    }

    public function test_two_concurrent_decide_calls_on_the_same_submitted_document_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();
        $this->originalDefaultConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('mysql');

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $uploader = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));

        $submittedAt = now()->toISOString();
        $document = Document::on('mysql')->create(array_merge(
            Document::factory()->raw([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'uploaded_by' => $uploader->id,
                'created_by' => $uploader->id,
                'updated_by' => $uploader->id,
            ]),
            [
                'status' => 'submitted',
                'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
                'approval_status' => DocumentApprovalStatus::AWAITING_APPROVAL->value,
                'current_version_id' => null,
                'metadata' => [
                    'status' => 'submitted',
                    'submitted_by' => (string) $uploader->id,
                    'submitted_at' => $submittedAt,
                ],
            ]
        ));
        $version = DocumentVersion::on('mysql')->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => "documents/{$document->id}/v1.pdf",
            'storage_driver' => 'local',
            'comment' => 'Initial version',
            'metadata' => ['version' => 1],
            'created_by' => $uploader->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();
        DocumentApprovalEvent::on('mysql')->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'event' => 'submitted',
            'from_approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'to_approval_status' => DocumentApprovalStatus::AWAITING_APPROVAL->value,
            'actor_id' => $uploader->id,
            'note' => null,
            'context' => [
                'submitted_by' => (string) $uploader->id,
                'submitted_at' => $submittedAt,
            ],
        ]);

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
        $this->assertSame($finalRow->status, $finalRow->approval_status);
        $this->assertSame(DocumentLifecycleStatus::DRAFT->value, $finalRow->lifecycle_status);

        // Amended (per amendment #3): the final status and ALL decision audit
        // metadata must belong to the SAME winning process — not a mix where
        // one process's status write "won" but another's audit fields leaked
        // through, which would prove the two writes weren't properly
        // serialized even if the exit-code assertion above happened to pass.
        $finalMetadata = json_decode($finalRow->metadata, true);
        $this->assertSame($finalRow->status, $finalMetadata['decision'] ?? null);
        $this->assertSame((string) $uploader->id, $finalMetadata['decision_by'] ?? null);
        $this->assertNotNull($finalMetadata['decision_at'] ?? null);

        $decisionEvents = DB::connection('mysql')->table('document_approval_events')
            ->where('document_id', $document->id)
            ->whereIn('event', ['approved', 'rejected'])
            ->get();
        $this->assertCount(1, $decisionEvents);
        $decisionEvent = $decisionEvents->first();
        $this->assertSame($finalRow->status, $decisionEvent->event);
        $this->assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $decisionEvent->from_approval_status);
        $this->assertSame($finalRow->status, $decisionEvent->to_approval_status);
        $this->assertSame((string) $version->id, $decisionEvent->document_version_id);
        $this->assertSame((string) $uploader->id, $decisionEvent->actor_id);

        $third = new Process([
            $php, 'artisan', 'document:concurrency-test-decide', $tenant->id, $document->id, $uploader->id, 'approved',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $third->run();

        $this->assertSame(1, $third->getExitCode());
        $this->assertStringContainsString('CONFLICT', $third->getOutput());
    }
}
