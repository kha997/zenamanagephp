<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorSubmittalUiTest extends TestCase
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
            ['submittal.view', 'submittal.create', 'submittal.submit', 'submittal.approve', 'submittal.reject']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Submittal UI Project',
            'code' => 'PRJ-SUB-001',
        ]);
    }

    public function test_submittal_ui_full_flow_create_submit_approve(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.submittals.index'), $headers)
            ->assertOk()
            ->assertSee('Submittals')
            ->assertSee('Tạo submittal');

        $this->actingAs($this->user)
            ->get(route('operator.submittals.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin submittal');

        $create = $this->actingAs($this->user)
            ->post(route('operator.submittals.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Concrete mix design',
                'description' => 'Mix design for grade 40 concrete.',
                'submittal_type' => 'material_sample',
            ], $headers);

        $submittal = Submittal::query()->firstOrFail();
        $create->assertRedirect(route('operator.submittals.show', $submittal->id));
        $create->assertSessionHas('success', 'Tạo submittal thành công');
        $this->assertSame('draft', (string) $submittal->status);

        $submit = $this->actingAs($this->user)
            ->post(route('operator.submittals.submit', $submittal->id), [], $headers);

        $submit->assertRedirect(route('operator.submittals.show', $submittal->id));
        $submittal->refresh();
        $this->assertSame('submitted', (string) $submittal->status);

        $approve = $this->actingAs($this->user)
            ->post(route('operator.submittals.approve', $submittal->id), [
                'approval_comments' => 'Approved per spec.',
            ], $headers);

        $approve->assertRedirect(route('operator.submittals.show', $submittal->id));
        $approve->assertSessionHas('success', 'Đã phê duyệt');

        $submittal->refresh();
        $this->assertSame('approved', (string) $submittal->status);
    }

    public function test_submittal_reject_requires_reason_and_updates_status(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $submittal = Submittal::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Steel shop drawing',
            'description' => 'Level 2 framing.',
            'submittal_type' => 'shop_drawing',
            'status' => 'submitted',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-UI-001',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.submittals.show', $submittal->id), $headers)
            ->assertOk();

        $reject = $this->actingAs($this->user)
            ->post(route('operator.submittals.reject', $submittal->id), [
                'rejection_reason' => 'Missing weld details.',
            ], $headers);

        $reject->assertRedirect(route('operator.submittals.show', $submittal->id));
        $reject->assertSessionHas('success', 'Đã từ chối');

        $submittal->refresh();
        $this->assertSame('rejected', (string) $submittal->status);
    }

    public function test_submittal_pages_require_authentication(): void
    {
        $this->get(route('operator.submittals.index'))->assertRedirect();
    }

    public function test_submittal_actions_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);

        $submittal = Submittal::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Locked submittal',
            'description' => 'Permission test.',
            'submittal_type' => 'other',
            'status' => 'submitted',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-UI-002',
        ]);

        $this->actingAs($viewer)
            ->get(route('operator.submittals.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.submittals.approve', $submittal->id), [], $headers)
            ->assertForbidden();

        $submittal->refresh();
        $this->assertSame('submitted', (string) $submittal->status);
    }
}
