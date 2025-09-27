<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\File;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class TestPoliciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'policies:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all policy implementations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Policy Implementations...');
        
        try {
            // Test File Policy
            $this->testFilePolicy();
            
            // Test Permission Policy
            $this->testPermissionPolicy();
            
            // Test Role Policy
            $this->testRolePolicy();
            
            // Test Tenant Policy
            $this->testTenantPolicy();
            
            $this->info('✅ All policy tests passed!');
        return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Policy test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function testFilePolicy()
    {
        $this->info('Testing File Policy...');
        
        try {
            // Create test data
            $tenant = Tenant::factory()->create();
            $this->info("Created tenant: {$tenant->id}");
            
            $admin = User::factory()->create(['tenant_id' => $tenant->id]);
            $this->assignRoleToUser($admin, 'admin');
            $this->info("Created admin user: {$admin->id}");
            
            $projectManager = User::factory()->create(['tenant_id' => $tenant->id]);
            $this->assignRoleToUser($projectManager, 'project_manager');
            $this->info("Created project manager user: {$projectManager->id}");
            
            $member = User::factory()->create(['tenant_id' => $tenant->id]);
            $this->assignRoleToUser($member, 'member');
            $this->info("Created member user: {$member->id}");
            
            // Test basic permissions without creating actual files
            $this->info("Admin roles: " . $admin->roles->pluck('name')->join(', '));
            $this->info("Admin hasRole('admin'): " . ($admin->hasRole('admin') ? 'true' : 'false'));
            $this->info("Admin is_active: " . ($admin->is_active ? 'true' : 'false'));
            
            $this->assertTrue($admin->can('viewAny', File::class), 'Admin should view any files');
            $this->assertTrue($admin->can('create', File::class), 'Admin should create files');
            $this->assertTrue($projectManager->can('create', File::class), 'Project manager should create files');
            $this->assertTrue($member->can('create', File::class), 'Member should create files');
            
            $this->info('✅ File Policy tests passed');
        } catch (\Exception $e) {
            $this->error("File Policy test failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    protected function testPermissionPolicy()
    {
        $this->info('Testing Permission Policy...');
        
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($admin, 'admin');
        
        $member = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($member, 'member');
        
        $permission = Permission::firstOrCreate([
            'code' => 'test.permission',
            'module' => 'test',
            'action' => 'permission'
        ], [
            'description' => 'Test permission for policy testing'
        ]);
        
        // Test permissions
        $this->assertTrue($admin->can('viewAny', Permission::class), 'Admin should view any permissions');
        $this->assertTrue($admin->can('view', $permission), 'Admin should view any permission');
        $this->assertFalse($member->can('viewAny', Permission::class), 'Member should not view permissions');
        $this->assertFalse($member->can('view', $permission), 'Member should not view permission');
        
        $this->info('✅ Permission Policy tests passed');
    }

    protected function testRolePolicy()
    {
        $this->info('Testing Role Policy...');
        
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($admin, 'admin');
        
        $projectManager = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($projectManager, 'project_manager');
        
        $member = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($member, 'member');
        
        $role = Role::factory()->create([
            'name' => 'test_role_' . $tenant->id,
            'tenant_id' => $tenant->id
        ]);
        
        // Test permissions
        $this->assertTrue($admin->can('viewAny', Role::class), 'Admin should view any roles');
        $this->assertTrue($projectManager->can('viewAny', Role::class), 'Project manager should view roles');
        $this->assertTrue($admin->can('view', $role), 'Admin should view any role');
        $this->assertTrue($projectManager->can('view', $role), 'Project manager should view tenant roles');
        $this->assertFalse($member->can('viewAny', Role::class), 'Member should not view roles');
        
        $this->info('✅ Role Policy tests passed');
    }

    protected function testTenantPolicy()
    {
        $this->info('Testing Tenant Policy...');
        
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($admin, 'admin');
        
        $projectManager = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($projectManager, 'project_manager');
        
        $member = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assignRoleToUser($member, 'member');
        
        // Test permissions
        $this->assertTrue($admin->can('viewAny', Tenant::class), 'Admin should view any tenants');
        $this->assertTrue($admin->can('view', $tenant), 'Admin should view any tenant');
        $this->assertTrue($projectManager->can('view', $tenant), 'Project manager should view own tenant');
        $this->assertTrue($member->can('view', $tenant), 'Member should view own tenant');
        
        $this->info('✅ Tenant Policy tests passed');
    }

    protected function assignRoleToUser(User $user, string $roleName)
    {
        // Find existing role or create new one with unique name per tenant
        $role = Role::where('name', $roleName)
                    ->where('tenant_id', $user->tenant_id)
                    ->first();
        
        if (!$role) {
            // Create role with unique name including tenant
            $uniqueName = $roleName . '_' . $user->tenant_id;
            $role = Role::create([
                'name' => $uniqueName,
                'tenant_id' => $user->tenant_id,
                'description' => "Test role: {$roleName}",
                'scope' => 'system'
            ]);
        }
        
        // Attach role to user if not already attached
        if (!$user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role->id);
        }
    }

    protected function assertTrue($condition, $message = '')
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: {$message}");
        }
    }

    protected function assertFalse($condition, $message = '')
    {
        if ($condition) {
            throw new \Exception("Assertion failed: {$message}");
        }
    }
}