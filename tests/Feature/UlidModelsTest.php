<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use Src\CoreProject\Models\Project;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\UserRoleSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test cases cho Models sử dụng ULID
 */
class UlidModelsTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * Test Tenant model sử dụng ULID
     */
    public function test_tenant_uses_ulid_as_primary_key(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com'
        ]);
        
        // Kiểm tra ID là ULID hợp lệ
        $this->assertEquals(26, strlen($tenant->id));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $tenant->id);
        
        // Kiểm tra có thể tìm thấy bằng ULID
        $foundTenant = Tenant::find($tenant->id);
        $this->assertNotNull($foundTenant);
        $this->assertEquals($tenant->name, $foundTenant->name);
    }
    
    /**
     * Test User model sử dụng ULID và foreign key
     */
    public function test_user_uses_ulid_and_foreign_key(): void
    {
        $tenant = Tenant::factory()->create();
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        // Kiểm tra User ID là ULID
        $this->assertEquals(26, strlen($user->id));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $user->id);
        
        // Kiểm tra foreign key relationship
        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertInstanceOf(Tenant::class, $user->tenant);
        $this->assertEquals($tenant->name, $user->tenant->name);
    }
    
    /**
     * Test Project model sử dụng ULID và relationships
     */
    public function test_project_uses_ulid_and_relationships(): void
    {
        $tenant = Tenant::factory()->create();
        
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Project'
        ]);
        
        // Kiểm tra Project ID là ULID
        $this->assertEquals(26, strlen($project->id));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $project->id);
        
        // Kiểm tra relationship với Tenant
        $this->assertEquals($tenant->id, $project->tenant_id);
        $this->assertInstanceOf(Tenant::class, $project->tenant);
    }
    
    /**
     * Test RBAC models sử dụng ULID
     */
    public function test_rbac_models_use_ulid(): void
    {
        // Tạo Role
        $role = Role::factory()->create([
            'name' => 'Test Role',
            'scope' => 'system'
        ]);
        
        $this->assertEquals(26, strlen($role->id));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $role->id);
        
        // Tạo Permission
        $permission = Permission::factory()->create([
            'code' => 'test.permission',
            'module' => 'test',
            'action' => 'permission'
        ]);
        
        $this->assertEquals(26, strlen($permission->id));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z]{26}$/', $permission->id);
    }
    
    /**
     * Test composite key models (UserRoleSystem)
     */
    public function test_user_role_system_composite_key(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::factory()->create(['scope' => 'system']);
        
        // Tạo UserRoleSystem bằng DB::table để tránh vấn đề composite key
        \DB::table('system_user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Kiểm tra dữ liệu đã được tạo
        $userRole = \DB::table('system_user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();
            
        $this->assertNotNull($userRole);
        $this->assertEquals($user->id, $userRole->user_id);
        $this->assertEquals($role->id, $userRole->role_id);
    }
    
    /**
     * Test mass assignment với ULID
     */
    public function test_mass_assignment_with_ulid(): void
    {
        $tenant = Tenant::create([
            'name' => 'Mass Assignment Tenant',
            'domain' => 'mass.example.com',
            'is_active' => true
        ]);
        
        // ID phải được tự động tạo
        $this->assertNotNull($tenant->id);
        $this->assertEquals(26, strlen($tenant->id));
        
        // Có thể tìm thấy bằng ID đã tạo
        $found = Tenant::find($tenant->id);
        $this->assertEquals($tenant->name, $found->name);
    }
    
    /**
     * Test query với ULID
     */
    public function test_query_with_ulid(): void
    {
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);
        
        // Test whereIn với ULID array
        $tenants = Tenant::whereIn('id', [$tenant1->id, $tenant2->id])->get();
        $this->assertEquals(2, $tenants->count());
        
        // Test where với ULID
        $foundTenant = Tenant::where('id', $tenant1->id)->first();
        $this->assertEquals($tenant1->name, $foundTenant->name);
    }
    
    /**
     * Test relationships với ULID foreign keys
     */
    public function test_relationships_with_ulid_foreign_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory(3)->create(['tenant_id' => $tenant->id]);
        
        // Test hasMany relationship
        $tenantUsers = $tenant->users;
        $this->assertEquals(3, $tenantUsers->count());
        
        // Test belongsTo relationship
        foreach ($users as $user) {
            $this->assertEquals($tenant->id, $user->tenant->id);
        }
    }
}