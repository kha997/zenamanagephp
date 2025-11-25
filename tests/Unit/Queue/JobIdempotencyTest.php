<?php declare(strict_types=1);

namespace Tests\Unit\Queue;

use Tests\TestCase;
use App\Jobs\BaseIdempotentJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Job Idempotency Tests
 * 
 * PR: Job idempotency
 */
class JobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_01_19_000001_create_job_idempotency_keys_table.php']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2025_01_19_000002_create_dead_letter_queue_table.php']);
    }

    public function test_idempotency_key_generation(): void
    {
        $job = new TestIdempotentJob('tenant-123', 'user-456');
        
        $this->assertNotNull($job->idempotencyKey);
        $this->assertStringContainsString('tenant-123', $job->idempotencyKey);
        $this->assertStringContainsString('user-456', $job->idempotencyKey);
    }

    public function test_job_skips_if_already_processed(): void
    {
        $job = new TestIdempotentJob('tenant-123', 'user-456');
        $idempotencyKey = $job->idempotencyKey;

        // Mark as completed
        DB::table('job_idempotency_keys')->insert([
            'idempotency_key' => $idempotencyKey,
            'tenant_id' => 'tenant-123',
            'user_id' => 'user-456',
            'action' => 'test_idempotent_job',
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::put("job_idempotency:{$idempotencyKey}", 'completed', 86400);

        // Job should skip execution
        $executed = false;
        $job->setExecuteCallback(function () use (&$executed) {
            $executed = true;
        });

        $job->handle();

        $this->assertFalse($executed, 'Job should not execute if already processed');
    }

    public function test_job_executes_if_not_processed(): void
    {
        $job = new TestIdempotentJob('tenant-123', 'user-456');

        $executed = false;
        $job->setExecuteCallback(function () use (&$executed) {
            $executed = true;
        });

        $job->handle();

        $this->assertTrue($executed, 'Job should execute if not processed');
    }

    public function test_job_marked_as_completed_after_success(): void
    {
        $job = new TestIdempotentJob('tenant-123', 'user-456');
        $idempotencyKey = $job->idempotencyKey;

        $job->handle();

        // Check database
        $record = DB::table('job_idempotency_keys')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('completed', $record->status);

        // Check cache
        $cached = Cache::get("job_idempotency:{$idempotencyKey}");
        $this->assertEquals('completed', $cached);
    }
}

/**
 * Test job for idempotency testing
 */
class TestIdempotentJob extends BaseIdempotentJob
{
    private $executeCallback;

    public function setExecuteCallback(callable $callback): void
    {
        $this->executeCallback = $callback;
    }

    protected function execute(): void
    {
        if ($this->executeCallback) {
            ($this->executeCallback)();
        }
    }
}

