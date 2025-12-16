<?php declare(strict_types=1);

namespace Tests\Unit\WebSocket;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\WebSocket\AuthGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AuthGuard Unit Tests
 * 
 * PR #3: Tests for WebSocket authentication and authorization guard
 */
class AuthGuardTest extends TestCase
{
    use RefreshDatabase;

    private AuthGuard $authGuard;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;
    private Task $taskA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable Scout/Meilisearch for tests
        \App\Models\Project::unsetEventDispatcher();
        \App\Models\Task::unsetEventDispatcher();
        
        $this->authGuard = new AuthGuard();
        
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'project_manager',
            'is_active' => true,
        ]);
        
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        
        $this->taskA = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
        ]);
    }

    /**
     * Test token verification with valid Sanctum token
     */
    public function test_verify_token_with_valid_token(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $user = $this->authGuard->verifyToken($token);
        
        $this->assertNotNull($user);
        $this->assertEquals($this->userA->id, $user->id);
    }

    /**
     * Test token verification with invalid token
     */
    public function test_verify_token_with_invalid_token(): void
    {
        $user = $this->authGuard->verifyToken('invalid-token');
        
        $this->assertNull($user);
    }

    /**
     * Test token verification with disabled user
     */
    public function test_verify_token_with_disabled_user(): void
    {
        $this->userA->is_active = false;
        $this->userA->save();
        
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $user = $this->authGuard->verifyToken($token);
        
        $this->assertNull($user);
    }

    /**
     * Test channel format validation
     */
    public function test_is_valid_channel_format(): void
    {
        // Valid formats
        $this->assertTrue($this->authGuard->isValidChannelFormat("tenant:{$this->tenantA->id}:projects"));
        $this->assertTrue($this->authGuard->isValidChannelFormat("tenant:{$this->tenantA->id}:tasks:{$this->taskA->id}"));
        
        // Legacy formats (still valid for backward compatibility)
        $this->assertTrue($this->authGuard->isValidChannelFormat("tenant.{$this->tenantA->id}"));
        $this->assertTrue($this->authGuard->isValidChannelFormat("project.{$this->projectA->id}"));
        $this->assertTrue($this->authGuard->isValidChannelFormat("App.Models.User.{$this->userA->id}"));
        $this->assertTrue($this->authGuard->isValidChannelFormat('admin-security'));
        
        // Invalid formats
        $this->assertFalse($this->authGuard->isValidChannelFormat('invalid:format'));
        $this->assertFalse($this->authGuard->isValidChannelFormat('tenant'));
        $this->assertFalse($this->authGuard->isValidChannelFormat('tenant:'));
    }

    /**
     * Test canSubscribe blocks cross-tenant subscription
     */
    public function test_can_subscribe_blocks_cross_tenant(): void
    {
        $tenantBChannel = "tenant:{$this->tenantB->id}:projects";
        
        $result = $this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $tenantBChannel);
        
        $this->assertFalse($result, 'User A should not be able to subscribe to tenant B channel');
    }

    /**
     * Test canSubscribe allows own tenant channel
     */
    public function test_can_subscribe_allows_own_tenant(): void
    {
        $tenantAChannel = "tenant:{$this->tenantA->id}:projects";
        
        $result = $this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $tenantAChannel);
        
        $this->assertTrue($result, 'User A should be able to subscribe to tenant A channel');
    }

    /**
     * Test canSubscribe allows resource channel with permission
     */
    public function test_can_subscribe_allows_resource_with_permission(): void
    {
        $projectChannel = "tenant:{$this->tenantA->id}:projects:{$this->projectA->id}";
        
        $result = $this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $projectChannel);
        
        $this->assertTrue($result, 'User A should be able to subscribe to project channel if they have view permission');
    }

    /**
     * Test canSubscribe blocks resource channel without permission
     */
    public function test_can_subscribe_blocks_resource_without_permission(): void
    {
        // Create a task in tenant B
        $taskB = Task::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        // User A tries to subscribe to tenant B task
        $taskChannel = "tenant:{$this->tenantB->id}:tasks:{$taskB->id}";
        
        $result = $this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $taskChannel);
        
        $this->assertFalse($result, 'User A should not be able to subscribe to tenant B task channel');
    }

    /**
     * Test legacy channel format support
     */
    public function test_can_subscribe_supports_legacy_formats(): void
    {
        // Legacy tenant format
        $legacyTenantChannel = "tenant.{$this->tenantA->id}";
        $this->assertTrue($this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $legacyTenantChannel));
        
        // Legacy project format
        $legacyProjectChannel = "project.{$this->projectA->id}";
        $this->assertTrue($this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $legacyProjectChannel));
        
        // Legacy user format
        $legacyUserChannel = "App.Models.User.{$this->userA->id}";
        $this->assertTrue($this->authGuard->canSubscribe($this->userA, (string) $this->tenantA->id, $legacyUserChannel));
    }
}

