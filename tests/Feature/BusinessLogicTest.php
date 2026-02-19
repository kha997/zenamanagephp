<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Component;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SSOT\FixtureFactory;

/**
 * Test chức năng nghiệp vụ cơ bản của hệ thống
 */
class BusinessLogicTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    private function createCoreFixtureSet(): array
    {
        $tenant = $this->createTenant([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'trial',
            'is_active' => true,
        ]);

        $user = $this->createTenantUserWithRbac($tenant, 'member', 'member', [], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        $project = $this->createProjectForTenant($tenant, $user, [
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test Description',
            'status' => 'planning',
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        return ['tenant' => $tenant, 'user' => $user, 'project' => $project];
    }

    /**
     * Test tạo và quản lý project cơ bản
     */
    public function test_can_create_and_manage_project(): void
    {
        ['tenant' => $tenant, 'project' => $project] = $this->createCoreFixtureSet();

        // Kiểm tra project được tạo
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Test Project',
            'tenant_id' => $tenant->id,
        ]);

        // Cập nhật project
        $project->update(['status' => 'active']);
        
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test tạo và quản lý task
     */
    public function test_can_create_and_manage_task(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'project' => $project] = $this->createCoreFixtureSet();

        // Tạo task
        $task = Task::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => 'Test Task',
            'description' => 'Test Task Description',
            'status' => 'pending',
            'priority' => 'medium',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        // Kiểm tra task được tạo
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Test Task',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
        ]);

        // Cập nhật task
        $task->update(['status' => 'in_progress']);
        
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Test tạo và quản lý component
     */
    public function test_can_create_and_manage_component(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'project' => $project] = $this->createCoreFixtureSet();

        // Tạo component
        $component = Component::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => 'Test Component',
            'description' => 'Test Component Description',
            'type' => 'structural',
            'status' => 'pending',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        // Kiểm tra component được tạo
        $this->assertDatabaseHas('components', [
            'id' => $component->id,
            'name' => 'Test Component',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
        ]);

        // Cập nhật component
        $component->update(['status' => 'completed']);
        
        $this->assertDatabaseHas('components', [
            'id' => $component->id,
            'status' => 'completed',
        ]);
    }

    /**
     * Test quan hệ giữa các entities
     */
    public function test_entity_relationships(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'project' => $project] = $this->createCoreFixtureSet();

        // Tạo task
        $task = Task::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => 'Test Task',
            'description' => 'Test Task Description',
            'status' => 'pending',
            'priority' => 'medium',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        // Tạo component
        $component = Component::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => 'Test Component',
            'description' => 'Test Component Description',
            'type' => 'structural',
            'status' => 'pending',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        // Kiểm tra quan hệ - Debug trước
        $this->assertNotNull($user->tenant_id, 'User should have tenant_id');
        $this->assertNotNull($project->tenant_id, 'Project should have tenant_id');
        $this->assertNotNull($task->tenant_id, 'Task should have tenant_id');
        $this->assertNotNull($component->tenant_id, 'Component should have tenant_id');
        
        // Load relationships
        $user->load('tenant');
        $project->load('tenant');
        $task->load('tenant', 'project');
        $component->load('tenant', 'project');
        
        $this->assertEquals($tenant->id, $user->tenant->id);
        $this->assertEquals($tenant->id, $project->tenant->id);
        $this->assertEquals($tenant->id, $task->tenant->id);
        $this->assertEquals($tenant->id, $component->tenant->id);
        $this->assertEquals($project->id, $task->project->id);
        $this->assertEquals($project->id, $component->project->id);
    }
}
