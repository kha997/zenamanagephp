<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RbacApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->createRolesAndPermissions();
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        $permissions = [
            'role.create',
            'role.read',
            'role.update',
            'role.delete',
            'permission.read',
            'user.assign_role',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => explode('.', $permissionCode)[0],
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test get all roles
     */
    public function test_can_get_all_roles()
    {
        Role::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/rbac/roles');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'roles' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'scope',
                                 'description',
                                 'created_at',
                                 'updated_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test create role
     */
    public function test_can_create_role()
    {
        $roleData = [
            'name' => 'Project Manager',
            'scope' => 'custom',
            'description' => 'Manages projects and teams',
            'permissions' => Permission::limit(3)->pluck('id')->toArray()
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/rbac/roles', $roleData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'role' => [
                             'id',
                             'name',
                             'scope',
                             'description',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Project Manager',
            'scope' => 'custom'
        ]);
    }

    /**
     * Test update role
     */
    public function test_can_update_role()
    {
        $role = Role::factory()->create([
            'scope' => 'custom'
        ]);

        $updateData = [
            'name' => 'Updated Role Name',
            'description' => 'Updated description'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/rbac/roles/{$role->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'role' => [
                             'id' => $role->id,
                             'name' => 'Updated Role Name'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Updated Role Name'
        ]);
    }

    /**
     * Test delete role
     */
    public function test_can_delete_role()
    {
        $role = Role::factory()->create([
            'scope' => 'custom'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/rbac/roles/{$role->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Vai trò đã được xóa thành công'
                     ]
                 ]);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id
        ]);
    }

    /**
     * Test get all permissions
     */
    public function test_can_get_all_permissions()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/rbac/permissions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'permissions' => [
                             '*' => [
                                 'id',
                                 'code',
                                 'module',
                                 'action',
                                 'description',
                                 'created_at',
                                 'updated_at'
                             ]
                         ]
                     ]
                 ]);
    }

    /**
     * Test assign role to user
     */
    public function test_can_assign_role_to_user()
    {
        $targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $role = Role::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/rbac/user-roles', [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
            'scope' => 'system'
        ]);

        $response->assertStatus(200)
                 ->assertJson(