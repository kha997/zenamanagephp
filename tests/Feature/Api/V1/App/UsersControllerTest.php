<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

/**
 * Integration tests for Users API Controller (V1)
 * 
 * Tests the new Api/V1/App/UsersController that replaced Unified/UserManagementController
 * 
 * @group users
 */
class UsersControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected string $authToken;
    protected array $seedData;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup domain isolation
        $this->setDomainSeed(78901);
        $this->setDomainName('users');
        $this->setupDomainIsolation();

        // Create tenant and users for testing
        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        // Create admin user
        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Admin User',
            'email' => 'admin@users-test.test',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        // Create additional test users
        User::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);

        // Authenticate using Sanctum
        Sanctum::actingAs($this->user);
        $this->authToken = $this->user->createToken('test-token')->plainTextToken;

        Cache::flush();
    }

    /**
     * Test users index requires authentication
     */
    public function test_users_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/users');

        $response->assertStatus(401);
    }

    /**
     * Test users index with tenant isolation
     */
    public function test_users_index_respects_tenant_isolation(): void
    {
        // Create another tenant with users
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, ['role' => 'member']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/app/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'message',
        ]);

        $users = $response->json('data');
        $userIds = array_column($users, 'id');

        // Should only see users from our tenant
        $this->assertContains($this->user->id, $userIds);
        $this->assertNotContains($otherUser->id, $userIds);
    }

    /**
     * Test users index with pagination
     */
    public function test_users_index_with_pagination(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/app/users?per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
    }

    /**
     * Test users index with cursor pagination
     */
    public function test_users_index_with_cursor_pagination(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/app/users?cursor=&limit=3');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
            'data' => [
                'pagination' => [
                    'next_cursor',
                    'has_more',
                ],
            ],
        ]);
    }

    /**
     * Test users index with filters
     */
    public function test_users_index_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        // Filter by role
        $response = $this->getJson('/api/v1/app/users?role=member');

        $response->assertStatus(200);
        $users = $response->json('data');
        
        foreach ($users as $user) {
            $this->assertEquals('member', $user['role']);
        }
    }

    /**
     * Test show user
     */
    public function test_show_user(): void
    {
        $targetUser = User::where('tenant_id', $this->tenant->id)
            ->where('id', '!=', $this->user->id)
            ->first();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/users/{$targetUser->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ]);
        $this->assertEquals($targetUser->id, $response->json('data.id'));
    }

    /**
     * Test show user with tenant isolation
     */
    public function test_show_user_respects_tenant_isolation(): void
    {
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, ['role' => 'member']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/app/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    /**
     * Test toggle user status
     */
    public function test_toggle_user_status(): void
    {
        $targetUser = User::where('tenant_id', $this->tenant->id)
            ->where('id', '!=', $this->user->id)
            ->first();

        $wasActive = $targetUser->is_active;

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/app/users/{$targetUser->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertNotEquals($wasActive, $targetUser->fresh()->is_active);
    }

    /**
     * Test search users
     */
    public function test_search_users(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/app/users/search?q=admin');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [],
        ]);
    }
}

