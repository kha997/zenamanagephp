<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTenantGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_tenant_id_from_entity_blocks_cross_tenant_ulid(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);
        $taskB = ProjectTask::create([
            'tenant_id' => $tenantB->id,
            'project_id' => $projectB->id,
            'name' => 'Cross Tenant Task',
            'sort_order' => 1,
            'duration_days' => 1,
            'progress_percent' => 0,
            'status' => ProjectTask::STATUS_PENDING,
        ]);

        $request = app('request');
        $request->attributes->set('active_tenant_id', $tenantA->id);

        $service = app(NotificationService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveTenantIdFromEntity');
        $method->setAccessible(true);

        $resolved = $method->invoke($service, 'task', $taskB->id, $tenantA->id);

        $this->assertNull($resolved);
    }
}
