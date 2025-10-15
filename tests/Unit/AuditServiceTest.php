<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Common\Services\AuditService;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

/**
 * Unit tests cho AuditService
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->auditService = new AuditService();
        
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'status' => 'active'
        ]);
    }

    /**
     * Test logging user actions
     */
    public function test_log_user_action(): void
    {
        $actionData = [
            'action' => 'project.created',
            'entity_type' => 'project',
            'entity_id' => $this->project->id,
            'old_values' => null,
            'new_values' => $this->project->toArray(),
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser'
        ];
        
        $auditLog = $this->auditService->logAction(
            (string) $this->user->id,
            $actionData['action'],
            $actionData['entity_type'],
            $actionData['entity_id'],
            $actionData['old_values'],
            $actionData['new_values'],
            $actionData['ip_address'],
            $actionData['user_agent']
        );
        
        $this->assertNotNull($auditLog);
        $this->assertEquals((string) $this->user->id, $auditLog->user_id);
        $this->assertEquals('project.created', $auditLog->action);
        $this->assertEquals('project', $auditLog->entity_type);
        $this->assertEquals($this->project->id, $auditLog->entity_id);
        
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'project.created',
            'entity_type' => 'project',
            'entity_id' => $this->project->id
        ]);
    }

    /**
     * Test audit trail query
     */
    public function test_audit_trail_query(): void
    {
        // Tạo multiple audit logs
        $this->auditService->logAction(
            (string) $this->user->id,
            'project.created',
            'project',
            $this->project->id
        );
        
        $this->auditService->logAction(
            (string) $this->user->id,
            'project.updated',
            'project',
            $this->project->id,
            ['name' => 'Old Name'],
            ['name' => 'New Name']
        );
        
        // Query audit trail
        $auditTrail = $this->auditService->getAuditTrail(
            'project',
            $this->project->id
        );
        
        $this->assertEquals(2, $auditTrail->count());
        $this->assertEquals('project.created', $auditTrail->first()->action);
        $this->assertEquals('project.updated', $auditTrail->last()->action);
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
     * Test audit log retention policy
     */
    public function test_audit_log_retention_policy(): void
    {
        // Tạo old audit logs
        $oldLog = $this->auditService->logAction(
            (string) $this->user->id,
            'old.action',
            'test',
            'test_id'
        );
        
        // Manually set created_at to old date
        $oldLog->created_at = now()->subYears(3);
        $oldLog->save();
        
        // Tạo recent log
        $recentLog = $this->auditService->logAction(
            (string) $this->user->id,
            'recent.action',
            'test',
            'test_id'
        );
        
        // Run cleanup (retention period: 2 years)
        $deletedCount = $this->auditService->cleanupOldLogs(2);
        
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('audit_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentLog->id]);
    }
}