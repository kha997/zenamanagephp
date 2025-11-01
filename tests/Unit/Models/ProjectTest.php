<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant first (required by rules)
        $this->tenant = Tenant::factory()->create();
        
        // Create test user with tenant_id (required by rules)
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test project creation with proper tenant isolation
     */
    public function test_can_create_project_with_tenant_isolation()
    {
        $projectData = [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project description',
            'status' => 'active',
            'owner_id' => $this->user->id,
            'priority' => 'normal',
            'progress_pct' => 0,
            'budget_total' => 10000.00,
            'budget_planned' => 8000.00,
            'budget_actual' => 0.00,
            'estimated_hours' => 40.0,
            'actual_hours' => 0.0,
            'risk_level' => 'low',
            'is_template' => false,
            'completion_percentage' => 0.0,
        ];

        $project = Project::create($projectData);

        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals($this->tenant->id, $project->tenant_id);
        $this->assertEquals('Test Project', $project->name);
        $this->assertEquals('TEST-001', $project->code);
        $this->assertEquals('active', $project->status);
        $this->assertEquals($this->user->id, $project->owner_id);
    }

    /**
     * Test tenant isolation - tenant A cannot access tenant B's projects
     */
    public function test_tenant_isolation_prevents_cross_tenant_access()
    {
        // Create second tenant
        $tenantB = Tenant::factory()->create();
        
        // Create project for tenant A
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant A Project'
        ]);
        
        // Create project for tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Project'
        ]);

        // Query projects for tenant A only
        $tenantAProjects = Project::where('tenant_id', $this->tenant->id)->get();
        
        $this->assertCount(1, $tenantAProjects);
        $this->assertEquals('Tenant A Project', $tenantAProjects->first()->name);
        $this->assertNotContains($projectB->id, $tenantAProjects->pluck('id'));
    }

    /**
     * Test project relationships
     */
    public function test_project_belongs_to_tenant()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertInstanceOf(Tenant::class, $project->tenant);
        $this->assertEquals($this->tenant->id, $project->tenant->id);
    }

    /**
     * Test project belongs to owner
     */
    public function test_project_belongs_to_owner()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id
        ]);

        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertEquals($this->user->id, $project->owner->id);
    }

    /**
     * Test project valid statuses
     */
    public function test_project_valid_statuses()
    {
        $validStatuses = Project::VALID_STATUSES;
        
        $this->assertContains('active', $validStatuses);
        $this->assertContains('archived', $validStatuses);
        $this->assertContains('completed', $validStatuses);
        $this->assertContains('on_hold', $validStatuses);
        $this->assertContains('cancelled', $validStatuses);
        $this->assertContains('planning', $validStatuses);
    }

    /**
     * Test project valid priorities
     */
    public function test_project_valid_priorities()
    {
        $validPriorities = Project::VALID_PRIORITIES;
        
        $this->assertContains('low', $validPriorities);
        $this->assertContains('normal', $validPriorities);
        $this->assertContains('high', $validPriorities);
        $this->assertContains('urgent', $validPriorities);
    }

    /**
     * Test project fillable attributes
     */
    public function test_project_fillable_attributes()
    {
        $fillable = (new Project())->getFillable();
        
        $expectedFillable = [
            'tenant_id',
            'name',
            'code',
            'description',
            'status',
            'owner_id',
            'tags',
            'start_date',
            'due_date',
            'end_date',
            'priority',
            'progress_pct',
            'budget_total',
            'budget_planned',
            'budget_actual',
            'estimated_hours',
            'actual_hours',
            'risk_level',
            'is_template',
            'template_id',
            'last_activity_at',
            'completion_percentage',
            'settings'
        ];
        
        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    /**
     * Test project casts
     */
    public function test_project_casts()
    {
        $casts = (new Project())->getCasts();
        
        $this->assertArrayHasKey('tags', $casts);
        $this->assertEquals('array', $casts['tags']);
        
        $this->assertArrayHasKey('settings', $casts);
        $this->assertEquals('array', $casts['settings']);
        
        $this->assertArrayHasKey('start_date', $casts);
        $this->assertEquals('date', $casts['start_date']);
        
        $this->assertArrayHasKey('due_date', $casts);
        $this->assertEquals('date', $casts['due_date']);
        
        $this->assertArrayHasKey('end_date', $casts);
        $this->assertEquals('date', $casts['end_date']);
        
        $this->assertArrayHasKey('last_activity_at', $casts);
        $this->assertEquals('datetime', $casts['last_activity_at']);
        
        $this->assertArrayHasKey('tags', $casts);
        $this->assertEquals('array', $casts['tags']);
    }

    /**
     * Test project default attributes
     */
    public function test_project_default_attributes()
    {
        $project = new Project();
        
        $this->assertEquals('active', $project->status);
        $this->assertEquals('normal', $project->priority);
        $this->assertEquals(0, $project->progress_pct);
        $this->assertEquals(0, $project->budget_total);
        $this->assertEquals(0, $project->budget_planned);
        $this->assertEquals(0, $project->budget_actual);
        $this->assertEquals(0, $project->estimated_hours);
        $this->assertEquals(0, $project->actual_hours);
        $this->assertEquals('low', $project->risk_level);
        $this->assertFalse($project->is_template);
        $this->assertEquals(0, $project->completion_percentage);
    }

    /**
     * Test project table name
     */
    public function test_project_table_name()
    {
        $this->assertEquals('projects', (new Project())->getTable());
    }

    /**
     * Test project primary key
     */
    public function test_project_primary_key()
    {
        $this->assertEquals('id', (new Project())->getKeyName());
    }

    /**
     * Test project key type
     */
    public function test_project_key_type()
    {
        $this->assertEquals('string', (new Project())->getKeyType());
    }

    /**
     * Test project incrementing
     */
    public function test_project_incrementing()
    {
        $this->assertFalse((new Project())->getIncrementing());
    }

    /**
     * Test project uses ULID
     */
    public function test_project_uses_ulid()
    {
        $project = new Project();
        $this->assertEquals('string', $project->getKeyType());
        $this->assertFalse($project->getIncrementing());
    }
}