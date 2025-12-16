<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Jobs\SendTenantInvitationMail;
use App\Mail\TenantInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Tenant Invitations API permission enforcement
 * 
 * Tests that tenant invitations endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, DELETE).
 * 
 * Round 17: Tenant Members & Invitations (Backend API + RBAC)
 * 
 * @group tenant-invitations
 * @group tenant-permissions
 */
class TenantInvitationsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private TenantInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(22222);
        $this->setDomainName('tenant-invitations-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create an invitation for testing
        $inviter = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $inviter->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        $this->invitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'invited@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Test that GET /api/v1/app/tenant/invitations requires tenant.view_members permission
     */
    public function test_get_invitations_requires_view_permission(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/tenant/invitations');
            
            $response->assertStatus(200, "Role {$role} should be able to GET invitations (has tenant.view_members)");
            $response->assertJsonStructure([
                'data' => [
                    'invitations' => [],
                ],
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/tenant/invitations returns 403 for guest role
     */
    public function test_get_invitations_returns_403_for_guest(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_members
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tenant/invitations');
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that POST /api/v1/app/tenant/invitations requires tenant.manage_members permission
     */
    public function test_create_invitation_requires_manage_permission(): void
    {
        $roles = ['owner', 'admin'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-invite-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/tenant/invitations', [
                'email' => 'newuser' . uniqid() . '@example.com',
                'role' => 'member',
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create invitation");
            $response->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'role',
                    'status',
                ],
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/tenant/invitations returns 403 without permission
     */
    public function test_create_invitation_returns_403_without_permission(): void
    {
        $roles = ['member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-invite-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/tenant/invitations', [
                'email' => 'newuser@example.com',
                'role' => 'member',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create invitation");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that DELETE /api/v1/app/tenant/invitations/{id} requires tenant.manage_members permission
     */
    public function test_revoke_invitation_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/invitations/{$this->invitation->id}");
        
        $response->assertStatus(204);
        
        // Verify invitation was revoked
        $this->invitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_REVOKED, $this->invitation->status);
        $this->assertNotNull($this->invitation->revoked_at);
    }

    /**
     * Test that DELETE /api/v1/app/tenant/invitations/{id} returns 403 without permission
     */
    public function test_revoke_invitation_returns_403_without_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/invitations/{$this->invitation->id}");
        
        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that creating invitation for existing member is rejected
     */
    public function test_cannot_invite_existing_member(): void
    {
        // Create a user who is already a member
        $existingMember = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'existing@example.com',
            'email_verified_at' => now(),
        ]);
        
        $existingMember->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-invite-existing-' . uniqid(),
        ])->postJson('/api/v1/app/tenant/invitations', [
            'email' => 'existing@example.com',
            'role' => 'member',
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_MEMBER',
        ]);
    }

    /**
     * Test that duplicate pending invitation is rejected
     */
    public function test_cannot_create_duplicate_pending_invitation(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $email = 'duplicate@example.com';
        
        // Create first invitation
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-invite-1-' . uniqid(),
        ])->postJson('/api/v1/app/tenant/invitations', [
            'email' => $email,
            'role' => 'member',
        ]);
        
        $response1->assertStatus(201);
        
        // Attempt to create duplicate
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-invite-2-' . uniqid(),
        ])->postJson('/api/v1/app/tenant/invitations', [
            'email' => $email,
            'role' => 'member',
        ]);
        
        $response2->assertStatus(422);
        $response2->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_PENDING',
        ]);
    }

    /**
     * Test that invalid role is rejected
     */
    public function test_cannot_create_invitation_with_invalid_role(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-invite-invalid-role-' . uniqid(),
        ])->postJson('/api/v1/app/tenant/invitations', [
            'email' => 'newuser@example.com',
            'role' => 'invalid_role',
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVALID_ROLE',
        ]);
    }

    /**
     * Test tenant isolation - invitations from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
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
        
        $invitationB = TenantInvitation::create([
            'tenant_id' => $tenantB->id,
            'email' => 'tenantb@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviterB->id,
            'expires_at' => now()->addDays(7),
        ]);
        
        // Create user in tenant A (our test tenant)
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // User A should only see invitations from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/tenant/invitations');
        
        $response->assertStatus(200);
        $invitations = $response->json('data.invitations', []);
        
        // Verify invitation B is not in the list
        $invitationIds = array_column($invitations, 'id');
        $this->assertNotContains($invitationB->id, $invitationIds, 'Tenant B invitation should not be visible in tenant A');
    }

    /**
     * Test that tenant A cannot revoke invitation of tenant B
     */
    public function test_cannot_revoke_invitation_of_another_tenant(): void
    {
        // Create tenant B
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create inviter in tenant B
        $inviterB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email_verified_at' => now(),
        ]);

        $inviterB->tenants()->attach($tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        // Create invitation in tenant B
        $invitationB = TenantInvitation::create([
            'tenant_id' => $tenantB->id,
            'email' => 'tenantb@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviterB->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Create owner of tenant A (our test tenant)
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;

        // Attempt to revoke invitation of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/invitations/{$invitationB->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);

        // Verify invitation B is still pending and not revoked
        $invitationB->refresh();
        $this->assertEquals(TenantInvitation::STATUS_PENDING, $invitationB->status, 'Invitation should still be pending');
        $this->assertNull($invitationB->revoked_at, 'Invitation should not be revoked');
    }

    /**
     * Test that non-pending invitations cannot be revoked
     */
    public function test_cannot_revoke_non_pending_invitation(): void
    {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        // Test case 1: accepted invitation
        $acceptedInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'accepted@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_ACCEPTED,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($owner);
        $token = $owner->createToken('test-token')->plainTextToken;

        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/invitations/{$acceptedInvitation->id}");

        $response1->assertStatus(422);
        $response1->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);
        $response1->assertJsonPath('details.validation.invitation', fn($value) => !empty($value));

        // Verify invitation status unchanged
        $acceptedInvitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_ACCEPTED, $acceptedInvitation->status);
        $this->assertNull($acceptedInvitation->revoked_at);

        // Test case 2: revoked invitation
        $revokedInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'revoked@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_REVOKED,
            'invited_by' => $owner->id,
            'revoked_at' => now()->subDay(),
            'expires_at' => now()->addDays(7),
        ]);

        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/tenant/invitations/{$revokedInvitation->id}");

        $response2->assertStatus(422);
        $response2->assertJson([
            'ok' => false,
            'code' => 'VALIDATION_FAILED',
        ]);
        $response2->assertJsonPath('details.validation.invitation', fn($value) => !empty($value));

        // Verify invitation status unchanged
        $revokedInvitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_REVOKED, $revokedInvitation->status);
        $this->assertNotNull($revokedInvitation->revoked_at);
    }

    /**
     * Test that admin can resend pending invitation and mail is dispatched
     */
    public function test_admin_can_resend_pending_invitation_and_mail_is_dispatched(): void
    {
        Bus::fake();
        Mail::fake();

        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        $pendingInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$pendingInvitation->id}/resend");

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
        $response->assertJsonStructure([
            'data' => [
                'invitation' => [
                    'id',
                    'email',
                    'role',
                    'status',
                    'expires_at',
                ],
            ],
        ]);

        // Assert job was dispatched
        Bus::assertDispatched(SendTenantInvitationMail::class, function ($job) use ($pendingInvitation) {
            // Use reflection to access private property
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('invitationId');
            $property->setAccessible(true);
            return $property->getValue($job) === $pendingInvitation->id;
        });
    }

    /**
     * Test that member and viewer cannot resend invitation
     */
    public function test_member_and_viewer_cannot_resend_invitation(): void
    {
        $roles = ['member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            $pendingInvitation = TenantInvitation::create([
                'tenant_id' => $this->tenant->id,
                'email' => "test{$role}@example.com",
                'role' => 'member',
                'token' => \Illuminate\Support\Str::ulid()->toBase32(),
                'status' => TenantInvitation::STATUS_PENDING,
                'invited_by' => $this->invitation->invited_by,
                'expires_at' => now()->addDays(7),
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-resend-' . $role . '-' . uniqid(),
            ])->postJson("/api/v1/app/tenant/invitations/{$pendingInvitation->id}/resend");

            $response->assertStatus(403);
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that non-pending invitations cannot be resent
     */
    public function test_cannot_resend_non_pending_invitation(): void
    {
        $owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $owner->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($owner);
        $token = $owner->createToken('test-token')->plainTextToken;

        // Test case 1: accepted invitation
        $acceptedInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'accepted@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_ACCEPTED,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-accepted-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$acceptedInvitation->id}/resend");

        $response1->assertStatus(422);
        $response1->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_ACCEPTED',
        ]);

        // Test case 2: declined invitation
        $declinedInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'declined@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_DECLINED,
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-declined-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$declinedInvitation->id}/resend");

        $response2->assertStatus(422);
        $response2->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_DECLINED',
        ]);

        // Test case 3: revoked invitation
        $revokedInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'revoked@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_REVOKED,
            'invited_by' => $owner->id,
            'revoked_at' => now()->subDay(),
            'expires_at' => now()->addDays(7),
        ]);

        $response3 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-revoked-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$revokedInvitation->id}/resend");

        $response3->assertStatus(422);
        $response3->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_ALREADY_REVOKED',
        ]);

        // Test case 4: expired invitation
        $expiredInvitation = TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $owner->id,
            'expires_at' => now()->subDay(), // Expired
        ]);

        $response4 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-expired-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$expiredInvitation->id}/resend");

        $response4->assertStatus(422);
        $response4->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_EXPIRED',
        ]);

        // Verify invitation was marked as expired
        $expiredInvitation->refresh();
        $this->assertEquals(TenantInvitation::STATUS_EXPIRED, $expiredInvitation->status);
    }

    /**
     * Test that tenant A cannot resend invitation of tenant B
     */
    public function test_cannot_resend_invitation_of_another_tenant(): void
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

        $invitationB = TenantInvitation::create([
            'tenant_id' => $tenantB->id,
            'email' => 'tenantb@example.com',
            'role' => 'member',
            'token' => \Illuminate\Support\Str::ulid()->toBase32(),
            'status' => TenantInvitation::STATUS_PENDING,
            'invited_by' => $inviterB->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Create user in tenant A (our test tenant)
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;

        // User A should not be able to resend invitation of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-resend-cross-tenant-' . uniqid(),
        ])->postJson("/api/v1/app/tenant/invitations/{$invitationB->id}/resend");

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_INVITE_INVALID_TOKEN',
        ]);

        // Verify invitation B is still pending
        $invitationB->refresh();
        $this->assertEquals(TenantInvitation::STATUS_PENDING, $invitationB->status);
    }
}

