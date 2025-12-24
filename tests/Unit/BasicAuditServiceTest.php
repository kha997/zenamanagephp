<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Common\Services\AuditService;
use App\Models\AuditLog;

/**
 * Basic Unit tests cho AuditService without any models
 */
class BasicAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService();
    }

    /**
     * Test sensitive data filtering
     */
    public function test_sensitive_data_filtering(): void
    {
        $sensitiveData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'api_key' => 'sk_test_123456',
            'credit_card' => '4111-1111-1111-1111'
        ];
        
        $filtered = $this->auditService->filterSensitiveData($sensitiveData);
        
        $this->assertEquals('John Doe', $filtered['name']);
        $this->assertEquals('john@example.com', $filtered['email']);
        $this->assertEquals('[FILTERED]', $filtered['password']);
        $this->assertEquals('[FILTERED]', $filtered['api_key']);
        $this->assertEquals('[FILTERED]', $filtered['credit_card']);
    }

    /**
     * Test audit log creation with manual data
     */
    public function test_audit_log_creation(): void
    {
        // Create a real user first
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        
        $auditLog = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test.created',
            'entity_type' => 'test',
            'entity_id' => 'test-123',
            'old_data' => null,
            'new_data' => ['name' => 'Test Entity'],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser'
        ]);
        
        $this->assertNotNull($auditLog);
        $this->assertEquals($user->id, $auditLog->user_id);
        $this->assertEquals('test.created', $auditLog->action);
        $this->assertEquals('test', $auditLog->entity_type);
        $this->assertEquals('test-123', $auditLog->entity_id);
        
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'test.created',
            'entity_type' => 'test',
            'entity_id' => 'test-123'
        ]);
    }

    /**
     * Test audit log query
     */
    public function test_audit_log_query(): void
    {
        // Create a real user first
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create multiple audit logs manually
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test.created',
            'entity_type' => 'test',
            'entity_id' => 'test-123'
        ]);
        
        // Small delay to ensure different timestamps
        usleep(1000); // 1ms delay
        
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test.updated',
            'entity_type' => 'test',
            'entity_id' => 'test-123',
            'old_data' => ['name' => 'Old Name'],
            'new_data' => ['name' => 'New Name']
        ]);
        
        // Query audit trail
        $auditTrail = $this->auditService->getAuditTrail('test', 'test-123');
        
        $this->assertEquals(2, $auditTrail->count());
        
        $sorted = $auditTrail->sort(function ($a, $b) {
            $createdAtComparison = $a->created_at <=> $b->created_at;
            return $createdAtComparison ?: ($a->id <=> $b->id);
        });

        $actions = $sorted->pluck('action')->values()->toArray();
        $this->assertEquals(['test.created', 'test.updated'], $actions, 'Expected creation order, but got: ' . implode(', ', $actions));
    }
}
