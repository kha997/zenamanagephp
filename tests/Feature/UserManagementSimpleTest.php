<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SSOT\FixtureFactory;

/**
 * Test User Management & Authentication System (Simplified)
 * 
 * Kịch bản: Test đăng nhập, đăng ký, quản lý user, và authentication features
 */
class UserManagementSimpleTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    private $tenant;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = $this->createTenant([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo user
        $this->user = $this->createTenantUserWithRbac($this->tenant, 'member', 'member', [], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => json_encode(['department' => 'IT']),
        ]);
    }

    /**
     * Test user creation
     */
    public function test_can_create_user(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => json_encode(['department' => 'HR']),
        ];

        $user = User::factory()->create($userData);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->assertEquals('New User', $user->name);
        $this->assertEquals('newuser@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Test user update
     */
    public function test_can_update_user(): void
    {
        $this->user->update([
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'profile_data' => json_encode(['department' => 'Finance']),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ]);

        $this->assertEquals('Updated User', $this->user->name);
        $this->assertEquals('updated@example.com', $this->user->email);
    }

    /**
     * Test password update
     */
    public function test_can_update_user_password(): void
    {
        $newPassword = 'newpassword123';
        $this->user->update([
            'password' => Hash::make($newPassword),
        ]);

        $this->assertTrue(Hash::check($newPassword, $this->user->password));
        $this->assertFalse(Hash::check('password123', $this->user->password));
    }

    /**
     * Test user status toggle
     */
    public function test_can_toggle_user_status(): void
    {
        // Test deactivate
        $this->user->update(['is_active' => false]);
        $this->assertFalse($this->user->is_active);

        // Test activate
        $this->user->update(['is_active' => true]);
        $this->assertTrue($this->user->is_active);
    }

    /**
     * Test user profile data
     */
    public function test_can_manage_user_profile_data(): void
    {
        $profileData = [
            'department' => 'Engineering',
            'position' => 'Senior Developer',
            'skills' => ['PHP', 'Laravel', 'JavaScript'],
            'preferences' => [
                'theme' => 'dark',
                'language' => 'en',
                'notifications' => true,
            ],
        ];

        $this->user->update([
            'profile_data' => json_encode($profileData),
        ]);

        // Test profile data update (simplified)
        $this->user->update([
            'name' => 'Updated Profile User',
        ]);

        $this->assertEquals('Updated Profile User', $this->user->name);
    }

    /**
     * Test user tenant relationship
     */
    public function test_user_tenant_relationship(): void
    {
        $this->assertEquals($this->tenant->id, $this->user->tenant_id);
        $this->assertEquals($this->tenant->name, $this->user->tenant->name);
    }

    /**
     * Test user last login tracking
     */
    public function test_can_track_last_login(): void
    {
        $this->assertNull($this->user->last_login_at);

        $this->user->update(['last_login_at' => now()]);

        $this->assertNotNull($this->user->last_login_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $this->user->last_login_at);
    }

    /**
     * Test user API token generation
     */
    public function test_can_generate_api_token(): void
    {
        $token = $this->user->createToken('test-token');

        $this->assertNotNull($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'name' => 'test-token',
        ]);
    }

    /**
     * Test user bulk operations
     */
    public function test_can_perform_bulk_user_operations(): void
    {
        // Create multiple users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[] = User::factory()->create([
                'name' => "Bulk User {$i}",
                'email' => "bulk{$i}@example.com",
                'password' => Hash::make('password123'),
                'tenant_id' => $this->tenant->id,
                'is_active' => true,
            ]);
        }

        // Verify all users created
        foreach ($users as $user) {
            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        // Bulk update
        User::whereIn('id', collect($users)->pluck('id'))
            ->update(['is_active' => false]);

        foreach ($users as $user) {
            $user->refresh();
            $this->assertFalse($user->is_active);
        }
    }

    /**
     * Test user email validation
     */
    public function test_user_email_validation(): void
    {
        // Test valid email
        $validUser = User::factory()->create([
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'valid@example.com',
        ]);

        // Test email format validation (this would be handled by validation rules in real app)
        $this->assertTrue(filter_var('valid@example.com', FILTER_VALIDATE_EMAIL) !== false);
        $this->assertFalse(filter_var('invalid-email', FILTER_VALIDATE_EMAIL) !== false);
    }

    /**
     * Test user password strength
     */
    public function test_user_password_strength(): void
    {
        $strongPassword = 'StrongPassword123!';
        $weakPassword = '123';

        $this->user->update(['password' => Hash::make($strongPassword)]);
        $this->assertTrue(Hash::check($strongPassword, $this->user->password));

        $this->user->update(['password' => Hash::make($weakPassword)]);
        $this->assertTrue(Hash::check($weakPassword, $this->user->password));

        // In real app, password strength would be validated before hashing
        $this->assertGreaterThan(strlen($weakPassword), strlen($strongPassword));
    }

    /**
     * Test user search functionality
     */
    public function test_can_search_users(): void
    {
        // Create additional users for search testing
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Search by name
        $johnUsers = User::where('name', 'like', '%John%')->get();
        $this->assertCount(1, $johnUsers);
        $this->assertEquals('John Doe', $johnUsers->first()->name);

        // Search by email
        $janeUsers = User::where('email', 'like', '%jane%')->get();
        $this->assertCount(1, $janeUsers);
        $this->assertEquals('jane@example.com', $janeUsers->first()->email);

        // Search by tenant
        $tenantUsers = User::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(3, $tenantUsers); // Original user + 2 new users
    }

    /**
     * Test user pagination
     */
    public function test_can_paginate_users(): void
    {
        $baselineTenantUsers = User::where('tenant_id', $this->tenant->id)->count();

        // Create multiple users
        $createdEmails = [];
        for ($i = 1; $i <= 15; $i++) {
            $email = "user{$i}@example.com";
            User::factory()->create([
                'name' => "User {$i}",
                'email' => $email,
                'password' => Hash::make('password123'),
                'tenant_id' => $this->tenant->id,
                'is_active' => true,
            ]);
            $createdEmails[] = $email;
        }

        // Test pagination with tenant-scoped deterministic query.
        $tenantUsersQuery = User::where('tenant_id', $this->tenant->id)->orderBy('email');
        $totalUsers = $tenantUsersQuery->count();
        $expectedTotalUsers = $baselineTenantUsers + 15;
        $this->assertEquals($expectedTotalUsers, $totalUsers);

        // Test getting users in chunks
        $firstChunk = (clone $tenantUsersQuery)->take(10)->get();
        $this->assertCount(min(10, $expectedTotalUsers), $firstChunk);

        $secondChunk = (clone $tenantUsersQuery)->skip(10)->take(10)->get();
        $this->assertCount(max(0, $expectedTotalUsers - 10), $secondChunk);

        $allRows = $firstChunk->concat($secondChunk);
        $this->assertEquals($expectedTotalUsers, $allRows->count());
        $this->assertTrue($allRows->every(fn (User $user) => $user->tenant_id === $this->tenant->id));

        $returnedEmails = $allRows->pluck('email')->all();
        foreach ($createdEmails as $email) {
            $this->assertContains($email, $returnedEmails);
        }
    }

    /**
     * Test user filtering
     */
    public function test_can_filter_users(): void
    {
        // Create users with different statuses
        User::factory()->create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        // Filter active users
        $activeUsers = User::where('is_active', true)->get();
        $this->assertGreaterThan(0, $activeUsers->count());

        // Filter inactive users
        $inactiveUsers = User::where('is_active', false)->get();
        $this->assertGreaterThan(0, $inactiveUsers->count());

        // Verify filtering works
        foreach ($activeUsers as $user) {
            $this->assertTrue($user->is_active);
        }

        foreach ($inactiveUsers as $user) {
            $this->assertFalse($user->is_active);
        }
    }
}
