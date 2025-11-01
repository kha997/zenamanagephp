<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Broadcast;
use App\Events\Security\LoginFailed;
use App\Events\Security\KeyRevoked;
use App\Events\Security\SessionEnded;

class SecurityApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'super_admin'
        ]);
        
        $this->token = $this->user->createToken('admin', ['admin'])->plainTextToken;
    }

    /** @test */
    public function it_requires_authentication_for_security_endpoints()
    {
        $response = $this->getJson('/api/admin/security/kpis');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_admin_ability_for_security_endpoints()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('user')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/admin/security/kpis');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_security_kpis_for_admin_users()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/kpis');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'mfaAdoption' => ['value', 'deltaPct', 'series', 'period'],
                    'failedLogins' => ['value', 'deltaAbs', 'series', 'period'],
                    'lockedAccounts' => ['value', 'deltaAbs', 'series', 'period'],
                    'activeSessions' => ['value', 'deltaAbs', 'series', 'period'],
                    'riskyKeys' => ['value', 'deltaAbs', 'series', 'period'],
                    'loginAttempts' => ['success', 'failed']
                ],
                'meta' => ['generatedAt']
            ]);
    }

    /** @test */
    public function it_returns_mfa_users_with_pagination()
    {
        User::factory()->count(25)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/mfa?per_page=10&page=1');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'mfa_enabled', 'last_login_at', 'created_at']
                ],
                'meta' => ['total', 'page', 'per_page', 'last_page', 'generatedAt']
            ]);
    }

    /** @test */
    public function it_returns_login_attempts_with_filters()
    {
        AuditLog::factory()->count(10)->create([
            'action' => 'login_failed'
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/logins?result=failed&per_page=5');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_email', 'result', 'ip_address', 'user_agent', 'created_at']
                ],
                'meta' => ['total', 'page', 'per_page', 'last_page', 'generatedAt']
            ]);
    }

    /** @test */
    public function it_returns_audit_logs_with_filters()
    {
        AuditLog::factory()->count(15)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/audit?action=login&per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action', 'user_email', 'severity', 'result', 'ip_address', 'user_agent', 'details', 'created_at']
                ],
                'meta' => ['total', 'page', 'per_page', 'last_page', 'generatedAt']
            ]);
    }

    /** @test */
    public function it_returns_active_sessions()
    {
        User::factory()->count(5)->create([
            'last_login_at' => now()->subMinutes(15)
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/sessions?active_only=true');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'ip_address', 'user_agent', 'last_seen', 'created_at']
                ],
                'meta' => ['total', 'page', 'per_page', 'last_page', 'generatedAt']
            ]);
    }

    /** @test */
    public function it_can_force_mfa_for_user()
    {
        $user = User::factory()->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson("/api/admin/security/users/{$user->id}:force-mfa");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['success', 'user_id', 'user_email', 'forced_at']
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_user_force_mfa()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/security/users/99999:force-mfa');
        
        $response->assertStatus(404)
            ->assertJsonStructure(['error' => ['code', 'message']]);
    }

    /** @test */
    public function it_can_export_audit_logs()
    {
        AuditLog::factory()->count(5)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/audit/export');
        
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition');
    }

    /** @test */
    public function it_can_export_mfa_users()
    {
        User::factory()->count(3)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/mfa/export');
        
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition');
    }

    /** @test */
    public function it_can_export_login_attempts()
    {
        AuditLog::factory()->count(3)->create([
            'action' => 'login_failed'
        ]);
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/logins/export');
        
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv')
            ->assertHeader('Content-Disposition');
    }

    /** @test */
    public function it_respects_rate_limits_for_exports()
    {
        // Make multiple export requests quickly
        for ($i = 0; $i < 12; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token
            ])->getJson('/api/admin/security/audit/export');
            
            if ($i >= 10) {
                $response->assertStatus(429)
                    ->assertHeader('Retry-After');
            }
        }
    }

    /** @test */
    public function it_can_trigger_test_events()
    {
        Event::fake();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'timestamp']);
        
        Event::assertDispatched(LoginFailed::class);
    }

    /** @test */
    public function it_broadcasts_security_events()
    {
        Broadcast::fake();
        
        event(new LoginFailed(
            now()->toISOString(),
            'test@example.com',
            '192.168.1.100',
            'US',
            'Test Tenant'
        ));
        
        Broadcast::assertDispatched(LoginFailed::class, function ($event) {
            return $event->email === 'test@example.com' && $event->ip === '192.168.1.100';
        });
    }

    /** @test */
    public function it_broadcasts_key_revoked_events()
    {
        Broadcast::fake();
        
        event(new KeyRevoked(
            'test-key-123',
            'admin@example.com',
            now()->toISOString(),
            'Manual revocation'
        ));
        
        Broadcast::assertDispatched(KeyRevoked::class, function ($event) {
            return $event->keyId === 'test-key-123' && $event->ownerEmail === 'admin@example.com';
        });
    }

    /** @test */
    public function it_broadcasts_session_ended_events()
    {
        Broadcast::fake();
        
        event(new SessionEnded(
            'session-456',
            'user@example.com',
            now()->toISOString(),
            'Manual logout',
            '192.168.1.101'
        ));
        
        Broadcast::assertDispatched(SessionEnded::class, function ($event) {
            return $event->sessionId === 'session-456' && $event->userEmail === 'user@example.com';
        });
    }

    /** @test */
    public function it_handles_period_parameter_for_kpis()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/kpis?period=7d');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals('7d', $data['mfaAdoption']['period']);
        $this->assertCount(7, $data['mfaAdoption']['series']);
    }

    /** @test */
    public function it_handles_invalid_period_parameter()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/admin/security/kpis?period=invalid');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals('30d', $data['mfaAdoption']['period']); // Default fallback
    }
}
