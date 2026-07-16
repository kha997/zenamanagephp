<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorRfiUiTest extends TestCase
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
            ['rfi.view', 'rfi.create', 'rfi.respond', 'rfi.close']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'RFI UI Project',
            'code' => 'PRJ-RFI-001',
        ]);
    }

    private function makeRfi(array $overrides = []): Rfi
    {
        return Rfi::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Clarify column rebar detail',
            'subject' => 'Clarify column rebar detail',
            'description' => 'Please clarify rebar spacing at grid C-5.',
            'question' => 'Please clarify rebar spacing at grid C-5.',
            'rfi_number' => 'RFI-UI-001',
            'priority' => 'medium',
            'status' => 'open',
            'asked_by' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ], $overrides));
    }

    public function test_rfi_ui_full_flow_create_respond_close(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.rfis.index'), $headers)
            ->assertOk()
            ->assertSee('RFI')
            ->assertSee('Tạo RFI');

        $this->actingAs($this->user)
            ->get(route('operator.rfis.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin RFI');

        $create = $this->actingAs($this->user)
            ->post(route('operator.rfis.store'), [
                'project_id' => (string) $this->project->id,
                'title' => 'Clarify slab thickness',
                'description' => 'What is the slab thickness at zone B?',
                'priority' => 'high',
            ], $headers);

        $rfi = Rfi::query()->firstOrFail();
        $create->assertRedirect(route('operator.rfis.show', $rfi->id));
        $create->assertSessionHas('success', 'Tạo RFI thành công');
        $this->assertSame('open', (string) $rfi->status);
        $this->assertNotEmpty($rfi->rfi_number);

        $this->actingAs($this->user)
            ->get(route('operator.rfis.show', $rfi->id), $headers)
            ->assertOk()
            ->assertSee($rfi->rfi_number)
            ->assertSee('Gửi phản hồi');

        $respond = $this->actingAs($this->user)
            ->post(route('operator.rfis.respond', $rfi->id), [
                'response' => 'Slab thickness is 250mm per S-201 Rev.3.',
            ], $headers);

        $respond->assertRedirect(route('operator.rfis.show', $rfi->id));
        $respond->assertSessionHas('success', 'Đã gửi phản hồi');

        $rfi->refresh();
        $this->assertSame('answered', (string) $rfi->status);

        $close = $this->actingAs($this->user)
            ->post(route('operator.rfis.close', $rfi->id), [], $headers);

        $close->assertRedirect(route('operator.rfis.show', $rfi->id));
        $close->assertSessionHas('success', 'Đã đóng RFI');

        $rfi->refresh();
        $this->assertSame('closed', (string) $rfi->status);
    }

    public function test_rfi_index_filters_by_status_and_search(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->makeRfi(['rfi_number' => 'RFI-UI-OPEN', 'status' => 'open']);
        $this->makeRfi(['rfi_number' => 'RFI-UI-CLOSED', 'status' => 'closed', 'title' => 'Closed question']);

        $this->actingAs($this->user)
            ->get(route('operator.rfis.index', ['status' => 'open']), $headers)
            ->assertOk()
            ->assertSee('RFI-UI-OPEN')
            ->assertDontSee('RFI-UI-CLOSED');

        $this->actingAs($this->user)
            ->get(route('operator.rfis.index', ['search' => 'RFI-UI-CLOSED']), $headers)
            ->assertOk()
            ->assertSee('RFI-UI-CLOSED')
            ->assertDontSee('RFI-UI-OPEN');
    }

    public function test_rfi_pages_require_authentication(): void
    {
        $this->get(route('operator.rfis.index'))->assertRedirect();
        $this->get(route('operator.rfis.create'))->assertRedirect();
    }

    public function test_rfi_actions_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['rfi_viewer'], ['rfi.view']);
        $rfi = $this->makeRfi();

        $this->actingAs($viewer)
            ->get(route('operator.rfis.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.rfis.respond', $rfi->id), ['response' => 'Attempt'], $headers)
            ->assertForbidden();

        $rfi->refresh();
        $this->assertSame('open', (string) $rfi->status);
    }

    public function test_rfi_index_only_shows_current_tenant_rfis(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $foreignTenant = Tenant::factory()->create();
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
        ]);

        Rfi::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'title' => 'Foreign RFI',
            'subject' => 'Foreign RFI',
            'description' => 'Should not be visible.',
            'question' => 'Should not be visible.',
            'rfi_number' => 'RFI-FOREIGN-001',
            'priority' => 'low',
            'status' => 'open',
            'asked_by' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->makeRfi(['rfi_number' => 'RFI-LOCAL-001']);

        $this->actingAs($this->user)
            ->get(route('operator.rfis.index'), $headers)
            ->assertOk()
            ->assertSee('RFI-LOCAL-001')
            ->assertDontSee('RFI-FOREIGN-001');
    }
}
