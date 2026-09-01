<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role as AppRole;
use App\Models\Permission as AppPermission;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

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
        $this->grantAppRbacPermissions();

        $this->token = $this->user->createToken('rbac-api-test')->plainTextToken;
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
            Permission::factory()->create([
                'code' => $permissionCode,
                'module' => explode('.', $permissionCode)[0],
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::factory()->create([
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
     * Grant app-level RBAC permissions used by route middleware.
     */
    private function grantAppRbacPermissions(): void
    {
        $permissionCodes = [
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
            'permission.view',
            'role.assign',
        ];

        // GAP-042: Src\RBAC\Models\Permission and App\Models\Permission now
        // query the identical 'permissions' table (Option A convergence).
        // createRolesAndPermissions() above may have already inserted a row
        // for an overlapping code (e.g. 'role.create') via the Src\RBAC
        // factory, which does not set 'name' to match the code. This gate
        // check (App\Models\User::hasPermission()) matches on 'name', not
        // 'code' — updateOrCreate() (not firstOrCreate()) so this method is
        // authoritative for 'name' regardless of insertion order.
        $permissionIds = collect($permissionCodes)->map(function (string $code) {
            [$module, $action] = explode('.', $code, 2);

            return AppPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => $module,
                    'action' => $action,
                    'description' => 'Permission for ' . $code,
                ]
            )->id;
        })->all();

        $appRole = AppRole::firstOrCreate(
            ['name' => 'rbac_test_admin', 'scope' => 'system'],
            ['description' => 'RBAC test admin role', 'allow_override' => true]
        );

        $appRole->permissions()->syncWithoutDetaching($permissionIds);
        $this->user->roles()->syncWithoutDetaching([$appRole->id]);
    }

    /**
     * Test get all roles
     */
    public function test_can_get_all_roles()
    {
        Sanctum::actingAs($this->user);

        Role::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/rbac/roles');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'roles' => [
                             'data' => [
                                 '*' => [
                                     'id',
                                     'name',
                                     'scope',
                                     'description',
                                     'created_at',
                                     'updated_at'
                                 ]
                             ],
                             'meta'
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
        Sanctum::actingAs($this->user);

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
        Sanctum::actingAs($this->user);

        // GAP-042 §6: a custom-scope role with no tenant_id is a global role,
        // read-only through this tenant-scoped surface — must be bound to
        // this test's own tenant to exercise a genuinely mutable role.
        $role = Role::factory()->create([
            'scope' => 'custom',
            'tenant_id' => $this->tenant->id,
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
        Sanctum::actingAs($this->user);

        // GAP-042 §6: bind to this test's own tenant — see test_can_update_role.
        $role = Role::factory()->create([
            'scope' => 'custom',
            'tenant_id' => $this->tenant->id,
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
        Sanctum::actingAs($this->user);

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
        Sanctum::actingAs($this->user);

        $targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // GAP-042 §6a: assignSystemRole() now verifies the target role
        // actually has scope=system (closes a previously-unchecked gap) —
        // this test must use a deterministic system-scope role, not the
        // factory's random default scope.
        $role = Role::factory()->create(['scope' => 'system']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/rbac/user-roles', [
            'user_id' => $targetUser->id,
            'role_id' => $role->id,
            'scope' => 'system'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Vai trò đã được gán thành công'
                     ]
                 ]);

        $this->assertDatabaseHas('system_user_roles', [
            'user_id' => $targetUser->id,
            'role_id' => $role->id
        ]);
    }

    /**
     * Test remove role from user
     */
    public function test_can_remove_role_from_user()
    {
        Sanctum::actingAs($this->user);

        $targetUser = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $role = Role::factory()->create(['scope' => 'system']);
        $targetUser->systemRoles()->attach($role->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/rbac/user-roles/{$targetUser->id}/{$role->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Vai trò đã được gỡ bỏ thành công'
                     ]
                 ]);

        $this->assertDatabaseMissing('system_user_roles', [
            'user_id' => $targetUser->id,
            'role_id' => $role->id
        ]);
    }

    /**
     * Test unauthorized access returns 401
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/v1/rbac/roles');
        $response->assertStatus(401);
    }

    /**
     * Test forbidden access without permissions
     */
    public function test_forbidden_access_without_permissions()
    {
        // Tạo user không có quyền
        $userWithoutPermissions = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123')
        ]);
        
        Sanctum::actingAs($userWithoutPermissions);

        $response = $this->postJson('/api/v1/rbac/roles', [
            'name' => 'Test Role',
            'scope' => 'custom',
            'description' => 'Test Description'
        ]);

        $response->assertStatus(403);
    }
}
