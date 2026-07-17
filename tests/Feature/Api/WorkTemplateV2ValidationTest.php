<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class WorkTemplateV2ValidationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkTemplateV2Routes();
    }

    public function test_rejects_duplicate_phase_and_task_orders_and_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.edit_draft']);

        $payload = $this->workTemplateV2Payload('WT-V2-INVALID');
        $payload['phases'][] = [
            'key' => 'design',
            'name' => 'Duplicate Design',
            'order' => 1,
            'tasks' => [
                [
                    'key' => 'submit-drawings',
                    'name' => 'Duplicate Task',
                    'task_type' => 'standard',
                    'order' => 1,
                ],
            ],
        ];

        $response = $this->postJson($this->workTemplateRoute('store'), $payload, $this->authHeaders($user));

        $response->assertStatus(422);
        $errors = $response->json('error.details.data');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('phases.1.key', $errors);
        $this->assertArrayHasKey('phases.1.order', $errors);
    }

    public function test_rejects_invalid_assignment_and_trigger_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.edit_draft']);

        $payload = $this->workTemplateV2Payload('WT-V2-INVALID-ENUMS');
        $payload['phases'][0]['tasks'][0]['assignments'][0]['assignment_type'] = 'owner';
        $payload['phases'][0]['tasks'][0]['assignments'][1]['approval_order'] = null;
        $payload['phases'][0]['tasks'][0]['triggers'][0]['event'] = 'task.unknown';
        $payload['phases'][0]['tasks'][0]['triggers'][0]['action'] = 'send_magic';

        $response = $this->postJson($this->workTemplateRoute('store'), $payload, $this->authHeaders($user));

        $response->assertStatus(422);
        $errors = $response->json('error.details.data');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('phases.0.tasks.0.assignments.0.assignment_type', $errors);
        $this->assertArrayHasKey('phases.0.tasks.0.assignments.1.approval_order', $errors);
        $this->assertArrayHasKey('phases.0.tasks.0.triggers.0.event', $errors);
        $this->assertArrayHasKey('phases.0.tasks.0.triggers.0.action', $errors);
    }
}
