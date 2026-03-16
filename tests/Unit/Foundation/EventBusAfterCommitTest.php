<?php declare(strict_types=1);

namespace Tests\Unit\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Foundation\EventBus;
use Tests\TestCase;

class EventBusAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EventBus::clearAll();
    }

    protected function tearDown(): void
    {
        EventBus::clearAll();

        parent::tearDown();
    }

    public function test_publish_after_commit_runs_immediately_without_transaction(): void
    {
        $received = [];

        EventBus::subscribe('Audit.Project.Created', static function (array $payload) use (&$received): void {
            $received[] = $payload['entityId'] ?? null;
        });

        EventBus::publishAfterCommit('Audit.Project.Created', [
            'entityId' => 'project-1',
            'projectId' => 'project-1',
            'actorId' => 'user-1',
        ]);

        $this->assertSame(['project-1'], $received);
    }

    public function test_publish_after_commit_defers_listeners_until_transaction_commits(): void
    {
        $received = [];

        EventBus::subscribe('Audit.Project.Created', static function (array $payload) use (&$received): void {
            $received[] = $payload['entityId'] ?? null;
        });

        DB::transaction(function () use (&$received): void {
            EventBus::publishAfterCommit('Audit.Project.Created', [
                'entityId' => 'project-2',
                'projectId' => 'project-2',
                'actorId' => 'user-2',
            ]);

            $this->assertSame([], $received);
        });

        $this->assertSame(['project-2'], $received);
    }
}
