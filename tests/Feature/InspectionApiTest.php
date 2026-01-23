<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class InspectionApiTest extends TestCase
{
    use RefreshDatabase, RbacTestTrait;

    public function test_cannot_list_inspections_without_permission(): void
    {
        $context = $this->actingAsWithPermissions([]);
        $headers = $this->headersForUser($context['user']);

        $response = $this->withHeaders($headers)
            ->getJson('/api/zena/inspections');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Insufficient permissions to view inspections'
            ]);
    }

    public function test_can_create_inspection_with_permissions(): void
    {
        $context = $this->actingAsWithPermissions(['inspection.create']);
        $user = $context['user'];

        $plan = $this->createPlanForUser($user);
        $inspector = User::factory()->create(['tenant_id' => $user->tenant_id]);

        $payload = [
            'qc_plan_id' => $plan->id,
            'title' => 'Foundation pour inspection',
            'description' => 'Verify concrete mix',
            'inspection_date' => now()->addDay()->format('Y-m-d'),
            'inspector_id' => $inspector->id,
            'status' => 'scheduled',
            'checklist_results' => [
                ['item' => 'Rebar layout', 'result' => 'pass']
            ],
            'photos' => [
                ['filename' => 'test.jpg', 'path' => 'qc_inspections/test.jpg']
            ]
        ];

        $headers = $this->headersForUser($user);

        $response = $this->withHeaders($headers)
            ->postJson('/api/zena/inspections', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.inspection.title', 'Foundation pour inspection')
            ->assertJsonPath('data.inspection.tenant_id', (string) $user->tenant_id);

        $this->assertDatabaseHas('qc_inspections', [
            'title' => 'Foundation pour inspection',
            'tenant_id' => (string) $user->tenant_id,
            'qc_plan_id' => (string) $plan->id,
        ]);
    }

    public function test_tenant_isolation_blocks_other_tenant_records(): void
    {
        $context = $this->actingAsWithPermissions(['inspection.read']);
        $headers = $this->headersForUser($context['user']);

        $otherInspection = $this->createInspectionForOtherTenant();

        $response = $this->withHeaders($headers)
            ->getJson("/api/zena/inspections/{$otherInspection->id}");

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Inspection not found'
            ]);
    }

    public function test_schedule_requires_permission(): void
    {
        $context = $this->actingAsWithPermissions([]);
        $user = $context['user'];
        $inspection = $this->createInspectionForUser($user);
        $headers = $this->headersForUser($user);

        $response = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/schedule");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Insufficient permissions to schedule inspections'
            ]);
    }

    public function test_conduct_requires_permission(): void
    {
        $context = $this->actingAsWithPermissions([]);
        $user = $context['user'];
        $inspection = $this->createInspectionForUser($user);
        $headers = $this->headersForUser($user);

        $response = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/conduct");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Insufficient permissions to conduct inspections'
            ]);
    }

    public function test_complete_requires_permission(): void
    {
        $context = $this->actingAsWithPermissions([]);
        $user = $context['user'];
        $inspection = $this->createInspectionForUser($user);
        $headers = $this->headersForUser($user);

        $response = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/complete");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Insufficient permissions to complete inspections'
            ]);
    }

    public function test_schedule_conduct_complete_flow_updates_statuses(): void
    {
        $context = $this->actingAsWithPermissions([
            'inspection.schedule',
            'inspection.conduct',
            'inspection.complete',
        ]);
        $user = $context['user'];
        $inspection = $this->createInspectionForUser($user);
        $headers = $this->headersForUser($user);

        $scheduleResponse = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/schedule", [
                'inspection_date' => now()->addDays(2)->format('Y-m-d'),
                'notes' => 'Rescheduled due to weather',
                'results' => 'Pre-inspection check passed',
            ]);

        $scheduleResponse->assertStatus(200)
            ->assertJsonPath('data.inspection.status', 'scheduled')
            ->assertJsonPath('data.inspection.description', 'Rescheduled due to weather')
            ->assertJsonPath('data.inspection.findings', 'Pre-inspection check passed');

        $this->assertNotNull($scheduleResponse->json('data.inspection.scheduled_at'));

        $conductResponse = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/conduct", [
                'notes' => 'Inspection started',
                'results' => 'Inspection in progress',
            ]);

        $conductResponse->assertStatus(200)
            ->assertJsonPath('data.inspection.status', 'in_progress')
            ->assertJsonPath('data.inspection.findings', 'Inspection in progress');

        $this->assertNotNull($conductResponse->json('data.inspection.conducted_at'));

        $completeResponse = $this->withHeaders($headers)
            ->postJson("/api/zena/inspections/{$inspection->id}/complete", [
                'notes' => 'Inspection complete',
                'results' => 'All criteria met',
            ]);

        $completeResponse->assertStatus(200)
            ->assertJsonPath('data.inspection.status', 'completed')
            ->assertJsonPath('data.inspection.findings', 'All criteria met');

        $this->assertNotNull($completeResponse->json('data.inspection.completed_at'));
    }

    private function createInspectionForUser(User $user): QcInspection
    {
        $plan = $this->createPlanForUser($user);

        return QcInspection::create([
            'qc_plan_id' => $plan->id,
            'tenant_id' => $user->tenant_id,
            'title' => 'Flow inspection',
            'description' => 'Initial run',
            'status' => 'scheduled',
            'inspection_date' => now()->format('Y-m-d'),
            'inspector_id' => $user->id,
        ]);
    }

    private function createInspectionForOtherTenant(): QcInspection
    {
        $otherProject = Project::factory()->create();
        $plan = QcPlan::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        return QcInspection::create([
            'qc_plan_id' => $plan->id,
            'tenant_id' => $plan->tenant_id,
            'title' => 'Other tenant inspection',
            'description' => 'Blocked',
            'status' => 'scheduled',
            'inspection_date' => now()->format('Y-m-d'),
            'inspector_id' => User::factory()->create()->id,
        ]);
    }

    private function createPlanForUser(User $user): QcPlan
    {
        $project = Project::factory()->create([
            'tenant_id' => $user->tenant_id,
        ]);

        return QcPlan::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
        ]);
    }

    private function headersForUser(User $user): array
    {
        return $this->authHeader($user->createToken('tests')->plainTextToken);
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}
