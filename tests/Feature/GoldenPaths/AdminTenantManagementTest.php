<?php declare(strict_types=1);

namespace Tests\Feature\GoldenPaths;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Golden Path 4: Admin / Tenant Management
 * 
 * Tests the critical flow: Tenant admin views users → Assigns role → Deactivates user
 */
class AdminTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Create tenant admin
        $this->adminUser = User::factory()->create([
            'email' => 'admin@zena.local',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenantId,
            'role' => 'pm',
            'is_admin' => false, // Tenant admin, not super admin
        ]);
        
        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
    }

    /** @test */
    public function tenant_admin_can_list_users_in_tenant(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/app/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'users',
            ],
        ]);
        
        $users = $response->json('data.users');
        $this->assertIsArray($users);
        
        // All users should belong to admin's tenant
        foreach ($users as $user) {
            $this->assertEquals($this->tenantId, $user['tenant_id']);
        }
    }

    /** @test */
    public function tenant_admin_can_view_user_details(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        $response = $this->getJson("/api/v1/app/users/{$this->regularUser->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'user' => [
                    'id',
                    'email',
                    'tenant_id',
                    'role',
                ],
            ],
        ]);
        
        $user = $response->json('data.user');
        $this->assertEquals($this->regularUser->id, $user['id']);
        $this->assertEquals($this->tenantId, $user['tenant_id']);
    }

    /** @test */
    public function tenant_admin_can_assign_role_to_user(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        // Assign new role
        $response = $this->patchJson("/api/v1/app/users/{$this->regularUser->id}/role", [
            'role' => 'pm',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'user' => [
                    'id',
                    'role',
                ],
            ],
        ]);
        
        $user = $response->json('data.user');
        $this->assertEquals('pm', $user['role']);
        
        // Verify role was updated
        $this->regularUser->refresh();
        $this->assertEquals('pm', $this->regularUser->role);
    }

    /** @test */
    public function tenant_admin_can_deactivate_user(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        $response = $this->patchJson("/api/v1/app/users/{$this->regularUser->id}/deactivate", [
            'reason' => 'Test deactivation',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'user' => [
                    'id',
                    'active',
                ],
            ],
        ]);
        
        $user = $response->json('data.user');
        $this->assertFalse($user['active']);
        
        // Verify user is deactivated
        $this->regularUser->refresh();
        $this->assertFalse($this->regularUser->active);
    }

    /** @test */
    public function regular_user_cannot_manage_users(): void
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;
        
        // Try to list users
        $listResponse = $this->getJson('/api/v1/app/users', [
            'Authorization' => 'Bearer ' . $token,
        ]);
        
        // Should get 403 or empty list
        if ($listResponse->status() === 403) {
            $listResponse->assertStatus(403);
            $listResponse->assertJsonStructure([
                'ok',
                'code',
                'message',
            ]);
            $this->assertEquals('FORBIDDEN', $listResponse->json('code'));
        }
        
        // Try to assign role (should fail)
        $assignResponse = $this->patchJson("/api/v1/app/users/{$this->adminUser->id}/role", [
            'role' => 'member',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
        
        $assignResponse->assertStatus(403);
        $assignResponse->assertJsonStructure([
            'ok',
            'code',
            'message',
        ]);
        $this->assertEquals('FORBIDDEN', $assignResponse->json('code'));
    }

    /** @test */
    public function user_cannot_deactivate_own_account(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        $response = $this->patchJson("/api/v1/app/users/{$this->adminUser->id}/deactivate", [
            'reason' => 'Test',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 409 Conflict
        $response->assertStatus(409);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
        ]);
        $this->assertEquals('CANNOT_DEACTIVATE_SELF', $response->json('code'));
    }

    /** @test */
    public function invalid_role_assignment_returns_error(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        $response = $this->patchJson("/api/v1/app/users/{$this->regularUser->id}/role", [
            'role' => 'invalid_role',
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 422 Unprocessable Entity
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
            'details',
        ]);
        
        $this->assertFalse($response->json('ok'));
        $this->assertEquals('VALIDATION_FAILED', $response->json('code'));
        $this->assertArrayHasKey('validation', $response->json('details'));
    }

    /** @test */
    public function tenant_admin_cannot_manage_users_from_other_tenant(): void
    {
        $otherTenantId = '01K83FPK5XGPXF3V7ANJQRGX6Y';
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenantId,
            'role' => 'member',
        ]);
        
        $token = $this->adminUser->createToken('test')->plainTextToken;
        
        // Try to get other tenant user
        $response = $this->getJson("/api/v1/app/users/{$otherUser->id}", [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 404 Not Found (tenant isolation)
        $response->assertStatus(404);
    }
}

