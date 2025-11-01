<?php declare(strict_types=1);

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessDocumentJob;
use App\Jobs\SendEmailJob;
use App\Services\QueueManagementService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QueueJobTest extends TestCase
{
    use RefreshDatabase;

    protected QueueManagementService $queueService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueService = new QueueManagementService();
    }

    /**
     * Test ProcessDocumentJob can be dispatched
     */
    public function test_process_document_job_can_be_dispatched(): void
    {
        Queue::fake();

        $documentId = 'doc-123';
        $userId = 'user-123';
        $tenantId = 'tenant-123';

        ProcessDocumentJob::dispatch($documentId, $userId, $tenantId);

        Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($documentId, $userId, $tenantId) {
            return $job->documentId === $documentId &&
                   $job->userId === $userId &&
                   $job->tenantId === $tenantId;
        });
    }

    /**
     * Test SendEmailJob can be dispatched
     */
    public function test_send_email_job_can_be_dispatched(): void
    {
        Queue::fake();

        $to = 'test@example.com';
        $subject = 'Test Email';
        $template = 'emails.test';
        $data = ['name' => 'Test User'];
        $tenantId = 'tenant-123';

        SendEmailJob::dispatch($to, $subject, $template, $data, $tenantId);

        Queue::assertPushed(SendEmailJob::class, function ($job) use ($to, $subject, $template) {
            return $job->to === $to &&
                   $job->subject === $subject &&
                   $job->template === $template;
        });
    }

    /**
     * Test queue statistics
     */
    public function test_queue_statistics(): void
    {
        $stats = $this->queueService->getQueueStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('connection', $stats);
        $this->assertArrayHasKey('queues', $stats);
        $this->assertArrayHasKey('total_jobs', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('workers', $stats);
    }

    /**
     * Test queue metrics
     */
    public function test_queue_metrics(): void
    {
        $metrics = $this->queueService->getQueueMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('queue_jobs_total', $metrics);
        $this->assertArrayHasKey('queue_jobs_failed_total', $metrics);
        $this->assertArrayHasKey('queue_jobs_processing', $metrics);
        $this->assertArrayHasKey('queue_workers_active', $metrics);
        $this->assertArrayHasKey('timestamp', $metrics);
    }

    /**
     * Test queue health status
     */
    public function test_queue_health_status(): void
    {
        $health = $this->queueService->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('issues', $health);
        $this->assertArrayHasKey('recommendations', $health);
        $this->assertContains($health['status'], ['healthy', 'warning', 'critical']);
    }

    /**
     * Test retry failed jobs
     */
    public function test_retry_failed_jobs(): void
    {
        $result = $this->queueService->retryAllFailedJobs('default');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('count', $result);
    }

    /**
     * Test clear failed jobs
     */
    public function test_clear_failed_jobs(): void
    {
        $result = $this->queueService->clearFailedJobs('default');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('count', $result);
    }

    /**
     * Test active workers
     */
    public function test_active_workers(): void
    {
        $workers = $this->queueService->getActiveWorkers();

        $this->assertIsArray($workers);
    }

    /**
     * Test job retry mechanism
     */
    public function test_job_retry_mechanism(): void
    {
        // Create a test failed job
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => 'test-uuid-123',
                'displayName' => 'App\\Jobs\\TestJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => 3,
                'maxExceptions' => null,
                'failOnTimeout' => false,
                'backoff' => null,
                'timeout' => null,
                'retryUntil' => null,
                'data' => [
                    'commandName' => 'App\\Jobs\\TestJob',
                    'command' => serialize(new \App\Jobs\TestJob())
                ]
            ]),
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $result = $this->queueService->retryJob('test-uuid-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test exponential backoff configuration
     */
    public function test_exponential_backoff_configuration(): void
    {
        $job = new ProcessDocumentJob('doc-123', 'user-123', 'tenant-123');
        
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 300, 900], $job->backoff);
        $this->assertEquals(300, $job->timeout);
    }

    /**
     * Test email job configuration
     */
    public function test_email_job_configuration(): void
    {
        $job = new SendEmailJob('test@example.com', 'Test', 'emails.test');
        
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 120, 300], $job->backoff);
        $this->assertEquals(120, $job->timeout);
    }
}
