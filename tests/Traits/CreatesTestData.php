<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Task;
use Src\InteractionLogs\Models\InteractionLog;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\Notification\Models\NotificationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Trait để tạo test data chung cho các test cases
 */
trait CreatesTestData
{
    /**
     * Tạo authenticated user với token
     */
    protected function createAuthenticatedUser(array $attributes = []): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password123')
        ], $attributes));
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);
        
        return [
            'user' => $user,
            'tenant' => $tenant,
            'token' => $loginResponse->json('data.token')
        ];
    }
    
    /**
     * Tạo project với user và tenant
     */
    protected function createProjectWithUser(array $projectAttributes = []): array
    {
        $authData = $this->createAuthenticatedUser();
        
        $project = Project::factory()->create(array_merge([
            'tenant_id' => $authData['tenant']->id
        ], $projectAttributes));
        
        return array_merge($authData, ['project' => $project]);
    }
    
    /**
     * Tạo roles và permissions cơ bản
     */
    protected function createBasicRolesAndPermissions(): void
    {
        $permissions = [
            'project.create', 'project.read', 'project.update', 'project.delete',
            'task.create', 'task.read', 'task.update', 'task.delete',
            'interaction_log.create', 'interaction_log.read', 'interaction_log.update', 'interaction_log.delete'
        ];
        
        foreach ($permissions as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode);
            Permission::factory()->create([
                'code' => $permissionCode,
                'module' => $module,
                'action' => $action
            ]);
        }
        
        $adminRole = Role::factory()->create([
            'name' => 'Admin',
            'scope' => 'system'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
    }
}

/**
 * Trait để tạo test data cho các test cases
 * Cung cấp các helper methods để tạo models với dữ liệu test phù hợp
 */
trait CreatesTestData
{
    /**
     * Tạo tenant với dữ liệu test
     */
    protected function createTestTenant(array $attributes = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'name' => 'Test Tenant',
            'domain' => 'test-tenant.example.com',
            'status' => 'active'
        ], $attributes));
    }

    /**
     * Tạo user với tenant và roles
     */
    protected function createTestUser(array $attributes = [], ?Tenant $tenant = null): User
    {
        $tenant = $tenant ?? $this->createTestTenant();
        
        return User::factory()->create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id
        ], $attributes));
    }

    /**
     * Tạo admin user với full permissions
     */
    protected function createAdminUser(?Tenant $tenant = null): User
    {
        $user = $this->createTestUser(['name' => 'Admin User', 'email' => 'admin@example.com'], $tenant);
        
        // Assign admin role
        $adminRole = Role::factory()->create([
            'name' => 'Super Admin',
            'scope' => 'system'
        ]);
        
        $user->systemRoles()->attach($adminRole->id);
        
        return $user;
    }

    /**
     * Tạo project với components và tasks
     */
    protected function createTestProject(array $attributes = [], ?Tenant $tenant = null): Project
    {
        $tenant = $tenant ?? $this->createTestTenant();
        
        return Project::factory()->create(array_merge([
            'name' => 'Test Project',
            'description' => 'A test project for unit testing',
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'progress' => 0
        ], $attributes));
    }

    /**
     * Tạo component với project
     */
    protected function createTestComponent(Project $project, array $attributes = []): Component
    {
        return Component::factory()->create(array_merge([
            'project_id' => $project->id,
            'name' => 'Test Component',
            'progress_percent' => 0,
            'planned_cost' => 10000.00,
            'actual_cost' => 0.00
        ], $attributes));
    }

    /**
     * Tạo task với project và component
     */
    protected function createTestTask(Project $project, ?Component $component = null, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'project_id' => $project->id,
            'component_id' => $component?->id,
            'name' => 'Test Task',
            'status' => 'pending',
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ], $attributes));
    }

    /**
     * Tạo interaction log
     */
    protected function createTestInteractionLog(Project $project, User $user, array $attributes = []): InteractionLog
    {
        return InteractionLog::factory()->create(array_merge([
            'project_id' => $project->id,
            'type' => 'note',
            'description' => 'Test interaction log',
            'visibility' => 'internal',
            'created_by' => $user->id
        ], $attributes));
    }

    /**
     * Tạo change request
     */
    protected function createTestChangeRequest(Project $project, User $user, array $attributes = []): ChangeRequest
    {
        return ChangeRequest::factory()->create(array_merge([
            'project_id' => $project->id,
            'code' => 'CR-TEST-001',
            'title' => 'Test Change Request',
            'description' => 'A test change request',
            'status' => 'draft',
            'created_by' => $user->id
        ], $attributes));
    }

    /**
     * Tạo notification rule
     */
    protected function createTestNotificationRule(User $user, ?Project $project = null, array $attributes = []): NotificationRule
    {
        return NotificationRule::factory()->create(array_merge([
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'event_key' => 'task.created',
            'min_priority' => 'normal',
            'channels' => ['inapp'],
            'is_enabled' => true
        ], $attributes));
    }

    /**
     * Tạo role với permissions
     */
    protected function createTestRole(array $permissions = [], array $attributes = []): Role
    {
        $role = Role::factory()->create(array_merge([
            'name' => 'Test Role',
            'scope' => 'custom'
        ], $attributes));

        if (!empty($permissions)) {
            foreach ($permissions as $permissionCode) {
                $permission = Permission::factory()->create(['code' => $permissionCode]);
                $role->permissions()->attach($permission->id);
            }
        }

        return $role;
    }

    /**
     * Setup complete test environment với user, tenant, project
     */
    protected function setupTestEnvironment(): array
    {
        $tenant = $this->createTestTenant();
        $user = $this->createTestUser([], $tenant);
        $project = $this->createTestProject([], $tenant);
        $component = $this->createTestComponent($project);
        $task = $this->createTestTask($project, $component);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'project' => $project,
            'component' => $component,
            'task' => $task
        ];
    }
}