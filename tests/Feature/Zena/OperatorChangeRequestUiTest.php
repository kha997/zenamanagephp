<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorChangeRequestUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            [
                'change-request.view',
                'change-request.create',
                'change-request.submit',
                'change-request.approve',
                'change-request.reject',
            ]
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'CR UI Project',
            'code' => 'PRJ-CR-001',
        ]);
    }

    public function test_change_request_ui_full_flow_create_submit_approve(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.change-requests.index'), $headers)
            ->assertOk()
            ->assertSee('Yêu cầu thay đổi')
            ->assertSee('Tạo yêu cầu');

        $this->actingAs($this->user)
            ->get(route('operator.change-requests.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin yêu cầu thay đổi');

        $create = $this->actingAs($this->user)
            ->post(route('operator.change-requests.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Extend basement waterproofing scope',
                'description' => 'Additional waterproofing at lift pits.',
                'change_type' => 'scope',
                'impact_analysis' => 'Adds 3 days and material cost.',
                'priority' => 'high',
                'justification' => 'Site water table higher than survey.',
                'cost_impact' => 15000,
                'schedule_impact_days' => 3,
            ], $headers);

        $changeRequest = ChangeRequest::query()->firstOrFail();
        $create->assertRedirect(route('operator.change-requests.show', $changeRequest->id));
        $create->assertSessionHas('success', 'Tạo yêu cầu thay đổi thành công');
        $this->assertSame('draft', (string) $changeRequest->status);

        $submit = $this->actingAs($this->user)
            ->post(route('operator.change-requests.submit', $changeRequest->id), [], $headers);

        $submit->assertRedirect(route('operator.change-requests.show', $changeRequest->id));
        $changeRequest->refresh();
        $this->assertSame('submitted', (string) $changeRequest->status);

        $approve = $this->actingAs($this->user)
            ->post(route('operator.change-requests.approve', $changeRequest->id), [], $headers);

        $approve->assertRedirect(route('operator.change-requests.show', $changeRequest->id));
        $approve->assertSessionHas('success', 'Đã phê duyệt');

        $changeRequest->refresh();
        $this->assertSame('approved', (string) $changeRequest->status);
    }

    public function test_change_request_reject_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $changeRequest = ChangeRequest::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Rejected change',
            'description' => 'Test rejection.',
            'change_type' => 'cost',
            'priority' => 'medium',
            'status' => ChangeRequest::STATUS_SUBMITTED,
            'requested_by' => (string) $this->user->id,
            'change_number' => 'CR-UI-001',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.change-requests.show', $changeRequest->id), $headers)
            ->assertOk();

        $reject = $this->actingAs($this->user)
            ->post(route('operator.change-requests.reject', $changeRequest->id), [
                'rejection_reason' => 'Budget not available this quarter.',
            ], $headers);

        $reject->assertRedirect(route('operator.change-requests.show', $changeRequest->id));
        $reject->assertSessionHas('success', 'Đã từ chối');

        $changeRequest->refresh();
        $this->assertSame('rejected', (string) $changeRequest->status);
    }

    public function test_change_request_pages_require_authentication(): void
    {
        $this->get(route('operator.change-requests.index'))->assertRedirect();
    }

    public function test_change_request_actions_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['cr_viewer'], ['change-request.view']);

        $changeRequest = ChangeRequest::create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Locked change',
            'description' => 'Permission test.',
            'change_type' => 'scope',
            'priority' => 'low',
            'status' => ChangeRequest::STATUS_SUBMITTED,
            'requested_by' => (string) $this->user->id,
            'change_number' => 'CR-UI-002',
            'requested_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('operator.change-requests.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.change-requests.approve', $changeRequest->id), [], $headers)
            ->assertForbidden();

        $changeRequest->refresh();
        $this->assertSame('submitted', (string) $changeRequest->status);
    }
}
