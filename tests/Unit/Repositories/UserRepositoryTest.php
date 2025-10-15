<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Repositories\UserRepository;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected $userRepository;
    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepository = new UserRepository(new User());
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function it_can_get_all_users_with_pagination()
    {
        // Create additional users
        User::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->getAll(['tenant_id' => $this->tenant->id], 10);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(6, $result->total()); // 1 original + 5 new
    }

    /** @test */
    public function it_can_filter_users_by_tenant_id()
    {
        // Create users for different tenant
        $otherTenant = Tenant::factory()->create();
        User::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
        
        $result = $this->userRepository->getAll(['tenant_id' => $this->tenant->id], 10);
        
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_can_filter_users_by_role()
    {
        // Create role and assign to user
        $role = Role::factory()->create(['name' => 'admin']);
        $this->user->roles()->attach($role);
        
        $result = $this->userRepository->getAll(['role' => 'admin'], 10);
        
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_can_search_users()
    {
        // Create user with specific name
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'tenant_id' => $this->tenant->id
        ]);
        
        $result = $this->userRepository->search('John', 10);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_user_by_id()
    {
        $result = $this->userRepository->getById($this->user->id);
        
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    /** @test */
    public function it_can_get_user_by_email()
    {
        $result = $this->userRepository->getByEmail($this->user->email);
        
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->email, $result->email);
    }

    /** @test */
    public function it_can_get_users_by_tenant_id()
    {
        $result = $this->userRepository->getByTenantId($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_create_user()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'tenant_id' => $this->tenant->id
        ];
        
        $result = $this->userRepository->create($userData);
        
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Test User', $result->name);
        $this->assertEquals('test@example.com', $result->email);
    }

    /** @test */
    public function it_can_update_user()
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];
        
        $result = $this->userRepository->update($this->user->id, $updateData);
        
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('updated@example.com', $result->email);
    }

    /** @test */
    public function it_can_delete_user()
    {
        $result = $this->userRepository->delete($this->user->id);
        
        $this->assertTrue($result);
        $this->assertNull(User::find($this->user->id));
    }

    /** @test */
    public function it_can_soft_delete_user()
    {
        $this->markTestSkipped('Users table does not have deleted_at column');
    }

    /** @test */
    public function it_can_restore_soft_deleted_user()
    {
        $this->markTestSkipped('User model does not use SoftDeletes trait');
    }

    /** @test */
    public function it_can_get_active_users()
    {
        // Create active and inactive users
        User::factory()->create(['status' => 'active', 'tenant_id' => $this->tenant->id]);
        User::factory()->create(['status' => 'inactive', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->getActive();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_inactive_users()
    {
        // Create inactive user
        User::factory()->create(['status' => 'inactive', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->getInactive();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_update_last_login()
    {
        $result = $this->userRepository->updateLastLogin($this->user->id, '192.168.1.1');
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'last_login_at' => now()->format('Y-m-d H:i:s')
        ]);
    }

    /** @test */
    public function it_can_change_user_password()
    {
        $result = $this->userRepository->changePassword($this->user->id, 'newpassword123');
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_verify_user_password()
    {
        $result = $this->userRepository->verifyPassword($this->user->id, 'password');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_user_statistics()
    {
        // Create additional users with different statuses
        User::factory()->create(['status' => 'active', 'tenant_id' => $this->tenant->id]);
        User::factory()->create(['status' => 'inactive', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->getStatistics($this->tenant->id);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_users', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertArrayHasKey('inactive_users', $result);
    }

    /** @test */
    public function it_can_get_users_by_multiple_ids()
    {
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user3 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->getByIds([$this->user->id, $user2->id, $user3->id]);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(3, $result->count());
    }

    /** @test */
    public function it_can_bulk_update_users()
    {
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->bulkUpdate(
            [$this->user->id, $user2->id],
            ['status' => 'inactive']
        );
        
        $this->assertEquals(2, $result);
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('users', ['id' => $user2->id, 'status' => 'inactive']);
    }

    /** @test */
    public function it_can_bulk_delete_users()
    {
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->userRepository->bulkDelete([$this->user->id, $user2->id]);
        
        $this->assertEquals(2, $result);
        $this->assertNull(User::find($this->user->id));
        $this->assertNull(User::find($user2->id));
    }

    /** @test */
    public function it_can_get_user_permissions()
    {
        // Create role with permissions
        $role = Role::factory()->create(['name' => 'admin']);
        $this->user->roles()->attach($role);
        
        $result = $this->userRepository->getPermissions($this->user->id);
        
        $this->assertIsArray($result);
    }

    /** @test */
    public function it_can_check_user_permission()
    {
        // Create role with permissions
        $role = Role::factory()->create(['name' => 'admin']);
        $this->user->roles()->attach($role);
        
        $result = $this->userRepository->hasPermission($this->user->id, 'admin');
        
        $this->assertIsBool($result);
    }

    /** @test */
    public function it_can_get_user_roles()
    {
        // Create role and assign to user
        $role = Role::factory()->create(['name' => 'admin']);
        $this->user->roles()->attach($role);
        
        $result = $this->userRepository->getRoles($this->user->id);
        
        $this->assertIsArray($result);
        $this->assertContains('admin', $result);
    }

    /** @test */
    public function it_can_check_user_role()
    {
        // Create role and assign to user
        $role = Role::factory()->create(['name' => 'admin']);
        $this->user->roles()->attach($role);
        
        $result = $this->userRepository->hasRole($this->user->id, 'admin');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_user()
    {
        $result = $this->userRepository->getById(99999);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_false_for_invalid_operations()
    {
        $result = $this->userRepository->delete(99999);
        
        $this->assertFalse($result);
    }
}
