<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\UserTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Tenant Ownership Management (Round 24)
 * 
 * Tests that owners can promote members to owner and transfer ownership,
 * while ensuring proper RBAC and validation.
 * 
 * @group tenant-members
 * @group tenant-ownership
 * @group round-24
 */
class TenantOwnershipManagementTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $owner;
    private User $admin;
    private User $member;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(22222);
        $this->setDomainName('tenant-ownership-management');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create owner
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create admin
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => false,
        ]);
        
        // Create member
        $this->member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->member->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => false,
        ]);
        
        // Create viewer
        $this->viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => false,
        ]);
    }

    /**
     * Test owner can promote member to owner without demoting self
     */
    public function test_owner_can_promote_member_to_owner_without_demoting_self(): void
    {
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->admin->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => (string) $this->admin->id,
                    'role' => 'owner',
                ],
                'acting_member' => [
                    'id' => (string) $this->owner->id,
                    'role' => 'owner',
                ],
            ],
        ]);
        
        // Verify both are owners in DB
        $adminPivot = UserTenant::where('user_id', $this->admin->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $ownerPivot = UserTenant::where('user_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $adminPivot->role);
        $this->assertEquals('owner', $ownerPivot->role);
        
        // Verify there are at least 2 owners
        $ownerCount = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
        
        $this->assertGreaterThanOrEqual(2, $ownerCount);
    }

    /**
     * Test owner can transfer ownership and become admin
     */
    public function test_owner_can_transfer_ownership_and_become_admin(): void
    {
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->member->id}/make-owner", [
            'demote_self' => true,
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => (string) $this->member->id,
                    'role' => 'owner',
                ],
                'acting_member' => [
                    'id' => (string) $this->owner->id,
                    'role' => 'admin',
                ],
            ],
        ]);
        
        // Verify roles in DB
        $memberPivot = UserTenant::where('user_id', $this->member->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $ownerPivot = UserTenant::where('user_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $memberPivot->role);
        $this->assertEquals('admin', $ownerPivot->role);
        
        // Verify there is at least 1 owner
        $ownerCount = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
        
        $this->assertGreaterThanOrEqual(1, $ownerCount);
    }

    /**
     * Test admin cannot make owner (even with tenant.manage_members permission)
     */
    public function test_admin_cannot_make_owner(): void
    {
        Sanctum::actingAs($this->admin);
        $token = $this->admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->member->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'FORBIDDEN',
        ]);
        
        // Verify no roles changed
        $memberPivot = UserTenant::where('user_id', $this->member->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('member', $memberPivot->role);
    }

    /**
     * Test member cannot make owner
     */
    public function test_member_cannot_make_owner(): void
    {
        Sanctum::actingAs($this->member);
        $token = $this->member->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->viewer->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test viewer cannot make owner
     */
    public function test_viewer_cannot_make_owner(): void
    {
        Sanctum::actingAs($this->viewer);
        $token = $this->viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->member->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test cannot make non-member owner
     */
    public function test_cannot_make_non_member_owner(): void
    {
        // Create user in different tenant
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
        ]);
        
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email_verified_at' => now(),
        ]);
        
        $otherUser->tenants()->attach($otherTenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$otherUser->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);
        
        // Verify no roles changed in either tenant
        $otherPivot = UserTenant::where('user_id', $otherUser->id)
            ->where('tenant_id', $otherTenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('member', $otherPivot->role);
    }

    /**
     * Test cannot make member already owner
     */
    public function test_cannot_make_member_already_owner(): void
    {
        // Create second owner
        $owner2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $owner2->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => false,
        ]);
        
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$owner2->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_MEMBER_ALREADY_OWNER',
        ]);
        
        // Verify role unchanged
        $owner2Pivot = UserTenant::where('user_id', $owner2->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $owner2Pivot->role);
    }

    /**
     * Test owner cannot call make owner on self
     */
    public function test_owner_cannot_call_make_owner_on_self(): void
    {
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->owner->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_MEMBER_ALREADY_OWNER',
        ]);
        
        // Verify role unchanged
        $ownerPivot = UserTenant::where('user_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $ownerPivot->role);
    }

    /**
     * Test tenant isolation for make owner
     */
    public function test_tenant_isolation_for_make_owner(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);
        
        // Create owner of tenant B
        $ownerB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $ownerB->tenants()->attach($tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create member in tenant B
        $memberB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $memberB->tenants()->attach($tenantB->id, [
            'role' => 'member',
            'is_default' => false,
        ]);
        
        // Owner of tenant A tries to make owner of tenant B member
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$memberB->id}/make-owner", [
            'demote_self' => false,
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);
        
        // Verify tenant B member role unchanged
        $memberBPivot = UserTenant::where('user_id', $memberB->id)
            ->where('tenant_id', $tenantB->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('member', $memberBPivot->role);
    }

    /**
     * Test transfer ownership when acting user is last owner
     * 
     * Round 24+: Verifies that when the last owner transfers ownership,
     * the tenant still has at least one owner after transfer (target promoted,
     * acting user demoted to admin).
     */
    public function test_transfer_ownership_when_acting_user_is_last_owner(): void
    {
        // Ensure this is the only owner (remove admin/member/viewer if needed)
        // Actually, we already have only one owner in setUp, so we can use it
        
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        // Transfer ownership to member (last owner transfers)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->member->id}/make-owner", [
            'demote_self' => true,
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => (string) $this->member->id,
                    'role' => 'owner',
                ],
                'acting_member' => [
                    'id' => (string) $this->owner->id,
                    'role' => 'admin',
                ],
            ],
        ]);
        
        // Verify roles in DB
        $memberPivot = UserTenant::where('user_id', $this->member->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $ownerPivot = UserTenant::where('user_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $memberPivot->role, 'Target member should be promoted to owner');
        $this->assertEquals('admin', $ownerPivot->role, 'Acting owner should be demoted to admin');
        
        // Critical: Verify there is exactly 1 owner after transfer
        $ownerCount = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
        
        $this->assertEquals(1, $ownerCount, 'Tenant must have exactly one owner after last owner transfers ownership');
        
        // Verify the new owner is the target member
        $newOwnerPivot = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals($this->member->id, $newOwnerPivot->user_id, 'New owner should be the target member');
    }

    /**
     * Test transfer ownership with multiple owners preserves other owners
     * 
     * Round 24+: Verifies that when there are multiple owners and one owner
     * transfers ownership, other owners remain unchanged and tenant still
     * has multiple owners after transfer.
     */
    public function test_transfer_ownership_with_multiple_owners_preserves_other_owners(): void
    {
        // Create second owner
        $owner2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $owner2->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => false,
        ]);
        
        // Verify we have 2 owners before transfer
        $ownerCountBefore = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
        
        $this->assertEquals(2, $ownerCountBefore, 'Should have 2 owners before transfer');
        
        Sanctum::actingAs($this->owner);
        $token = $this->owner->createToken('test-token')->plainTextToken;
        
        // Transfer ownership from owner1 to member
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/app/tenant/members/{$this->member->id}/make-owner", [
            'demote_self' => true,
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => (string) $this->member->id,
                    'role' => 'owner',
                ],
                'acting_member' => [
                    'id' => (string) $this->owner->id,
                    'role' => 'admin',
                ],
            ],
        ]);
        
        // Verify roles in DB
        $memberPivot = UserTenant::where('user_id', $this->member->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $owner1Pivot = UserTenant::where('user_id', $this->owner->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $owner2Pivot = UserTenant::where('user_id', $owner2->id)
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertEquals('owner', $memberPivot->role, 'Target member should be promoted to owner');
        $this->assertEquals('admin', $owner1Pivot->role, 'Acting owner should be demoted to admin');
        $this->assertEquals('owner', $owner2Pivot->role, 'Other owner should remain unchanged');
        
        // Verify there are exactly 2 owners after transfer (member + owner2)
        $ownerCountAfter = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('role', 'owner')
            ->whereNull('deleted_at')
            ->count();
        
        $this->assertEquals(2, $ownerCountAfter, 'Tenant should have exactly 2 owners after transfer (new owner + existing owner2)');
        
        // Verify owner2 is still an owner
        $this->assertNotNull($owner2Pivot, 'Owner2 should still exist');
        $this->assertEquals('owner', $owner2Pivot->role, 'Owner2 should still be owner');
    }
}

