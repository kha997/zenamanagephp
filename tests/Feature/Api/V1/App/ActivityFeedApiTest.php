<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * ActivityFeedApiTest
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Tests for activity feed endpoint
 * 
 * @group activity-feed
 * @group api-v1
 */
class ActivityFeedApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(248001);
        $this->setDomainName('activity-feed-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Refresh users
        $this->userA->refresh();
        $this->userB->refresh();

        // Create project for userA
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
            'code' => 'PRJ-A',
            'created_by' => $this->userA->id,
        ]);
    }

    /**
     * Test returns only current user related activities
     */
    public function test_returns_only_current_user_related_activities(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create activities for userA (should appear)
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'action' => 'task_updated',
            'entity_type' => 'Task',
            'entity_id' => 'task-1',
            'description' => 'Task updated by userA',
            'metadata' => [],
        ]);

        // Create activity for userB (should NOT appear)
        ProjectActivity::create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => Project::factory()->create(['tenant_id' => $this->tenantB->id])->id,
            'user_id' => $this->userB->id,
            'action' => 'task_updated',
            'entity_type' => 'Task',
            'entity_id' => 'task-2',
            'description' => 'Task updated by userB',
            'metadata' => [],
        ]);

        // Create audit log for userA (should appear)
        $auditLogA = new AuditLog();
        $auditLogA->tenant_id = $this->tenantA->id;
        $auditLogA->user_id = $this->userA->id;
        $auditLogA->action = 'co.approved';
        $auditLogA->entity_type = 'ChangeOrder';
        $auditLogA->entity_id = 'co-1';
        $auditLogA->project_id = $this->projectA->id;
        $auditLogA->payload_before = ['status' => 'pending'];
        $auditLogA->payload_after = ['status' => 'approved'];
        $auditLogA->save();

        // Create audit log for userB (should NOT appear)
        $auditLogB = new AuditLog();
        $auditLogB->tenant_id = $this->tenantB->id;
        $auditLogB->user_id = $this->userB->id;
        $auditLogB->action = 'co.approved';
        $auditLogB->entity_type = 'ChangeOrder';
        $auditLogB->entity_id = 'co-2';
        $auditLogB->project_id = Project::factory()->create(['tenant_id' => $this->tenantB->id])->id;
        $auditLogB->payload_before = ['status' => 'pending'];
        $auditLogB->payload_after = ['status' => 'approved'];
        $auditLogB->save();

        $response = $this->getJson('/api/v1/app/activity-feed');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'timestamp',
                        'module',
                        'type',
                        'title',
                        'summary',
                        'project_id',
                        'project_name',
                        'entity_type',
                        'entity_id',
                        'actor_id',
                        'actor_name',
                        'is_directly_related',
                    ],
                ],
            ],
            'meta' => [
                'page',
                'per_page',
                'total',
            ],
        ]);

        $items = $response->json('data.items');
        $this->assertCount(2, $items); // Only userA's activities

        // Verify all items belong to userA
        foreach ($items as $item) {
            $this->assertEquals($this->userA->id, $item['actor_id']);
        }
    }

    /**
     * Test can filter by module
     */
    public function test_can_filter_by_module(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create task activity
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'action' => 'task_updated',
            'entity_type' => 'Task',
            'entity_id' => 'task-1',
            'description' => 'Task updated',
            'metadata' => [],
        ]);

        // Create document activity
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'action' => 'document_uploaded',
            'entity_type' => 'Document',
            'entity_id' => 'doc-1',
            'description' => 'Document uploaded',
            'metadata' => [],
        ]);

        // Create cost audit log
        $auditLog = new AuditLog();
        $auditLog->tenant_id = $this->tenantA->id;
        $auditLog->user_id = $this->userA->id;
        $auditLog->action = 'co.approved';
        $auditLog->entity_type = 'ChangeOrder';
        $auditLog->entity_id = 'co-1';
        $auditLog->project_id = $this->projectA->id;
        $auditLog->payload_before = [];
        $auditLog->payload_after = [];
        $auditLog->save();

        // Test tasks filter
        $response = $this->getJson('/api/v1/app/activity-feed?module=tasks');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(1, count($items));
        foreach ($items as $item) {
            $this->assertEquals('tasks', $item['module']);
        }

        // Test documents filter
        $response = $this->getJson('/api/v1/app/activity-feed?module=documents');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(1, count($items));
        foreach ($items as $item) {
            $this->assertEquals('documents', $item['module']);
        }

        // Test cost filter
        $response = $this->getJson('/api/v1/app/activity-feed?module=cost');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(1, count($items));
        foreach ($items as $item) {
            $this->assertEquals('cost', $item['module']);
        }
    }

    /**
     * Test pagination works
     */
    public function test_pagination_works(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create 25 activities
        for ($i = 1; $i <= 25; $i++) {
            ProjectActivity::create([
                'tenant_id' => $this->tenantA->id,
                'project_id' => $this->projectA->id,
                'user_id' => $this->userA->id,
                'action' => 'task_updated',
                'entity_type' => 'Task',
                'entity_id' => "task-{$i}",
                'description' => "Task {$i} updated",
                'metadata' => [],
            ]);
        }

        // Test first page
        $response = $this->getJson('/api/v1/app/activity-feed?page=1&per_page=20');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'items' => [],
            ],
            'meta' => [
                'page',
                'per_page',
                'total',
            ],
        ]);
        $data = $response->json('data');
        $meta = $response->json('meta');
        $this->assertCount(20, $data['items']);
        $this->assertEquals(1, $meta['page']);
        $this->assertEquals(20, $meta['per_page']);
        $this->assertEquals(25, $meta['total']);

        // Test second page
        $response = $this->getJson('/api/v1/app/activity-feed?page=2&per_page=20');
        $response->assertStatus(200);
        $data = $response->json('data');
        $meta = $response->json('meta');
        $this->assertCount(5, $data['items']); // Remaining 5 items
        $this->assertEquals(2, $meta['page']);
    }

    /**
     * Test requires authentication
     */
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/activity-feed');
        $response->assertStatus(401);
    }

    /**
     * Test search filter
     */
    public function test_search_filter(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create activity with specific description
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'action' => 'task_updated',
            'entity_type' => 'Task',
            'entity_id' => 'task-1',
            'description' => 'Important task updated',
            'metadata' => [],
        ]);

        // Create another activity
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'action' => 'document_uploaded',
            'entity_type' => 'Document',
            'entity_id' => 'doc-1',
            'description' => 'Document uploaded',
            'metadata' => [],
        ]);

        // Search for "Important"
        $response = $this->getJson('/api/v1/app/activity-feed?search=Important');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(1, count($items));
        $found = false;
        foreach ($items as $item) {
            if (stripos($item['summary'], 'Important') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find activity with "Important" in summary');
    }

    /**
     * Test activities with assignee metadata
     */
    public function test_includes_activities_where_user_is_assignee(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create activity where userA is assignee (not actor)
        ProjectActivity::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userB->id, // Different actor
            'action' => 'project_task_assigned',
            'entity_type' => 'ProjectTask',
            'entity_id' => 'task-1',
            'description' => 'Task assigned to userA',
            'metadata' => [
                'new_assignee_id' => $this->userA->id, // userA is assignee
            ],
        ]);

        $response = $this->getJson('/api/v1/app/activity-feed');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        
        // Should include activity where userA is assignee
        $found = false;
        foreach ($items as $item) {
            if ($item['entity_id'] === 'task-1') {
                $found = true;
                $this->assertTrue($item['is_directly_related'], 'Activity should be marked as directly related');
                break;
            }
        }
        $this->assertTrue($found, 'Should find activity where user is assignee');
    }

    /**
     * Test audit logs with approver information
     */
    public function test_includes_audit_logs_where_user_is_approver(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create audit log where userA is approver (not actor)
        $auditLog = new AuditLog();
        $auditLog->tenant_id = $this->tenantA->id;
        $auditLog->user_id = $this->userB->id; // Different actor
        $auditLog->action = 'co.first_approved';
        $auditLog->entity_type = 'ChangeOrder';
        $auditLog->entity_id = 'co-1';
        $auditLog->project_id = $this->projectA->id;
        $auditLog->payload_before = ['first_approved_by' => null];
        $auditLog->payload_after = [
            'first_approved_by' => $this->userA->id, // userA is approver
        ];
        $auditLog->save();

        $response = $this->getJson('/api/v1/app/activity-feed');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        
        // Should include audit log where userA is approver
        $found = false;
        foreach ($items as $item) {
            if ($item['entity_id'] === 'co-1') {
                $found = true;
                $this->assertTrue($item['is_directly_related'], 'Audit log should be marked as directly related');
                break;
            }
        }
        $this->assertTrue($found, 'Should find audit log where user is approver');
    }
}
