<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use App\Events\Security\LoginFailed;
use App\Events\Security\KeyRevoked;
use App\Events\Security\SessionEnded;

class SecurityApiBroadcastTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create super admin user
        $this->user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@example.com'
        ]);
        
        // Create token with admin ability
        $this->token = $this->user->createToken('admin', ['admin'])->plainTextToken;
    }

    /** @test */
    public function it_broadcasts_login_failed_event()
    {
        Broadcast::fake();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event login_failed triggered successfully',
                'broadcast_status' => 'success'
            ]);
        
        // Verify event was broadcast
        Broadcast::assertDispatched(LoginFailed::class, function ($event) {
            return $event->email === 'test@example.com' &&
                   $event->ip === '192.168.1.100' &&
                   $event->country === 'US';
        });
    }

    /** @test */
    public function it_broadcasts_key_revoked_event()
    {
        Broadcast::fake();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'key_revoked'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event key_revoked triggered successfully',
                'broadcast_status' => 'success'
            ]);
        
        // Verify event was broadcast
        Broadcast::assertDispatched(KeyRevoked::class, function ($event) {
            return $event->keyId === 'key_123456' &&
                   $event->ownerEmail === 'admin@example.com';
        });
    }

    /** @test */
    public function it_broadcasts_session_ended_event()
    {
        Broadcast::fake();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'session_ended'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event session_ended triggered successfully',
                'broadcast_status' => 'success'
            ]);
        
        // Verify event was broadcast
        Broadcast::assertDispatched(SessionEnded::class, function ($event) {
            return $event->sessionId === 'session_789' &&
                   $event->userEmail === 'user@example.com';
        });
    }

    /** @test */
    public function it_handles_broadcast_failure_gracefully()
    {
        // Mock Redis connection failure
        $this->app['config']->set('broadcasting.default', 'log');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event login_failed triggered successfully',
                'broadcast_status' => 'disabled',
                'note' => 'Broadcasting disabled or Redis not configured'
            ]);
    }

    /** @test */
    public function it_respects_test_event_feature_flag()
    {
        // Disable test events
        $this->app['config']->set('security.allow_test_event', false);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(503)
            ->assertJson([
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'Test events are disabled in production'
                ]
            ]);
    }

    /** @test */
    public function it_authorizes_broadcast_channel()
    {
        // Test super admin can access channel
        $this->actingAs($this->user);
        
        $response = $this->get('/broadcasting/auth', [
            'channel_name' => 'private-admin-security'
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_denies_broadcast_channel_for_regular_users()
    {
        // Create regular user
        $regularUser = User::factory()->create(['role' => 'user']);
        
        $this->actingAs($regularUser);
        
        $response = $this->get('/broadcasting/auth', [
            'channel_name' => 'private-admin-security'
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_denies_broadcast_channel_for_unauthorized_tokens()
    {
        // Create user without admin ability
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('user')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->get('/broadcasting/auth', [
            'channel_name' => 'private-admin-security'
        ]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_includes_correlation_id_in_broadcast_events()
    {
        Broadcast::fake();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Request-Id' => 'test-correlation-id'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200);
        
        // Verify event includes correlation ID
        Broadcast::assertDispatched(LoginFailed::class, function ($event) {
            return isset($event->correlationId) && $event->correlationId === 'test-correlation-id';
        });
    }

    /** @test */
    public function it_limits_broadcast_event_frequency()
    {
        Broadcast::fake();
        
        // Send multiple events rapidly
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post('/api/admin/security/test-event', [
                'event' => 'login_failed'
            ]);
            
            $response->assertStatus(200);
        }
        
        // Should only broadcast limited number of events
        Broadcast::assertDispatched(LoginFailed::class, 5); // Limited to 5 events
    }

    /** @test */
    public function it_logs_broadcast_events()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200);
        
        // Verify event is logged
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test_event_triggered',
            'user_id' => $this->user->id,
            'details' => json_encode([
                'event_type' => 'login_failed',
                'broadcast_status' => 'success'
            ])
        ]);
    }

    /** @test */
    public function it_handles_broadcast_queue_failures()
    {
        // Mock queue failure
        $this->app['config']->set('queue.default', 'sync');
        $this->app['config']->set('broadcasting.default', 'redis');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event login_failed triggered successfully',
                'broadcast_status' => 'disabled',
                'note' => 'Broadcasting queue not available'
            ]);
    }
}
