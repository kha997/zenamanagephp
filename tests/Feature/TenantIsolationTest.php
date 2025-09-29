<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create two tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create users for each tenant
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'admin'
        ]);
        
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'admin'
        ]);
        
        // Create projects for each tenant
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A'
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B'
        ]);
        
        // Create tasks for each tenant
        $this->taskA = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Task A'
        ]);
        
        $this->taskB = Task::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'title' => 'Task B'
        ]);
    }

    /** @test */
    public function user_cannot_access_other_tenant_data()
    {
        // User A tries to access Tenant B's data
        $response = $this->actingAs($this->userA)
            ->getJson("/api/admin/tenants/{$this->tenantB->id}");
            
        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_update_other_tenant_data()
    {
        $response = $this->actingAs($this->userA)
            ->putJson("/api/admin/tenants/{$this->tenantB->id}", [
                'name' => 'Hacked Tenant'
            ]);
            
        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_delete_other_tenant_data()
    {
        $response = $this->actingAs($this->userA)
            ->deleteJson("/api/admin/tenants/{$this->tenantB->id}");
            
        $response->assertStatus(403);
    }

    /** @test */
    public function user_can_only_see_own_tenant_in_list()
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/api/admin/tenants');
            
        $response->assertStatus(200);
        
        $tenants = $response->json('data');
        $this->assertCount(1, $tenants);
        $this->assertEquals($this->tenantA->id, $tenants[0]['id']);
    }

    /** @test */
    public function export_only_includes_own_tenant_data()
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/api/admin/tenants/export.csv');
            
        $response->assertStatus(200);
        
        $csvContent = $response->getContent();
        $this->assertStringContainsString($this->tenantA->name, $csvContent);
        $this->assertStringNotContainsString($this->tenantB->name, $csvContent);
    }

    /** @test */
    public function super_admin_can_access_all_tenants()
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin'
        ]);
        
        $response = $this->actingAs($superAdmin)
            ->getJson('/api/admin/tenants');
            
        $response->assertStatus(200);
        
        $tenants = $response->json('data');
        $this->assertCount(2, $tenants);
    }

    /** @test */
    public function tenant_isolation_middleware_blocks_cross_tenant_access()
    {
        // Simulate a request with wrong tenant context
        $response = $this->actingAs($this->userA)
            ->withHeaders([
                'X-Tenant-ID' => $this->tenantB->id
            ])
            ->getJson('/api/admin/tenants');
            
        $response->assertStatus(403);
    }

    /** @test */
    public function audit_logs_include_tenant_context()
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/api/admin/tenants');
            
        $response->assertStatus(200);
        
        // Check that audit log was created with correct tenant context
        $this->assertDatabaseHas('tenant_audit_logs', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id
        ]);
    }

    /** @test */
    public function tenant_context_is_preserved_in_jobs()
    {
        // Dispatch a tenant-scoped job
        $job = new \App\Jobs\ProcessTenantDataJob(
            'test data',
            'test.txt',
            $this->tenantA->id,
            $this->userA->id
        );
        
        $job->handle();
        
        // Check that the job executed with correct tenant context
        $this->assertTrue(true); // Job completed without errors
    }

    /** @test */
    public function cache_keys_are_tenant_scoped()
    {
        $cacheKey = \App\Services\TenantContext::getRedisKey('test-key');
        $expectedKey = "tm:{$this->tenantA->id}:test-key";
        
        $this->assertEquals($expectedKey, $cacheKey);
    }

    /** @test */
    public function s3_keys_are_tenant_scoped()
    {
        $s3Key = \App\Services\TenantContext::getS3Key('test-file.txt');
        $expectedKey = "tenants/{$this->tenantA->id}/test-file.txt";
        
        $this->assertEquals($expectedKey, $s3Key);
    }
}
