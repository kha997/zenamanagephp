<?php declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\Helpers\AuthHelper;
use Tests\TestCase;

/**
 * Profile Management Test
 * 
 * Tests for user profile management functionality
 */
class ProfileManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected array $authHeaders = [];
    protected array $otherAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Get auth headers for API requests
        $this->authHeaders = AuthHelper::getAuthHeaders($this, $this->user->email, 'password');
    }

    /**
     * Test get user profile
     */
    public function test_user_can_get_their_profile()
    {
        $response = $this->withHeaders($this->authHeaders)
            ->getJson('/api/users/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'avatar',
                        'first_name',
                        'last_name',
                        'department',
                        'job_title',
                        'tenant_id',
                        'email_verified_at',
                        'last_login_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test get profile requires authentication
     */
    public function test_get_profile_requires_authentication()
    {
        $response = $this->getJson('/api/users/profile');

        $response->assertStatus(401);
    }

    /**
     * Test update user profile
     */
    public function test_user_can_update_their_profile()
    {
        $updateData = [
            'name' => 'Updated Name',
            'phone' => '+1234567890',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'department' => 'Engineering',
            'job_title' => 'Senior Developer',
        ];

        $response = $this->withHeaders($this->authHeaders)
            ->putJson('/api/users/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message',
                    'user',
                ],
            ])
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.phone', '+1234567890')
            ->assertJsonPath('data.user.first_name', 'John')
            ->assertJsonPath('data.user.last_name', 'Doe')
            ->assertJsonPath('data.user.department', 'Engineering')
            ->assertJsonPath('data.user.job_title', 'Senior Developer');

        // Verify database was updated
        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
        $this->assertEquals('+1234567890', $this->user->phone);
        $this->assertEquals('John', $this->user->first_name);
        $this->assertEquals('Doe', $this->user->last_name);
        $this->assertEquals('Engineering', $this->user->department);
        $this->assertEquals('Senior Developer', $this->user->job_title);
    }

    /**
     * Test update profile with partial data
     */
    public function test_user_can_update_profile_with_partial_data()
    {
        $updateData = [
            'name' => 'Partial Update',
        ];

        $response = $this->withHeaders($this->authHeaders)
            ->putJson('/api/users/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Partial Update');

        // Verify only name was updated, other fields remain unchanged
        $this->user->refresh();
        $this->assertEquals('Partial Update', $this->user->name);
    }

    /**
     * Test update profile requires authentication
     */
    public function test_update_profile_requires_authentication()
    {
        $response = $this->putJson('/api/users/profile', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test update profile validation
     */
    public function test_update_profile_validation()
    {
        // Name too long
        $response = $this->withHeaders($this->authHeaders)
            ->putJson('/api/users/profile', [
                'name' => str_repeat('a', 256),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Phone too long
        $response = $this->withHeaders($this->authHeaders)
            ->putJson('/api/users/profile', [
                'phone' => str_repeat('1', 21),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Test update profile with PATCH method
     */
    public function test_user_can_update_profile_with_patch_method()
    {
        $updateData = [
            'name' => 'PATCH Update',
        ];

        $response = $this->withHeaders($this->authHeaders)
            ->patchJson('/api/users/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'PATCH Update');
    }

    /**
     * Test multi-tenant isolation for profile
     */
    public function test_profile_respects_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // User from tenant A can only see/update their own profile
        $response = $this->withHeaders($this->authHeaders)
            ->getJson('/api/users/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', (string) $this->user->id)
            ->assertJsonPath('data.user.tenant_id', (string) $this->tenant->id);

        // Get auth headers for other user
        $otherAuthHeaders = AuthHelper::getAuthHeaders($this, $otherUser->email, 'password');
        
        // User from tenant B can only see/update their own profile
        $response = $this->withHeaders($otherAuthHeaders)
            ->getJson('/api/users/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', (string) $otherUser->id)
            ->assertJsonPath('data.user.tenant_id', (string) $otherTenant->id);
    }

    /**
     * Test update profile with empty strings (should be ignored)
     */
    public function test_update_profile_ignores_empty_strings()
    {
        $originalName = $this->user->name;
        
        $response = $this->withHeaders($this->authHeaders)
            ->putJson('/api/users/profile', [
                'name' => '',
                'phone' => '',
            ]);

        $response->assertStatus(200);

        // Verify empty strings were ignored (not updated)
        $this->user->refresh();
        $this->assertEquals($originalName, $this->user->name);
    }
}

