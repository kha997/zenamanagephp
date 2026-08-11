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

    public function test_concurrent_submit_allows_exactly_one_transition(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, '']
        );

        $this->assertOneWinnerAndDomainLoser($results, ['INVALID_SUBMIT_TRANSITION']);
        $fresh = $document->fresh();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'));
        self::assertSame(1, DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->count());
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

        $this->assertOneWinnerAndDomainLoser($results, ['INVALID_DECISION_TRANSITION']);
        self::assertContains($document->fresh()->getRawOriginal('approval_status'), ['approved', 'rejected']);
        self::assertSame(1, DocumentApprovalEvent::query()
            ->where('document_id', $document->id)
            ->whereIn('event', ['approved', 'rejected'])
            ->count());
    }

    public function test_concurrent_submit_and_generic_update_cannot_undo_approval_entry(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['generic-update', (string) $tenant->id, (string) $document->id, (string) $actor->id, 'in-review']
        );

        self::assertSame('ok', $results[0]['operation'] === 'submit' ? $results[0]['status'] : $results[1]['status'], $this->resultDump($results));
        self::assertContains($results[0]['status'], ['ok', 'domain'], $this->resultDump($results));
        self::assertContains($results[1]['status'], ['ok', 'domain'], $this->resultDump($results));
        $fresh = $document->fresh();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'), $this->resultDump($results));
        self::assertSame('submitted', $fresh->getRawOriginal('status'));
        self::assertSame(1, DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->count());
    }

    public function test_concurrent_submit_and_version_create_cannot_commit_mixed_state(): void
    {
        $this->requireRealMysql();
        [$tenant, $actor, , $document] = $this->makeFixture();

        $results = $this->race(
            ['submit', (string) $tenant->id, (string) $document->id, (string) $actor->id, ''],
            ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, '']
        );

        self::assertContains($results[0]['status'], ['ok', 'domain'], $this->resultDump($results));
        self::assertContains($results[1]['status'], ['ok', 'domain'], $this->resultDump($results));
        $fresh = $document->fresh();
        $submitted = DocumentApprovalEvent::query()->where('document_id', $document->id)->where('event', 'submitted')->sole();
        self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'), $this->resultDump($results));
        self::assertSame($submitted->document_version_id, $fresh->current_version_id, 'Approval state and version evidence must come from one serialized outcome. ' . $this->resultDump($results));
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
                ['version', (string) $tenant->id, (string) $document->id, (string) $actor->id, '']
            );

            self::assertContains($results[0]['status'], ['ok', 'domain'], $this->resultDump($results));
            self::assertContains($results[1]['status'], ['ok', 'domain'], $this->resultDump($results));
            $fresh = $document->fresh();
            $decisionEvent = DocumentApprovalEvent::query()
                ->where('document_id', $document->id)
                ->where('event', $decision)
                ->first();
            if ($decisionEvent !== null) {
                self::assertSame($submittedVersionId, $decisionEvent->document_version_id);
                self::assertSame($submittedVersionId, $fresh->current_version_id, 'A version must not move underneath an active approval cycle. ' . $this->resultDump($results));
            } else {
                self::assertSame(DocumentApprovalStatus::AWAITING_APPROVAL->value, $fresh->getRawOriginal('approval_status'));
            }
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

        self::assertSame(['domain', 'domain'], array_column($results, 'status'), $this->resultDump($results));
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
    private function race(array $left, array $right): array
    {
        $barrier = sys_get_temp_dir() . '/gap032-' . bin2hex(random_bytes(8));
        if (! mkdir($barrier, 0700) && ! is_dir($barrier)) {
            self::fail("Unable to create process barrier {$barrier}.");
        }
        $this->barriers[] = $barrier;

        $php = (new PhpExecutableFinder())->find(false);
        self::assertNotFalse($php);
        $processes = [];
        foreach ([[$left, 'left'], [$right, 'right']] as [$arguments, $label]) {
            $processes[] = new Process(
                [$php, '-r', $this->workerScript(), ...$arguments, $barrier, $label],
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
    private function assertOneWinnerAndDomainLoser(array $results, array $allowedReasons): void
    {
        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame(['domain', 'ok'], $statuses, $this->resultDump($results));
        $loser = collect($results)->firstWhere('status', 'domain');
        self::assertContains($loser['reason'] ?? null, $allowedReasons, $this->resultDump($results));
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
[$operation, $tenantId, $documentId, $actorId, $payload, $barrier, $label] = array_slice($argv, 1);
file_put_contents($barrier . '/' . $label . '.ready', 'ready');
$deadline = microtime(true) + 20;
while (!file_exists($barrier . '/release') && microtime(true) < $deadline) { usleep(10000); }
if (!file_exists($barrier . '/release')) {
    echo 'GAP032_RESULT=' . json_encode(['operation' => $operation, 'status' => 'error', 'reason' => 'BARRIER_TIMEOUT']) . PHP_EOL;
    exit(3);
}
try {
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
    echo 'GAP032_RESULT=' . json_encode($result) . PHP_EOL;
    exit(($result['status'] ?? null) === 'ok' ? 0 : 2);
} catch (App\Exceptions\DocumentWorkflowException $exception) {
    echo 'GAP032_RESULT=' . json_encode([
        'operation' => $operation,
        'status' => 'domain',
        'reason' => $exception->reasonCode,
        'message' => $exception->getMessage(),
    ]) . PHP_EOL;
    exit(2);
} catch (Throwable $exception) {
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
