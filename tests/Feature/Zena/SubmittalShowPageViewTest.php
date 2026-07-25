<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalShowPageViewTest extends TestCase
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
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Show View Test Project',
            'code' => 'PRJ-SV-001',
        ]);
    }

    private function makeSubmittal(array $overrides = []): Submittal
    {
        return Submittal::query()->create(array_merge([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'A title',
            'description' => 'A description',
            'submittal_type' => 'shop_drawing',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SV-' . Str::random(4),
        ], $overrides));
    }

    public function test_draft_shows_edit_card_and_submit_button_not_resubmit(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Sửa nội dung');
        $response->assertSee('Gửi duyệt');
        $response->assertDontSee('Mở lại để sửa');
        $response->assertDontSee('Tóm tắt thay đổi');
    }

    public function test_rejected_shows_reopen_button_and_rejection_reason_with_revision_number(): void
    {
        $submittal = $this->makeSubmittal([
            'status' => 'rejected',
            'current_revision_no' => 1,
            'rejected_by' => (string) $this->user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Missing calcs',
            'rejection_comments' => 'Please redo section 3',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Mở lại để sửa');
        $response->assertSee('Lần nộp #1 bị từ chối');
        $response->assertSee('Missing calcs');
        $response->assertSee('Please redo section 3');
        $response->assertSee($this->user->name);
        $response->assertDontSee('Sửa nội dung');
    }

    public function test_revising_shows_edit_card_and_resubmit_form_with_revision_summary(): void
    {
        $submittal = $this->makeSubmittal([
            'status' => 'revising',
            'current_revision_no' => 1,
            'rejection_reason' => 'Missing calcs',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertSee('Sửa nội dung');
        $response->assertSee('Tóm tắt thay đổi');
        $response->assertSee('Gửi lại');
        $response->assertSee('Lần nộp #1 bị từ chối');
    }

    public function test_submitted_shows_neither_edit_nor_reopen_nor_resubmit(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'submitted']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $response = $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response->assertOk();
        $response->assertDontSee('Sửa nội dung');
        $response->assertDontSee('Mở lại để sửa');
        $response->assertDontSee('Tóm tắt thay đổi');
    }

    public function test_viewer_without_edit_or_submit_permission_sees_no_action_buttons(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeSubmittal(['status' => 'draft']);
        $rejected = $this->makeSubmittal(['status' => 'rejected', 'rejection_reason' => 'x']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $draftResponse = $this->actingAs($viewer)->get(route('operator.submittals.show', $submittal->id), $headers);
        $draftResponse->assertDontSee('Sửa nội dung');
        $draftResponse->assertDontSee('Gửi duyệt');

        $rejectedResponse = $this->actingAs($viewer)->get(route('operator.submittals.show', $rejected->id), $headers);
        $rejectedResponse->assertDontSee('Mở lại để sửa');
    }
}
