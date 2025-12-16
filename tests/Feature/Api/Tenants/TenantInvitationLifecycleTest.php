<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\UserTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Tenant Invitation Lifecycle API
 * 
 * Tests the invitee-side operations:
 * - Preview invitation (public)
 * - Accept invitation (authenticated)
 * - Decline invitation (authenticated)
 * 
 * Round 20: Tenant Invitation Lifecycle (Accept/Decline)
 * 
 * @group tenant-invitations
 * @group tenant-lifecycle
 */
class TenantInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private TenantInvitation $invitation;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(33333);
        $this->setDomainName('tenant-invitation-lifecycle');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create inviter
        $inviter = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $inviter->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create invitation
        $this->token = \Illuminate\Support\Str::ulid()->toBase32();
        $this->invitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'token' => $this->token,
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Test that GET /api/v1/tenant/invitations/{token} returns invitation metadata for valid token
     */
    public function test_show_public_returns_invitation_metadata_for_valid_token(): void
    {
        $response = $this->getJson("/api/v1/tenant/invitations/{$this->token}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'success',
            'message',
            'data' => [
                'tenant_name',
                'email',
                'role',
                'status',
                'is_expired',
            ],
            'timestamp',
        ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->tenant->name, $data['tenant_name']);
        $this->assertEquals('invited@example.com', $data['email']);
        $this->assertEquals('member', $data['role']);
        $this->assertEquals('pending', $data['status']);
        $this->assertFalse($data['is_expired']);
    }

    /**
     * Test that GET /api/v1/tenant/invitations/{token} returns 404 for invalid token
     */
    public function test_show_public_returns_404_for_invalid_token(): void
    {
        $response = $this->getJson('/api/v1/tenant/invitations/invalid-token-12345');

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_INVALID_TOKEN',
        ]);
    }

    /**
     * Test that GET /api/v1/tenant/invitations/{token} marks expired invitation as expired
     */
    public function test_show_public_marks_expired_invitation_as_expired(): void
    {
        // Create expired invitation
        $expiredToken = \Illuminate\Support\Str::ulid()->toBase32();
        $expiredInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => $expiredToken,
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $this->invitation->invited_by,
            'expires_at' => now()->subDay(), // Expired
        ]);

        $response = $this->getJson("/api/v1/tenant/invitations/{$expiredToken}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertTrue($data['is_expired']);
        
        // Verify status was updated
        $expiredInvitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_EXPIRED, $expiredInvitation->status);
    }

    /**
     * Test that authenticated user can accept valid invitation with matching email
     */
    public function test_authenticated_user_can_accept_valid_invitation_with_matching_email(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'invitation_status' => 'accepted',
                'already_member' => false,
            ],
        ]);

        // Verify invitation was updated
        $this->invitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_ACCEPTED, $this->invitation->status);
        $this->assertNotNull($this->invitation->accepted_at);

        // Verify membership was created
        $membership = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertNotNull($membership);
        $this->assertEquals('member', $membership->role);
    }

    /**
     * Test that accept fails for email mismatch
     */
    public function test_accept_fails_for_email_mismatch(): void
    {
        $user = User::factory()->create([
            'email' => 'different@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_EMAIL_MISMATCH',
        ]);

        // Verify invitation was NOT updated
        $this->invitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_PENDING, $this->invitation->status);
        $this->assertNull($this->invitation->accepted_at);

        // Verify membership was NOT created
        $membership = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertNull($membership);
    }

    /**
     * Test that accept fails for expired invitation
     */
    public function test_accept_fails_for_expired_invitation(): void
    {
        // Create expired invitation
        $expiredToken = \Illuminate\Support\Str::ulid()->toBase32();
        $expiredInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => $expiredToken,
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $this->invitation->invited_by,
            'expires_at' => now()->subDay(), // Expired
        ]);

        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$expiredToken}/accept");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_EXPIRED',
        ]);

        // Verify invitation status was updated to expired
        $expiredInvitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_EXPIRED, $expiredInvitation->status);
    }

    /**
     * Test that accept fails for already accepted invitation
     */
    public function test_accept_fails_for_already_accepted_invitation(): void
    {
        // Mark invitation as accepted
        $this->invitation->status = TenantInvitation::STATUS_ACCEPTED;
        $this->invitation->accepted_at = now();
        $this->invitation->save();

        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_ACCEPTED',
        ]);
    }

    /**
     * Test that accept fails for invalid token
     */
    public function test_accept_fails_for_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/tenant/invitations/invalid-token-12345/accept');

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_INVALID_TOKEN',
        ]);
    }

    /**
     * Test that accept is idempotent for already member
     */
    public function test_accept_is_idempotent_for_already_member(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        // Make user already a member
        UserTenant::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'is_default' => false,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'invitation_status' => 'accepted',
                'already_member' => true,
            ],
        ]);

        // Verify invitation was updated
        $this->invitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_ACCEPTED, $this->invitation->status);

        // Verify only one membership exists (no duplicate)
        $memberships = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();
        
        $this->assertCount(1, $memberships);
    }

    /**
     * Test that accept sets is_default=true when user has no other tenants
     */
    public function test_accept_sets_is_default_when_user_has_no_other_tenants(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        // User has no tenants
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(200);

        // Verify membership has is_default=true
        $membership = UserTenant::where('tenant_id', $this->tenant->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->first();
        
        $this->assertNotNull($membership);
        $this->assertTrue($membership->is_default);
    }

    /**
     * Test that authenticated user can decline pending invitation
     */
    public function test_authenticated_user_can_decline_pending_invitation(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/decline");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'invitation_status' => 'declined',
            ],
        ]);

        // Verify invitation was updated
        $this->invitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_DECLINED, $this->invitation->status);
        $this->assertNotNull($this->invitation->revoked_at);
    }

    /**
     * Test that decline fails for already accepted invitation
     */
    public function test_decline_fails_for_already_accepted_invitation(): void
    {
        // Mark invitation as accepted
        $this->invitation->status = TenantInvitation::STATUS_ACCEPTED;
        $this->invitation->accepted_at = now();
        $this->invitation->save();

        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/decline");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_ACCEPTED',
        ]);
    }

    /**
     * Test that decline fails for expired invitation
     */
    public function test_decline_fails_for_expired_invitation(): void
    {
        // Create expired invitation
        $expiredToken = \Illuminate\Support\Str::ulid()->toBase32();
        $expiredInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => $expiredToken,
            'status' => TenantInvitation::STATUS_EXPIRED,
            'invited_by' => $this->invitation->invited_by,
            'expires_at' => now()->subDay(),
        ]);

        $user = User::factory()->create([
            'email' => 'expired@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$expiredToken}/decline");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_EXPIRED',
        ]);
    }

    /**
     * Test that decline fails for invalid token
     */
    public function test_decline_fails_for_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/tenant/invitations/invalid-token-12345/decline');

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_INVALID_TOKEN',
        ]);
    }

    /**
     * Test that accept invitation does not leak other tenants
     */
    public function test_accept_invitation_does_not_leak_other_tenants(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create invitation in tenant B
        $inviterB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);

        $inviterB->tenants()->attach($tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        $tokenB = \Illuminate\Support\Str::ulid()->toBase32();
        $invitationB = TenantInvitation::create([
            'tenant_id' => $tenantB->id,
            'email' => 'tenantb@example.com',
            'role' => 'member',
            'token' => $tokenB,
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviterB->id,
            'expires_at' => now()->addDays(7),
        ]);

        // User accepts invitation from tenant A (our test tenant)
        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(200);

        // Verify user is only member of tenant A
        $memberships = UserTenant::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        $this->assertCount(1, $memberships);
        $this->assertEquals($this->tenant->id, $memberships->first()->tenant_id);
        $this->assertNotEquals($tenantB->id, $memberships->first()->tenant_id);
    }

    /**
     * Test that accept fails for revoked invitation
     */
    public function test_accept_fails_for_revoked_invitation(): void
    {
        // Mark invitation as revoked
        $this->invitation->status = TenantInvitation::STATUS_REVOKED;
        $this->invitation->revoked_at = now();
        $this->invitation->save();

        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/accept");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_REVOKED',
        ]);
    }

    /**
     * Test that decline fails for revoked invitation
     */
    public function test_decline_fails_for_revoked_invitation(): void
    {
        // Mark invitation as revoked
        $this->invitation->status = TenantInvitation::STATUS_REVOKED;
        $this->invitation->revoked_at = now();
        $this->invitation->save();

        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/decline");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_REVOKED',
        ]);
    }

    /**
     * Test that decline fails for already declined invitation
     */
    public function test_decline_fails_for_already_declined_invitation(): void
    {
        // Mark invitation as declined
        $this->invitation->status = TenantInvitation::STATUS_DECLINED;
        $this->invitation->revoked_at = now();
        $this->invitation->save();

        $user = User::factory()->create([
            'email' => 'invited@example.com',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/tenant/invitations/{$this->token}/decline");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_DECLINED',
        ]);
    }
}

