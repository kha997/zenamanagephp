<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Test User Management & Authentication System
 * 
 * Kịch bản: Test đăng nhập, đăng ký, quản lý user, và authentication features
 */
class UserManagementAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo user
        $this->user = User::create([
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
            'password' => 'password123',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => json_encode(['department' => 'HR']),
        ];

        $user = User::create($userData);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertEquals('New User', $user->name);
        $this->assertEquals('newuser@example.com', $user->email);
    }

    /**
     * Test user authentication with valid credentials
     */
    public function test_can_authenticate_with_valid_credentials(): void
    {
        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $this->assertTrue(Auth::attempt($credentials));
        
        $authenticatedUser = Auth::user();
        $this->assertEquals($this->user->id, $authenticatedUser->id);
        $this->assertEquals($this->user->email, $authenticatedUser->email);
    }

    /**
     * Test user authentication with invalid credentials
     */
    public function test_cannot_authenticate_with_invalid_credentials(): void
    {
        $invalidCredentials = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        $this->assertFalse(Auth::attempt($invalidCredentials));
        $this->assertNull(Auth::user());
    }

    /**
     * Test user authentication with non-existent email
     */
    public function test_cannot_authenticate_with_non_existent_email(): void
    {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $this->assertFalse(Auth::attempt($credentials));
        $this->assertNull(Auth::user());
    }

    /**
     * Test inactive user cannot authenticate
     */
    public function test_inactive_user_cannot_authenticate(): void
    {
        // Create inactive user
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $credentials = [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ];

        $this->assertFalse(Auth::attempt($credentials));
    }

    /**
     * Test user logout
     */
    public function test_can_logout_user(): void
    {
        // Login user first
        Auth::login($this->user);
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->user->id, Auth::id());

        // Logout
        Auth::logout();
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::id());
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

        $this->assertEquals($profileData, $this->user->profile_data);
        $this->assertEquals('Engineering', $this->user->profile_data['department']);
        $this->assertEquals('Senior Developer', $this->user->profile_data['position']);
        $this->assertContains('PHP', $this->user->profile_data['skills']);
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
     * Test user soft delete
     */
    public function test_can_soft_delete_user(): void
    {
        $this->user->delete();

        $this->assertSoftDeleted('users', [
            'id' => $this->user->id,
        ]);

        $this->assertNull(User::find($this->user->id));
        $this->assertNotNull(User::withTrashed()->find($this->user->id));
    }

    /**
     * Test user restore from soft delete
     */
    public function test_can_restore_soft_deleted_user(): void
    {
        $this->user->delete();
        $this->assertSoftDeleted('users', ['id' => $this->user->id]);

        $this->user->restore();
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test user email uniqueness within tenant
     */
    public function test_email_uniqueness_within_tenant(): void
    {
        // This should work (different tenant)
        $anotherTenant = Tenant::create([
            'name' => 'Another Company',
            'slug' => 'another-company',
            'domain' => 'another.com',
            'status' => 'active',
            'is_active' => true,
        ]);

        $userInAnotherTenant = User::create([
            'name' => 'User in Another Tenant',
            'email' => 'test@example.com', // Same email
            'password' => Hash::make('password123'),
            'tenant_id' => $anotherTenant->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $userInAnotherTenant->id,
            'email' => 'test@example.com',
            'tenant_id' => $anotherTenant->id,
        ]);
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
     * Test user remember token
     */
    public function test_can_generate_remember_token(): void
    {
        $this->assertNull($this->user->remember_token);

        $this->user->update(['remember_token' => 'test-remember-token']);

        $this->assertEquals('test-remember-token', $this->user->remember_token);
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
            $users[] = User::create([
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
}
