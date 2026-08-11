<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentLifecycleStatus;
use App\Models\Document;
use App\Models\DocumentApprovalEvent;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * Real row-lock evidence only: every race uses two independently bootstrapped
 * PHP processes/PDO connections released from the same barrier. SQLite and
 * sequential in-process calls are deliberately not accepted as substitutes.
 *
 */
#[Group('stress')]
class DocumentWorkflowConcurrencyTest extends TestCase
{
    use TenantUserFactoryTrait;

    /** @var array<int, string> */
    private array $barriers = [];

    public function test_synthetic_preacquired_lock_evidence_is_rejected(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $this->assertExpectedCrossPathOutcome([
            [
                'operation' => 'submit',
                'status' => 'ok',
                'exit_code' => 0,
                'contention_evidence' => [
                    'role' => 'leader',
                    'lock_wait_observed' => true,
                    'governed_query_observed' => true,
                    'observer_connection_id' => 101,
                    'waiting_connection_id' => 202,
                    'data_lock_waiting_connection_id' => 202,
                    'blocking_connection_id' => 101,
                    'trx_state' => 'LOCK WAIT',
                    'trx_wait_started' => '2026-08-11 12:00:00',
                    'query_targets_documents' => true,
                    'query_uses_for_update' => true,
                    'synthetic_query_marker_present' => true,
                    'document_lock_identity_matched' => true,
                    'object_name' => 'documents',
                    'index_name' => 'PRIMARY',
                    'lock_status' => 'WAITING',
                    'lock_data' => "'synthetic-document'",
                    'observed_query' => 'select /* GAP032_LOCK_synthetic */ * from documents where id = ? for update',
                ],
            ],
            [
                'operation' => 'version',
                'status' => 'domain',
                'reason' => 'HTTP_422',
                'exit_code' => 2,
                'contention_evidence' => [
                    'role' => 'loser',
                    'lock_wait_observed' => true,
                    'connection_id' => 202,
                    'governed_operation' => 'version',
                    'governed_operation_microseconds' => 100_000,
                    'governed_operation_completed_after_observation' => true,
                ],
            ],
        ], 'submit', 'version', 'HTTP_422', 'synthetic-document');
    }

    public function test_concurrent_submit_allows_exactly_one_transition(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, '']
        );

        $this->assertOneWinnerAndDomainLoser($results, 'INVALID_SUBMIT_TRANSITION');
        $fresh = $document->fresh();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'));
        $submitted = DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->sole();
        $this->assertSubmittedLineage($submitted, $fresh, $actor);
    }

    public function test_concurrent_approve_and_reject_allow_exactly_one_decision(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();
        app(DocumentWorkflowService::class)->submit((string) $tenant->id, (string) $document->id, (string) $actor->id);

        $results = $this->race(
            ['decide', (string) $tenant->id, (string) $document->id, (string) $actor->id, 'approved'],
            ['decide', (string) $tenant->id, (string) $document->id, (string) $actor->id, 'rejected']
        );

        $this->assertOneWinnerAndDomainLoser($results, 'INVALID_DECISION_TRANSITION');
        $fresh = $document->fresh();
        self::assertContains($fresh->getRawOriginal('approval_status'), ['approved', 'rejected']);
        $decisionEvent = DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->whereIn('event', ['approved', 'rejected'])
            ->sole();
        $this->assertDecisionLineage($decisionEvent, $fresh, $actor);
    }

    public function test_concurrent_submit_and_generic_update_cannot_undo_approval_entry(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['generic-update', (string) $tenant->id, (string) $document->id, (string) $actor->id, 'in-review'],
            true
        );

        $this->assertExpectedCrossPathOutcome($results, 'submit', 'generic-update', 'HTTP_422', (string) $document->id);
        $fresh = $document->fresh();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'), $this->resultDump($results));
        self::assertSame('submitted', $fresh->getRawOriginal('status'));
        $submitted = DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->sole();
        $this->assertSubmittedLineage($submitted, $fresh, $actor);
    }

    public function test_concurrent_submit_and_version_create_cannot_commit_mixed_state(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            true
        );

        $this->assertExpectedCrossPathOutcome($results, 'submit', 'version', 'HTTP_422', (string) $document->id);
        $fresh = $document->fresh();
        $submitted = DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->sole();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'), $this->resultDump($results));
        $this->assertSubmittedLineage($submitted, $fresh, $actor);
        self::assertSame(1, DocumentVersion::query()->where('document_id', $document->id)->count(), $this->resultDump($results));
    }

    public function test_concurrent_approve_or_reject_and_version_create_cannot_commit_mixed_state(): void
    {
        $this->requireRealMysql();

        foreach (['approved', 'rejected'] as $decision) {
            [$tenant, $actor, , $document] = $this->makeFixture();
            app(DocumentWorkflowService::class)->submit((string) $tenant->id, (string) $document->id, (string) $actor->id);
            $submittedVersionId = (string) $document->fresh()->current_version_id;

            $results = $this->race(
                ['decide', (string) $tenant->id, (string) $document->id, (string) $actor->id, $decision],
                ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
                true
            );

            $this->assertExpectedCrossPathOutcome($results, 'decide', 'version', 'HTTP_422', (string) $document->id);
            $fresh = $document->fresh();
            $decisionEvent = DocumentApprovalEvent::query()
                ->where('document_id', $document->id)
                ->where('event', $decision)
                ->sole();
            self::assertSame($decision, $fresh->getRawOriginal('approval_status'));
            self::assertSame($submittedVersionId, $fresh->current_version_id, 'A version must not move underneath an active approval cycle. ' . $this->resultDump($results));
            self::assertSame(1, DocumentVersion::query()->where('document_id', $document->id)->count(), $this->resultDump($results));
            $this->assertDecisionLineage($decisionEvent, $fresh, $actor);
        }
    }

    public function test_concurrent_version_change_cannot_change_version_under_active_approval_cycle(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();
        app(DocumentWorkflowService::class)->submit((string) $tenant->id, (string) $document->id, (string) $actor->id);
        $versionId = (string) $document->fresh()->current_version_id;

        $results = $this->race(
            ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, '']
        );

        foreach ($results as $result) {
            self::assertSame('version', $result['operation'] ?? null, $this->resultDump($results));
            self::assertSame('domain', $result['status'] ?? null, $this->resultDump($results));
            self::assertSame('HTTP_422', $result['reason'] ?? null, $this->resultDump($results));
            self::assertSame(2, $result['exit_code'] ?? null, $this->resultDump($results));
        }
        self::assertSame($versionId, $document->fresh()->current_version_id, $this->resultDump($results));
        self::assertSame(1, DocumentVersion::query()->where('document_id', $document->id)->count());
    }

    /** @return array{Tenant, User, Project, Document} */
    private function makeFixture(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->createTenantUser($tenant, [], ['designer'], ['document.update', 'document.approve']);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $actor->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'metadata' => ['status' => 'draft'],
            'version' => 1,
            'current_version_id' => null,
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => "documents/{$document->id}/v1.pdf",
            'storage_driver' => 'local',
            'comment' => 'Initial version',
            'metadata' => ['version' => 1],
            'created_by' => $actor->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

        return [$tenant, $actor, $project, $document->fresh()];
    }

    private function requireRealMysql(): void
    {
        if (env('DB_CONNECTION') !== 'mysql' || DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'INSUFFICIENT CONCURRENCY EVIDENCE: set DB_CONNECTION=mysql and run with a real MySQL service. '
                . 'SQLite/sequential execution cannot prove row-lock behavior.'
            );
        }

        DB::select('SELECT 1');
    }

    /**
     * @param array{string, string, string, string, string} $left
     * @param array{string, string, string, string, string} $right
     * @return array<int, array<string, mixed>>
     */
    private function race(array $left, array $right, bool $leftLocksFirst = false): array
    {
        $lockToken = bin2hex(random_bytes(8));
        $barrier = sys_get_temp_dir() . '/gap032-' . $lockToken;
        if (! mkdir($barrier, 0700) && ! is_dir($barrier)) {
            self::fail("Unable to create process barrier {$barrier}.");
        }
        $this->barriers[] = $barrier;

        $php = (new PhpExecutableFinder())->find(false);
        self::assertNotFalse($php);
        $processes = [];
        foreach ([[$left, 'left'], [$right, 'right']] as [$arguments, $label]) {
            $lockRole = $leftLocksFirst ? ($label === 'left' ? 'leader' : 'loser') : 'peer';
            $processes[] = new Process(
                [$php, '-r', $this->workerScript(), ...$arguments, $barrier, $label, $lockRole, $lockToken],
                base_path(),
                ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql'],
                null,
                45
            );
        }

        foreach ($processes as $process) {
            $process->start();
        }
        $deadline = microtime(true) + 15;
        while ((! file_exists($barrier . '/left.ready') || ! file_exists($barrier . '/right.ready')) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        self::assertFileExists($barrier . '/left.ready', $this->processDump($processes));
        self::assertFileExists($barrier . '/right.ready', $this->processDump($processes));
        file_put_contents($barrier . '/release', 'go');

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $output = $process->getOutput() . $process->getErrorOutput();
            self::assertMatchesRegularExpression('/GAP032_RESULT=(\{.*\})/', $output, $output);
            preg_match('/GAP032_RESULT=(\{.*\})/', $output, $matches);
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $decoded['exit_code'] = $process->getExitCode();
            $decoded['raw_output'] = $output;
            $results[] = $decoded;
        }

        return $results;
    }

    /** @param array<int, array<string, mixed>> $results */
    private function assertOneWinnerAndDomainLoser(array $results, string $expectedReason): void
    {
        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame(['domain', 'ok'], $statuses, $this->resultDump($results));
        $loser = collect($results)->firstWhere('status', 'domain');
        $winner = collect($results)->firstWhere('status', 'ok');
        self::assertSame($expectedReason, $loser['reason'] ?? null, $this->resultDump($results));
        self::assertSame(2, $loser['exit_code'] ?? null, $this->resultDump($results));
        self::assertSame(0, $winner['exit_code'] ?? null, $this->resultDump($results));
    }

    /** @param array<int, array<string, mixed>> $results */
    private function assertExpectedCrossPathOutcome(
        array $results,
        string $winnerOperation,
        string $loserOperation,
        string $loserReason,
        string $documentId,
    ): void {
        $winner = collect($results)->firstWhere('operation', $winnerOperation);
        $loser = collect($results)->firstWhere('operation', $loserOperation);

        self::assertNotNull($winner, $this->resultDump($results));
        self::assertNotNull($loser, $this->resultDump($results));
        self::assertSame('ok', $winner['status'] ?? null, $this->resultDump($results));
        self::assertSame(0, $winner['exit_code'] ?? null, $this->resultDump($results));
        self::assertSame('domain', $loser['status'] ?? null, $this->resultDump($results));
        self::assertSame($loserReason, $loser['reason'] ?? null, $this->resultDump($results));
        self::assertSame(2, $loser['exit_code'] ?? null, $this->resultDump($results));
        $this->assertObservedMysqlLockWait($winner, $loser, $loserOperation, $documentId, $results);
    }

    /**
     * @param array<string, mixed> $winner
     * @param array<string, mixed> $loser
     * @param array<int, array<string, mixed>> $results
     */
    private function assertObservedMysqlLockWait(
        array $winner,
        array $loser,
        string $loserOperation,
        string $documentId,
        array $results,
    ): void
    {
        $winnerEvidence = $winner['contention_evidence'] ?? null;
        $loserEvidence = $loser['contention_evidence'] ?? null;

        self::assertIsArray($winnerEvidence, $this->resultDump($results));
        self::assertIsArray($loserEvidence, $this->resultDump($results));
        self::assertSame('leader', $winnerEvidence['role'] ?? null, $this->resultDump($results));
        self::assertSame('loser', $loserEvidence['role'] ?? null, $this->resultDump($results));
        self::assertTrue($winnerEvidence['lock_wait_observed'] ?? false, $this->resultDump($results));
        self::assertTrue($loserEvidence['lock_wait_observed'] ?? false, $this->resultDump($results));
        self::assertSame('LOCK WAIT', $winnerEvidence['trx_state'] ?? null, $this->resultDump($results));
        self::assertTrue($winnerEvidence['governed_query_observed'] ?? false, $this->resultDump($results));
        self::assertTrue($winnerEvidence['query_targets_documents'] ?? false, $this->resultDump($results));
        self::assertTrue($winnerEvidence['query_uses_for_update'] ?? false, $this->resultDump($results));
        self::assertFalse($winnerEvidence['synthetic_query_marker_present'] ?? true, $this->resultDump($results));
        self::assertMatchesRegularExpression(
            '/\bfrom\s+`?documents`?\b/i',
            $winnerEvidence['observed_query'] ?? '',
            $this->resultDump($results)
        );
        self::assertStringContainsStringIgnoringCase(
            'for update',
            $winnerEvidence['observed_query'] ?? '',
            $this->resultDump($results)
        );
        self::assertStringNotContainsStringIgnoringCase(
            'GAP032_LOCK_',
            $winnerEvidence['observed_query'] ?? '',
            $this->resultDump($results)
        );
        self::assertTrue($winnerEvidence['document_lock_identity_matched'] ?? false, $this->resultDump($results));
        self::assertTrue($loserEvidence['governed_operation_completed_after_observation'] ?? false, $this->resultDump($results));
        self::assertSame($loserOperation, $loserEvidence['governed_operation'] ?? null, $this->resultDump($results));
        self::assertGreaterThan(0, $winnerEvidence['observer_connection_id'] ?? 0, $this->resultDump($results));
        self::assertGreaterThan(0, $winnerEvidence['waiting_connection_id'] ?? 0, $this->resultDump($results));
        self::assertNotSame(
            $winnerEvidence['observer_connection_id'] ?? null,
            $winnerEvidence['waiting_connection_id'] ?? null,
            $this->resultDump($results)
        );
        self::assertNotSame('', $winnerEvidence['trx_wait_started'] ?? '', $this->resultDump($results));
        self::assertSame(
            $winnerEvidence['waiting_connection_id'] ?? null,
            $loserEvidence['connection_id'] ?? null,
            $this->resultDump($results)
        );
        self::assertSame(
            $winnerEvidence['waiting_connection_id'] ?? null,
            $winnerEvidence['data_lock_waiting_connection_id'] ?? null,
            $this->resultDump($results)
        );
        self::assertSame(
            $winnerEvidence['observer_connection_id'] ?? null,
            $winnerEvidence['blocking_connection_id'] ?? null,
            $this->resultDump($results)
        );
        self::assertSame('documents', $winnerEvidence['object_name'] ?? null, $this->resultDump($results));
        self::assertSame('PRIMARY', $winnerEvidence['index_name'] ?? null, $this->resultDump($results));
        self::assertSame('WAITING', $winnerEvidence['lock_status'] ?? null, $this->resultDump($results));
        self::assertStringContainsString($documentId, $winnerEvidence['lock_data'] ?? '', $this->resultDump($results));
        self::assertGreaterThan(0, $loserEvidence['governed_operation_microseconds'] ?? 0, $this->resultDump($results));
    }

    private function assertSubmittedLineage(DocumentApprovalEvent $event, Document $document, User $actor): void
    {
        $metadata = $document->metadata ?? [];
        self::assertSame('submitted', $event->event);
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $event->from_approval_status);
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $event->to_approval_status);
        self::assertSame($document->current_version_id, $event->document_version_id);
        self::assertSame((string) $actor->id, $event->actor_id);
        self::assertNull($event->note);
        self::assertSame([
            'submitted_by' => $metadata['submitted_by'] ?? null,
            'submitted_at' => $metadata['submitted_at'] ?? null,
        ], $event->context);
    }

    private function assertDecisionLineage(DocumentApprovalEvent $event, Document $document, User $actor): void
    {
        $metadata = $document->metadata ?? [];
        self::assertSame($document->getRawOriginal('approval_status'), $event->event);
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $event->from_approval_status);
        self::assertSame($document->getRawOriginal('approval_status'), $event->to_approval_status);
        self::assertSame($document->current_version_id, $event->document_version_id);
        self::assertSame((string) $actor->id, $event->actor_id);
        self::assertNull($event->note);
        self::assertSame([
            'decision' => $metadata['decision'] ?? null,
            'decision_by' => $metadata['decision_by'] ?? null,
            'decision_at' => $metadata['decision_at'] ?? null,
            'decision_note' => $metadata['decision_note'] ?? null,
        ], $event->context);
    }

    /** @param array<int, array<string, mixed>> $results */
    private function resultDump(array $results): string
    {
        return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'unable to encode results';
    }

    /** @param array<int, Process> $processes */
    private function processDump(array $processes): string
    {
        return collect($processes)->map(static fn (Process $process): array => [
            'running' => $process->isRunning(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ])->toJson(JSON_PRETTY_PRINT);
    }

    private function workerScript(): string
    {
        return <<<'PHP'
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$operation, $tenantId, $documentId, $actorId, $payload, $barrier, $label, $lockRole, $barrierToken] = array_slice($argv, 1);
file_put_contents($barrier . '/' . $label . '.ready', 'ready');
$deadline = microtime(true) + 20;
while (!file_exists($barrier . '/release') && microtime(true) < $deadline) { usleep(10000); }
if (!file_exists($barrier . '/release')) {
    echo 'GAP032_RESULT=' . json_encode(['operation' => $operation, 'status' => 'error', 'reason' => 'BARRIER_TIMEOUT']) . PHP_EOL;
    exit(3);
}
$contentionEvidence = null;
$governedOperationStarted = null;
$loserConnectionId = null;
try {
    if ($lockRole === 'leader') {
        Illuminate\Support\Facades\DB::beginTransaction();
        $leaderConnection = Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS connection_id');
        $leaderConnectionId = (int) ($leaderConnection->connection_id ?? 0);
        $lockedDocument = Illuminate\Support\Facades\DB::table('documents')
            ->where('tenant_id', $tenantId)
            ->where('id', $documentId)
            ->lockForUpdate()
            ->first();
        if ($lockedDocument === null) {
            throw new RuntimeException('Controlled leader could not find the document.');
        }
        file_put_contents($barrier . '/leader.locked', 'locked');
        $deadline = microtime(true) + 10;
        while (!file_exists($barrier . '/loser.connection') && microtime(true) < $deadline) { usleep(10000); }
        if (!file_exists($barrier . '/loser.connection')) {
            throw new RuntimeException('Controlled loser did not publish its MySQL connection ID.');
        }
        $loserConnectionId = (int) trim((string) file_get_contents($barrier . '/loser.connection'));
        $waitingTransaction = null;
        $waitingDataLock = null;
        $observationDeadline = microtime(true) + 10;
        while (microtime(true) < $observationDeadline) {
            $candidate = Illuminate\Support\Facades\DB::selectOne(
                "SELECT trx_state, trx_mysql_thread_id, trx_wait_started, trx_query
                 FROM information_schema.innodb_trx
                 WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'",
                [$loserConnectionId]
            );
            $query = is_string($candidate->trx_query ?? null) ? $candidate->trx_query : '';
            $normalizedQuery = strtolower((string) preg_replace('/\s+/', ' ', trim($query)));
            $queryTargetsDocuments = preg_match('/\bfrom\s+`?documents`?\b/', $normalizedQuery) === 1;
            $queryUsesForUpdate = str_contains($normalizedQuery, 'for update');
            $syntheticQueryMarkerPresent = str_contains($normalizedQuery, 'gap032_lock_');

            $lockCandidate = Illuminate\Support\Facades\DB::selectOne(
                "SELECT
                    requesting_thread.PROCESSLIST_ID AS waiting_connection_id,
                    blocking_thread.PROCESSLIST_ID AS blocking_connection_id,
                    requested_lock.OBJECT_SCHEMA AS object_schema,
                    requested_lock.OBJECT_NAME AS object_name,
                    requested_lock.INDEX_NAME AS index_name,
                    requested_lock.LOCK_TYPE AS lock_type,
                    requested_lock.LOCK_MODE AS lock_mode,
                    requested_lock.LOCK_STATUS AS lock_status,
                    requested_lock.LOCK_DATA AS lock_data
                 FROM performance_schema.data_lock_waits AS lock_wait
                 JOIN performance_schema.data_locks AS requested_lock
                   ON requested_lock.ENGINE_LOCK_ID = lock_wait.REQUESTING_ENGINE_LOCK_ID
                 JOIN performance_schema.threads AS requesting_thread
                   ON requesting_thread.THREAD_ID = lock_wait.REQUESTING_THREAD_ID
                 JOIN performance_schema.threads AS blocking_thread
                   ON blocking_thread.THREAD_ID = lock_wait.BLOCKING_THREAD_ID
                 WHERE requesting_thread.PROCESSLIST_ID = ?
                   AND blocking_thread.PROCESSLIST_ID = ?
                   AND requested_lock.OBJECT_SCHEMA = DATABASE()
                   AND requested_lock.OBJECT_NAME = 'documents'
                 LIMIT 1",
                [$loserConnectionId, $leaderConnectionId]
            );
            $documentLockIdentityMatched = $lockCandidate !== null
                && (string) ($lockCandidate->index_name ?? '') === 'PRIMARY'
                && (string) ($lockCandidate->lock_status ?? '') === 'WAITING'
                && str_contains((string) ($lockCandidate->lock_data ?? ''), $documentId);

            if ($candidate !== null
                && $queryTargetsDocuments
                && $queryUsesForUpdate
                && !$syntheticQueryMarkerPresent
                && $documentLockIdentityMatched) {
                $waitingTransaction = $candidate;
                $waitingDataLock = $lockCandidate;
                break;
            }
            usleep(10000);
        }
        if ($waitingTransaction === null || $waitingDataLock === null) {
            throw new RuntimeException('Controlled loser never entered an observable InnoDB LOCK WAIT on the governed documents primary-key lock.');
        }
        $observedQuery = (string) ($waitingTransaction->trx_query ?? '');
        $normalizedObservedQuery = strtolower((string) preg_replace('/\s+/', ' ', trim($observedQuery)));
        $contentionEvidence = [
            'role' => 'leader',
            'lock_wait_observed' => true,
            'governed_query_observed' => true,
            'observer_connection_id' => $leaderConnectionId,
            'waiting_connection_id' => (int) ($waitingTransaction->trx_mysql_thread_id ?? 0),
            'data_lock_waiting_connection_id' => (int) ($waitingDataLock->waiting_connection_id ?? 0),
            'blocking_connection_id' => (int) ($waitingDataLock->blocking_connection_id ?? 0),
            'trx_state' => (string) ($waitingTransaction->trx_state ?? ''),
            'trx_wait_started' => (string) ($waitingTransaction->trx_wait_started ?? ''),
            'query_targets_documents' => preg_match('/\bfrom\s+`?documents`?\b/', $normalizedObservedQuery) === 1,
            'query_uses_for_update' => str_contains($normalizedObservedQuery, 'for update'),
            'synthetic_query_marker_present' => str_contains($normalizedObservedQuery, 'gap032_lock_'),
            'document_lock_identity_matched' => str_contains((string) ($waitingDataLock->lock_data ?? ''), $documentId),
            'object_name' => (string) ($waitingDataLock->object_name ?? ''),
            'index_name' => (string) ($waitingDataLock->index_name ?? ''),
            'lock_status' => (string) ($waitingDataLock->lock_status ?? ''),
            'lock_type' => (string) ($waitingDataLock->lock_type ?? ''),
            'lock_mode' => (string) ($waitingDataLock->lock_mode ?? ''),
            'lock_data' => (string) ($waitingDataLock->lock_data ?? ''),
            'observed_query' => $observedQuery,
        ];
        $observationPath = $barrier . '/lock-wait.observed';
        $observationTemp = $observationPath . '.' . $barrierToken . '.tmp';
        if (file_put_contents($observationTemp, json_encode($contentionEvidence, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || !rename($observationTemp, $observationPath)) {
            throw new RuntimeException('Controlled leader could not atomically publish lock-wait evidence.');
        }
    } elseif ($lockRole === 'loser') {
        $deadline = microtime(true) + 10;
        while (!file_exists($barrier . '/leader.locked') && microtime(true) < $deadline) { usleep(10000); }
        if (!file_exists($barrier . '/leader.locked')) {
            throw new RuntimeException('Controlled leader did not acquire the row lock.');
        }
        $loserConnection = Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS connection_id');
        $loserConnectionId = (int) ($loserConnection->connection_id ?? 0);
        $connectionPath = $barrier . '/loser.connection';
        $connectionTemp = $connectionPath . '.' . $label . '.' . $barrierToken . '.tmp';
        if (file_put_contents($connectionTemp, (string) $loserConnectionId, LOCK_EX) === false
            || !rename($connectionTemp, $connectionPath)) {
            throw new RuntimeException('Controlled loser could not atomically publish its MySQL connection ID.');
        }
        $governedOperationStarted = hrtime(true);
    }

    if ($operation === 'submit') {
        $document = $app->make(App\Services\DocumentWorkflowService::class)->submit($tenantId, $documentId, $actorId);
        $result = ['operation' => $operation, 'status' => 'ok', 'approval' => $document->getRawOriginal('approval_status')];
    } elseif ($operation === 'decide') {
        $document = $app->make(App\Services\DocumentWorkflowService::class)->decide(
            $tenantId,
            $documentId,
            $actorId,
            App\Enums\DocumentDecision::from($payload),
            null
        );
        $result = ['operation' => $operation, 'status' => 'ok', 'approval' => $document->getRawOriginal('approval_status')];
    } else {
        $user = App\Models\User::query()->withoutGlobalScopes()->findOrFail($actorId);
        Illuminate\Support\Facades\Auth::login($user);
        $app->instance('current_tenant_id', $tenantId);
        if ($operation === 'generic-update') {
            $request = Illuminate\Http\Request::create('/', 'PATCH', ['status' => $payload]);
            $response = $app->make(App\Http\Controllers\Api\SimpleDocumentController::class)->update($request, $documentId);
        } elseif ($operation === 'version') {
            $path = tempnam(sys_get_temp_dir(), 'gap032-version-');
            file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
            $upload = new Illuminate\Http\UploadedFile($path, $label . '.pdf', 'application/pdf', null, true);
            $request = Illuminate\Http\Request::create('/', 'POST', [], [], ['file' => $upload]);
            $response = $app->make(App\Http\Controllers\Api\SimpleDocumentController::class)->createVersion($request, $documentId);
        } else {
            throw new RuntimeException('Unknown operation ' . $operation);
        }
        $statusCode = $response->getStatusCode();
        $result = [
            'operation' => $operation,
            'status' => $statusCode >= 400 ? 'domain' : 'ok',
            'reason' => $statusCode >= 400 ? 'HTTP_' . $statusCode : null,
            'http_status' => $statusCode,
            'body' => json_decode($response->getContent(), true),
        ];
    }
    if ($lockRole === 'loser') {
        $observationPath = $barrier . '/lock-wait.observed';
        if (!file_exists($observationPath)) {
            throw new RuntimeException('Governed controller operation completed without leader-side InnoDB LOCK WAIT evidence.');
        }
        $observedEvidence = json_decode(
            (string) file_get_contents($observationPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $contentionEvidence = [
            'role' => 'loser',
            'lock_wait_observed' => ($observedEvidence['lock_wait_observed'] ?? false) === true
                && ($observedEvidence['governed_query_observed'] ?? false) === true
                && ($observedEvidence['document_lock_identity_matched'] ?? false) === true
                && ($observedEvidence['waiting_connection_id'] ?? null) === $loserConnectionId,
            'connection_id' => $loserConnectionId,
            'governed_operation' => $operation,
            'governed_operation_microseconds' => $governedOperationStarted === null
                ? 0
                : (int) ((hrtime(true) - $governedOperationStarted) / 1000),
            'governed_operation_completed_after_observation' => true,
        ];
    }
    if ($contentionEvidence !== null) {
        $result['contention_evidence'] = $contentionEvidence;
    }
    if ($lockRole === 'leader') {
        Illuminate\Support\Facades\DB::commit();
    }
    echo 'GAP032_RESULT=' . json_encode($result) . PHP_EOL;
    exit(($result['status'] ?? null) === 'ok' ? 0 : 2);
} catch (App\Exceptions\DocumentWorkflowException $exception) {
    if (Illuminate\Support\Facades\DB::transactionLevel() > 0) {
        Illuminate\Support\Facades\DB::rollBack();
    }
    echo 'GAP032_RESULT=' . json_encode([
        'operation' => $operation,
        'status' => 'domain',
        'reason' => $exception->reasonCode,
        'message' => $exception->getMessage(),
    ]) . PHP_EOL;
    exit(2);
} catch (Throwable $exception) {
    if (Illuminate\Support\Facades\DB::transactionLevel() > 0) {
        Illuminate\Support\Facades\DB::rollBack();
    }
    echo 'GAP032_RESULT=' . json_encode([
        'operation' => $operation,
        'status' => 'error',
        'reason' => get_class($exception),
        'message' => $exception->getMessage(),
    ]) . PHP_EOL;
    exit(3);
}
PHP;
    }

    protected function tearDown(): void
    {
        foreach ($this->barriers as $barrier) {
            foreach (glob($barrier . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($barrier);
        }

        parent::tearDown();
    }
}
