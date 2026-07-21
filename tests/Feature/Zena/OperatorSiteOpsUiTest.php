<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\EventRecord;
use App\Models\Project;
use App\Models\SiteDiary;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorSiteOpsUiTest extends TestCase
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
                'site_diary.view',
                'site_diary.create',
                'site_diary.approve',
                'event-record.view',
            ]
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Site Ops Project',
            'code' => 'PRJ-SO-001',
        ]);
    }

    public function test_site_diary_pages_load_and_full_workflow_executes(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.site-diaries.index'), $headers)
            ->assertOk()
            ->assertSee('Nhật ký công trường');

        $this->actingAs($this->user)
            ->get(route('operator.site-diaries.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin nhật ký');

        $store = $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.site-diaries.store'), [
                'project_id' => (string) $this->project->id,
                'diary_date' => '2026-07-08',
                'weather' => 'Nắng',
                'manpower_count' => 25,
                'work_performed' => 'Lắp dựng cốp pha tầng 5',
            ]);

        $store->assertRedirect(route('operator.site-diaries.index'));

        $diary = SiteDiary::query()->first();
        $this->assertNotNull($diary);
        $this->assertSame('draft', (string) $diary->status);

        $this->actingAs($this->user)
            ->get(route('operator.site-diaries.show', $diary->id), $headers)
            ->assertOk()
            ->assertSee('Lắp dựng cốp pha tầng 5');

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.site-diaries.submit', $diary->id))
            ->assertRedirect(route('operator.site-diaries.index'));

        $this->assertSame('submitted', (string) $diary->fresh()->status);

        $this->actingAs($this->user)
            ->withHeaders($headers)
            ->post(route('operator.site-diaries.approve', $diary->id))
            ->assertRedirect(route('operator.site-diaries.index'));

        $this->assertSame('approved', (string) $diary->fresh()->status);
    }

    public function test_create_page_exposes_autofill_from_most_recent_diary_per_project(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'diary_number' => 'SD-OLD-001',
            'diary_date' => '2026-07-01',
            'weather' => 'Mưa',
            'temperature' => '24-26°C',
            'manpower_count' => 10,
            'work_performed' => 'Đổ móng',
            'equipment_used' => 'Máy trộn bê tông',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'diary_number' => 'SD-NEW-001',
            'diary_date' => '2026-07-08',
            'weather' => 'Nắng',
            'temperature' => '30-34°C',
            'manpower_count' => 25,
            'work_performed' => 'Lắp dựng cốp pha',
            'equipment_used' => 'Cần cẩu tháp',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $this->user->id,
        ]);

        $projectWithoutDiary = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Project Without Diary',
            'code' => 'PRJ-ND-001',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'name' => 'Foreign Project',
            'code' => 'PRJ-FT-001',
        ]);
        $foreignUser = $this->createTenantUser($foreignTenant, [], ['admin'], ['site_diary.view']);
        SiteDiary::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'diary_number' => 'SD-FOREIGN-001',
            'diary_date' => '2026-07-08',
            'weather' => 'Foreign weather',
            'work_performed' => 'Foreign work',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $foreignUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('operator.site-diaries.create'), $headers)
            ->assertOk();

        $autofill = $response->viewData('autofillByProject');

        $this->assertSame([
            'weather' => 'Nắng',
            'temperature' => '30-34°C',
            'manpower_count' => 25,
            'equipment_used' => 'Cần cẩu tháp',
        ], $autofill[(string) $this->project->id]);

        $this->assertArrayNotHasKey((string) $projectWithoutDiary->id, $autofill);
        $this->assertArrayNotHasKey((string) $foreignProject->id, $autofill);
    }

    public function test_activity_feed_page_lists_tenant_events(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        EventRecord::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'aggregate_type' => 'material_request',
            'aggregate_id' => 'MR-TEST-001',
            'event_key' => 'material_request.approved',
            'actor_user_id' => (string) $this->user->id,
            'payload' => ['status' => 'approved'],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.activity-feed.index'), $headers)
            ->assertOk()
            ->assertSee('Nhật ký hoạt động')
            ->assertSee('material_request.approved');
    }

    public function test_global_search_finds_records_across_modules(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VENDOR-SEARCH-01',
            'name' => 'Công ty Thép Miền Nam',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.search.index', ['q' => 'Thép Miền Nam']), $headers)
            ->assertOk()
            ->assertSee('Công ty Thép Miền Nam')
            ->assertSee('Nhà cung cấp');
    }

    public function test_global_search_does_not_leak_foreign_tenant_records(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $foreignTenant = Tenant::factory()->create();
        Vendor::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'code' => 'VENDOR-FOREIGN-01',
            'name' => 'Nhà cung cấp tenant khác',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.search.index', ['q' => 'tenant khác']), $headers)
            ->assertOk()
            ->assertDontSee('Nhà cung cấp tenant khác');
    }
}
