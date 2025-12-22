<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Tenant;
use App\Services\InvitationService;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Unit tests for InvitationService
 * 
 * Tests business logic for invitation creation, acceptance, and idempotency
 * 
 * @group invitations
 */
class InvitationServiceTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private InvitationService $invitationService;
    private Tenant $tenant;
    private User $inviter;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('invitations');
        $this->setupDomainIsolation();

        $this->invitationService = new InvitationService();
        $this->tenant = TestDataSeeder::createTenant(['name' => 'Test Tenant']);
        $this->inviter = TestDataSeeder::createUser($this->tenant, [
            'email' => 'inviter@test.test',
            'role' => 'admin',
        ]);

        Mail::fake();
        Queue::fake();
    }

    /**
     * Test create invitation for new user
     */
    public function test_create_invitation_for_new_user(): void
    {
        $result = $this->invitationService->createInvitation([
            'email' => 'newuser@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ]);

        $this->assertEquals('created', $result['status']);
        $this->assertNotNull($result['invitation']);
        $this->assertNull($result['user']);
        $this->assertDatabaseHas('invitations', [
            'email' => 'newuser@test.test',
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test create invitation - already member
     */
    public function test_create_invitation_already_member(): void
    {
        $existingUser = TestDataSeeder::createUser($this->tenant, [
            'email' => 'existing@test.test',
            'role' => 'member',
        ]);

        $result = $this->invitationService->createInvitation([
            'email' => 'existing@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ]);

        $this->assertEquals('already_member', $result['status']);
        $this->assertNotNull($result['user']);
        $this->assertEquals($existingUser->id, $result['user']->id);
    }

    /**
     * Test create invitation - pending invitation exists
     */
    public function test_create_invitation_pending_exists(): void
    {
        $existingInvitation = Invitation::create([
            'email' => 'pending@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $result = $this->invitationService->createInvitation([
            'email' => 'pending@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ]);

        $this->assertEquals('pending_invitation', $result['status']);
        $this->assertEquals($existingInvitation->id, $result['invitation']->id);
    }

    /**
     * Test create invitation - user exists in different tenant
     */
    public function test_create_invitation_user_exists_different_tenant(): void
    {
        $otherTenant = TestDataSeeder::createTenant(['name' => 'Other Tenant']);
        $existingUser = TestDataSeeder::createUser($otherTenant, [
            'email' => 'cross@test.test',
            'role' => 'member',
        ]);

        $result = $this->invitationService->createInvitation([
            'email' => 'cross@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ]);

        // Should create invitation for cross-tenant membership
        $this->assertEquals('created', $result['status']);
        $this->assertNotNull($result['invitation']);
    }

    /**
     * Test bulk create invitations
     */
    public function test_bulk_create_invitations(): void
    {
        $emails = [
            'user1@test.test',
            'user2@test.test',
            'user3@test.test',
        ];
        
        $defaults = [
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ];
        
        $result = $this->invitationService->createBulkInvitations($emails, $defaults);

        $this->assertEquals(3, $result['summary']['total']);
        $this->assertEquals(3, $result['summary']['created']);
        $this->assertEquals(0, $result['summary']['already_member']);
        $this->assertEquals(0, $result['summary']['pending']);
        $this->assertEquals(0, $result['summary']['errors']);
    }

    /**
     * Test bulk create with mixed results
     */
    public function test_bulk_create_mixed_results(): void
    {
        // Create existing user
        TestDataSeeder::createUser($this->tenant, [
            'email' => 'existing@test.test',
            'role' => 'member',
        ]);

        // Create pending invitation
        Invitation::create([
            'email' => 'pending@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $emails = [
            'new1@test.test',
            'existing@test.test',
            'pending@test.test',
            'new2@test.test',
        ];
        
        $defaults = [
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'send_email' => false,
        ];
        
        $result = $this->invitationService->createBulkInvitations($emails, $defaults);

        $this->assertEquals(4, $result['summary']['total']);
        $this->assertEquals(2, $result['summary']['created']);
        $this->assertEquals(1, $result['summary']['already_member']);
        $this->assertEquals(1, $result['summary']['pending']);
    }

    /**
     * Test accept invitation - new user
     */
    public function test_accept_invitation_new_user(): void
    {
        $invitation = Invitation::create([
            'email' => 'newuser@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $user = $this->invitationService->acceptInvitation($invitation->token, [
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertNotNull($user);
        $this->assertEquals('newuser@test.test', $user->email);
        $this->assertEquals($this->tenant->id, $user->tenant_id);

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
        $this->assertNotNull($invitation->used_at);
    }

    /**
     * Test accept invitation - existing user
     */
    public function test_accept_invitation_existing_user(): void
    {
        $existingUser = TestDataSeeder::createUser($this->tenant, [
            'email' => 'existing@test.test',
            'role' => 'member',
        ]);

        $invitation = Invitation::create([
            'email' => 'existing@test.test',
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $user = $this->invitationService->acceptInvitation($invitation->token, [
            'user_id' => $existingUser->id,
        ]);

        $this->assertNotNull($user);
        $this->assertEquals($existingUser->id, $user->id);

        $invitation->refresh();
        $this->assertEquals('accepted', $invitation->status);
        $this->assertNotNull($invitation->used_at);
    }

    /**
     * Test accept invitation - expired
     */
    public function test_accept_invitation_expired(): void
    {
        $invitation = Invitation::create([
            'email' => 'expired@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invitation has expired');

        $this->invitationService->acceptInvitation($invitation->token, [
            'name' => 'User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    /**
     * Test accept invitation - already used
     */
    public function test_accept_invitation_already_used(): void
    {
        $invitation = Invitation::create([
            'email' => 'used@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'accepted',
            'used_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invitation has already been used');

        $this->invitationService->acceptInvitation($invitation->token, [
            'name' => 'User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    /**
     * Test resend invitation
     */
    public function test_resend_invitation(): void
    {
        $invitation = Invitation::create([
            'email' => 'resend@test.test',
            'role' => 'member',
            'tenant_id' => $this->tenant->id,
            'invited_by' => $this->inviter->id,
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $oldToken = $invitation->token;
        $oldExpiresAt = $invitation->expires_at;

        $resendInvitation = $this->invitationService->resendInvitation($invitation->id);

        $invitation->refresh();
        $this->assertNotEquals($oldToken, $invitation->token);
        // expires_at should be updated to a future date (7 days from now)
        $this->assertGreaterThan($oldExpiresAt->timestamp, $invitation->expires_at->timestamp);
        $this->assertNull($invitation->used_at);
        $this->assertEquals('pending', $invitation->status);
    }

    /**
     * Test check existing user
     */
    public function test_check_existing_user(): void
    {
        $existingUser = TestDataSeeder::createUser($this->tenant, [
            'email' => 'check@test.test',
            'role' => 'member',
        ]);

        $result = $this->invitationService->checkExistingUser('check@test.test', $this->tenant->id);

        $this->assertNotNull($result);
        $this->assertEquals($existingUser->id, $result->id);
    }

    /**
     * Test check existing user - not found
     */
    public function test_check_existing_user_not_found(): void
    {
        $result = $this->invitationService->checkExistingUser('notfound@test.test', $this->tenant->id);

        $this->assertNull($result);
    }

    /**
     * Test is email configured
     */
    public function test_is_email_configured(): void
    {
        // Default should be false (array driver in tests)
        $this->assertFalse($this->invitationService->isEmailConfigured());

        // Can't easily test true case without changing config, but method exists
        $this->assertIsBool($this->invitationService->isEmailConfigured());
    }
}
