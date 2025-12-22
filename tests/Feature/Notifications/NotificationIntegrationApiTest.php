<?php declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Role;
use App\Models\RoleProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectTaskManagementService;
use App\Services\RoleProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * NotificationIntegrationApiTest - Round 252: Notifications Center Phase 2
 * 
 * Integration tests for notification creation from various domain events:
 * - Task creation with assignee
 * - Task assignee change
 * - Change Order final approval
 * - Payment Certificate approval
 * - Payment marked paid
 * - Role Profile assignment
 * - Tenant isolation
 * 
 * @group notifications
 * @group integration
 * @group api-v1
 */
class NotificationIntegrationApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected User $assigneeA;
    protected User $assigneeB;
    protected Project $projectA;
    protected Project $projectB;
    protected \App\Models\Role $pmRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(252001);
        $this->setDomainName('notification-integration');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users for tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->assigneeA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'member',
        ]);


        // Create users for tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        $this->assigneeB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'member',
        ]);

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->assigneeA->tenants()->attach($this->tenantA->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->assigneeB->tenants()->attach($this->tenantB->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Setup permissions for cost approval
        $costApprovePermission = \App\Models\Permission::firstOrCreate(
            ['code' => 'projects.cost.approve'],
            [
                'module' => 'projects.cost',
                'action' => 'approve',
                'description' => 'Approve cost items',
            ]
        );
        
        $pmRole = \App\Models\Role::firstOrCreate(
            ['name' => 'pm'],
            [
                'scope' => 'system',
                'description' => 'Project Manager',
            ]
        );
        $pmRole->permissions()->syncWithoutDetaching([$costApprovePermission->id]);
        
        // Attach roles to users
        $this->userA->roles()->syncWithoutDetaching([$pmRole->id]);
        $this->userB->roles()->syncWithoutDetaching([$pmRole->id]);

        // Refresh users
        $this->userA->refresh();
        $this->userB->refresh();
        $this->assigneeA->refresh();
        $this->assigneeB->refresh();
        
        // Load relationships for permission checks
        $this->userA->load('roles.permissions');
        $this->userB->load('roles.permissions');
        
        // Store pmRole for use in test methods
        $this->pmRole = $pmRole;

        // Create projects
        $this->projectA = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
            'code' => 'PRJ-A-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);

        $this->projectB = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
            'code' => 'PRJ-B-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);
    }

    /**
     * Test task creation with assignee creates notification
     */
    public function test_task_creation_with_assignee_creates_notification(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $projectTaskService = app(ProjectTaskManagementService::class);
        
        // Create task with assignee
        $task = $projectTaskService->createTaskForProject(
            (string) $this->tenantA->id,
            $this->projectA->id,
            [
                'name' => 'Test Task',
                'description' => 'Test task description',
                'assignee_id' => $this->assigneeA->id,
            ]
        );

        // Assert notification was created
        $notification = Notification::where('user_id', $this->assigneeA->id)
            ->where('module', 'tasks')
            ->where('type', 'task.assigned')
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Bạn được giao một công việc mới', $notification->title);
        $this->assertStringContainsString('Test Task', $notification->message);
        $this->assertStringContainsString('Project A', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);
        $this->assertFalse($notification->is_read);
        $this->assertArrayHasKey('project_id', $notification->metadata);
        $this->assertArrayHasKey('project_name', $notification->metadata);
    }

    /**
     * Test task assignee change creates notification for new assignee
     */
    public function test_task_assignee_change_creates_notification_for_new_assignee(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $projectTaskService = app(ProjectTaskManagementService::class);
        
        // Create task with assignee A
        $task = $projectTaskService->createTaskForProject(
            (string) $this->tenantA->id,
            $this->projectA->id,
            [
                'name' => 'Test Task',
                'assignee_id' => $this->assigneeA->id,
            ]
        );

        // Clear any notifications from creation
        Notification::where('user_id', $this->assigneeA->id)->delete();

        // Create another user to assign to
        $newAssignee = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'member',
        ]);
        $newAssignee->tenants()->attach($this->tenantA->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        // Change assignee to new user
        $projectTaskService->updateTaskForProject(
            (string) $this->tenantA->id,
            $this->projectA,
            $task->id,
            [
                'assignee_id' => $newAssignee->id,
            ]
        );

        // Assert notification was created for new assignee
        $notification = Notification::where('user_id', $newAssignee->id)
            ->where('module', 'tasks')
            ->where('type', 'task.assignee_changed')
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Bạn được giao một công việc', $notification->title);
        $this->assertStringContainsString('Test Task', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);

        // Assert no notification for old assignee
        $oldAssigneeNotification = Notification::where('user_id', $this->assigneeA->id)
            ->where('type', 'task.assignee_changed')
            ->first();
        $this->assertNull($oldAssigneeNotification);
    }

    /**
     * Test CO final approval creates notification for creator
     */
    public function test_co_final_approval_creates_notification_for_creator(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create contract
        $contract = Contract::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Contract',
            'code' => 'CON-001',
            'party_name' => 'Test Contractor',
            'base_amount' => 1000000,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create change order
        $changeOrder = ChangeOrder::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'code' => 'CO-001',
            'title' => 'Test Change Order',
            'status' => 'proposed',
            'amount_delta' => 100000,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);

        // Approve change order (simulate API call)
        // Note: We need a different user to approve (not the creator) to trigger notification
        $approver = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);
        $approver->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        $approver->roles()->syncWithoutDetaching([$this->pmRole->id]);
        
        // Clear cache and reload relationships
        $approver = $approver->fresh(['roles.permissions']);
        
        // Verify permission is set up correctly
        $hasPermission = $approver->roles()->whereHas('permissions', function ($query) {
            $query->where('code', 'projects.cost.approve');
        })->exists();
        
        if (!$hasPermission) {
            $this->markTestSkipped('Approver does not have projects.cost.approve permission - test setup issue');
        }
        
        Sanctum::actingAs($approver, [], 'sanctum');
        
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$contract->id}/change-orders/{$changeOrder->id}/approve"
        );

        if ($response->status() === 403) {
            $this->fail('Approval failed with 403 - approver does not have permission. Response: ' . $response->getContent());
        }
        
        $response->assertStatus(200);

        // Assert notification was created for creator (not approver)
        $notification = Notification::where('user_id', $this->userA->id)
            ->where('module', 'cost')
            ->where('type', 'co.approved')
            ->where('entity_type', 'change_order')
            ->where('entity_id', $changeOrder->id)
            ->first();

        $this->assertNotNull($notification, 'Notification should be created for CO creator when approved by different user');
        $this->assertEquals('Change order đã được phê duyệt', $notification->title);
        $this->assertStringContainsString('CO-001', $notification->message);
        $this->assertStringContainsString('Project A', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);
        $this->assertArrayHasKey('project_id', $notification->metadata);
        $this->assertArrayHasKey('project_name', $notification->metadata);
        $this->assertArrayHasKey('status', $notification->metadata);
    }

    /**
     * Test certificate approval creates notification for creator
     */
    public function test_certificate_approval_creates_notification_for_creator(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create contract
        $contract = Contract::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Contract',
            'code' => 'CON-001',
            'party_name' => 'Test Contractor',
            'base_amount' => 1000000,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create payment certificate
        $certificate = ContractPaymentCertificate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'code' => 'IPC-001',
            'title' => 'Interim Payment Certificate #01',
            'status' => 'submitted',
            'amount_before_retention' => 500000,
            'retention_amount' => 50000,
            'amount_payable' => 450000,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);

        // Approve certificate (simulate API call)
        // Note: We need a different user to approve (not the creator) to trigger notification
        $approver = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);
        $approver->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        $approver->roles()->syncWithoutDetaching([$this->pmRole->id]);
        
        // Clear cache and reload relationships
        $approver = $approver->fresh(['roles.permissions']);
        
        // Verify permission is set up correctly
        $hasPermission = $approver->roles()->whereHas('permissions', function ($query) {
            $query->where('code', 'projects.cost.approve');
        })->exists();
        
        if (!$hasPermission) {
            $this->markTestSkipped('Approver does not have projects.cost.approve permission - test setup issue');
        }
        
        Sanctum::actingAs($approver, [], 'sanctum');
        
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$contract->id}/payment-certificates/{$certificate->id}/approve"
        );

        if ($response->status() === 403) {
            $this->fail('Approval failed with 403 - approver does not have permission. Response: ' . $response->getContent());
        }
        
        $response->assertStatus(200);

        // Assert notification was created for creator (not approver)
        $notification = Notification::where('user_id', $this->userA->id)
            ->where('module', 'cost')
            ->where('type', 'certificate.approved')
            ->where('entity_type', 'payment_certificate')
            ->where('entity_id', $certificate->id)
            ->first();

        $this->assertNotNull($notification, 'Notification should be created for certificate creator when approved by different user');
        $this->assertEquals('Chứng chỉ thanh toán đã được phê duyệt', $notification->title);
        $this->assertStringContainsString('IPC-001', $notification->message);
        $this->assertStringContainsString('Project A', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);
    }

    /**
     * Test payment mark paid creates notification for creator
     */
    public function test_payment_mark_paid_creates_notification_for_creator(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create contract
        $contract = Contract::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Test Contract',
            'code' => 'CON-001',
            'party_name' => 'Test Contractor',
            'base_amount' => 1000000,
            'status' => 'active',
            'created_by_id' => $this->userA->id,
            'updated_by_id' => $this->userA->id,
        ]);

        // Create payment
        $payment = ContractActualPayment::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'contract_id' => $contract->id,
            'paid_date' => null,
            'amount_paid' => 100000,
            'status' => 'planned',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);

        // Mark payment as paid (simulate API call)
        // Note: We need a different user to mark paid (not the creator) to trigger notification
        $approver = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);
        $approver->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        $approver->roles()->syncWithoutDetaching([$this->pmRole->id]);
        
        // Clear cache and reload relationships
        $approver = $approver->fresh(['roles.permissions']);
        
        // Verify permission is set up correctly
        $hasPermission = $approver->roles()->whereHas('permissions', function ($query) {
            $query->where('code', 'projects.cost.approve');
        })->exists();
        
        if (!$hasPermission) {
            $this->markTestSkipped('Approver does not have projects.cost.approve permission - test setup issue');
        }
        
        Sanctum::actingAs($approver, [], 'sanctum');
        
        $response = $this->postJson(
            "/api/v1/app/projects/{$this->projectA->id}/contracts/{$contract->id}/payments/{$payment->id}/mark-paid"
        );

        if ($response->status() === 403) {
            $this->fail('Mark paid failed with 403 - approver does not have permission. Response: ' . $response->getContent());
        }
        
        $response->assertStatus(200);

        // Assert notification was created for creator (not approver)
        $notification = Notification::where('user_id', $this->userA->id)
            ->where('module', 'cost')
            ->where('type', 'payment.marked_paid')
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->first();

        $this->assertNotNull($notification, 'Notification should be created for payment creator when marked paid by different user');
        $this->assertEquals('Thanh toán đã được ghi nhận', $notification->title);
        $this->assertStringContainsString('Project A', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);
        $this->assertArrayHasKey('amount', $notification->metadata);
    }

    /**
     * Test profile assignment creates notification for user
     */
    public function test_profile_assignment_creates_notification_for_user(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create a role first
        $role = Role::factory()->create([
            'name' => 'member',
            'scope' => 'system',
        ]);

        // Create role profile with role ID
        $profile = RoleProfile::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $this->tenantA->id,
            'name' => 'Test Profile',
            'description' => 'Test profile description',
            'roles' => [$role->id],
            'is_active' => true,
        ]);

        // Assign profile to user
        $roleProfileService = app(RoleProfileService::class);
        $roleProfileService->assignProfileToUser($this->assigneeA, $profile);

        // Assert notification was created
        $notification = Notification::where('user_id', $this->assigneeA->id)
            ->where('module', 'rbac')
            ->where('type', 'user.profile_assigned')
            ->where('entity_type', 'role_profile')
            ->where('entity_id', $profile->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Quyền truy cập của bạn đã được cập nhật', $notification->title);
        $this->assertStringContainsString('Test Profile', $notification->message);
        $this->assertEquals($this->tenantA->id, $notification->tenant_id);
        $this->assertArrayHasKey('profile_name', $notification->metadata);
    }

    /**
     * Test notifications respect tenant isolation in integration
     */
    public function test_notifications_respect_tenant_isolation_in_integration(): void
    {
        // Create task in tenant A with assignee A (same as test_task_creation_with_assignee_creates_notification)
        Sanctum::actingAs($this->userA, [], 'sanctum');
        $projectTaskService = app(ProjectTaskManagementService::class);
        
        $taskA = $projectTaskService->createTaskForProject(
            (string) $this->tenantA->id,
            $this->projectA->id,
            [
                'name' => 'Task A',
                'assignee_id' => $this->assigneeA->id,
            ]
        );

        // Assert notification was created for tenant A (same assertion as test_task_creation_with_assignee_creates_notification)
        $notificationA = Notification::where('user_id', $this->assigneeA->id)
            ->where('module', 'tasks')
            ->where('type', 'task.assigned')
            ->where('entity_type', 'task')
            ->where('entity_id', $taskA->id)
            ->first();

        $this->assertNotNull($notificationA, 'Notification for task A should exist');
        
        // Debug: Check actual tenant_id value
        $actualTenantId = (string) $notificationA->tenant_id;
        $expectedTenantId = (string) $this->tenantA->id;
        $this->assertEquals($expectedTenantId, $actualTenantId, "Notification should have tenant A id. Expected: {$expectedTenantId}, Got: {$actualTenantId}, Type: " . gettype($notificationA->tenant_id));

        // Create task in tenant B with assignee B
        Sanctum::actingAs($this->userB, [], 'sanctum');
        $taskB = $projectTaskService->createTaskForProject(
            (string) $this->tenantB->id,
            $this->projectB->id,
            [
                'name' => 'Task B',
                'assignee_id' => $this->assigneeB->id,
            ]
        );

        // Assert notification was created for tenant B
        $notificationB = Notification::where('user_id', $this->assigneeB->id)
            ->where('module', 'tasks')
            ->where('type', 'task.assigned')
            ->where('entity_type', 'task')
            ->where('entity_id', $taskB->id)
            ->first();

        $this->assertNotNull($notificationB, 'Notification for task B should exist');
        $this->assertEquals((string) $this->tenantB->id, $notificationB->tenant_id, 'Notification should have tenant B id');

        // Verify tenant isolation: tenant A notifications should only show tenant A data
        // Use withoutGlobalScope to ensure we can query across tenants for isolation testing
        $notificationsA = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $notificationsA->count(), 'Should have at least 1 notification for tenant A');
        
        // Find the notification for task A
        $taskANotificationInList = $notificationsA->firstWhere('entity_id', $taskA->id);
        $this->assertNotNull($taskANotificationInList, 'Should have notification for task A in tenant A list');
        $this->assertEquals((string) $this->tenantA->id, $taskANotificationInList->tenant_id);

        // Verify tenant isolation: tenant B notifications should only show tenant B data
        // Use withoutGlobalScope to ensure we can query across tenants for isolation testing
        $notificationsB = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $this->tenantB->id)
            ->where('user_id', $this->assigneeB->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $notificationsB->count(), 'Should have at least 1 notification for tenant B');
        
        // Find the notification for task B
        $taskBNotificationInList = $notificationsB->firstWhere('entity_id', $taskB->id);
        $this->assertNotNull($taskBNotificationInList, 'Should have notification for task B in tenant B list');
        $this->assertEquals((string) $this->tenantB->id, $taskBNotificationInList->tenant_id);

        // Verify tenant A user cannot see tenant B notifications via API
        Sanctum::actingAs($this->assigneeA, [], 'sanctum');
        $response = $this->getJson('/api/v1/app/notifications');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data), 'Should have at least 1 notification');
        
        // All notifications should be for tenant A
        foreach ($data as $notification) {
            $this->assertEquals((string) $this->tenantA->id, $notification['tenant_id'], 'All notifications should be for tenant A');
        }
        
        // Should have notification for task A
        $taskANotificationInResponse = collect($data)->firstWhere('entity_id', $taskA->id);
        $this->assertNotNull($taskANotificationInResponse, 'Notification for task A should be in API response');
    }
}
