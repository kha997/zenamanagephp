<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalStartRevisionPageTest extends TestCase
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
            'name' => 'Start Revision Test Project',
            'code' => 'PRJ-SR-001',
        ]);
    }

    private function makeRejectedSubmittalWithRevision(): Submittal
    {
        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Rejected title',
            'description' => 'Rejected description',
            'submittal_type' => 'shop_drawing',
            'status' => 'rejected',
            'current_revision_no' => 1,
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-' . Str::random(4),
            'rejected_by' => (string) $this->user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Missing details',
        ]);

        SubmittalRevision::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Rejected title',
            'description' => 'Rejected description',
            'submitted_by' => (string) $this->user->id,
            'submitted_at' => now(),
            'decision' => 'rejected',
            'decided_by' => (string) $this->user->id,
            'decided_at' => now(),
            'decision_comments' => 'Missing details',
            'created_at' => now(),
        ]);

        return $submittal;
    }

    public function test_start_revision_moves_status_to_revising_without_creating_new_revision(): void
    {
        $submittal = $this->makeRejectedSubmittalWithRevision();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        $response->assertRedirect(route('operator.submittals.show', $submittal->id));
        $response->assertSessionHas('success');

        $submittal->refresh();
        $this->assertSame('revising', $submittal->status);
        $this->assertSame(1, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());
    }

    public function test_start_revision_blocked_from_draft(): void
    {
        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Draft',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-DRAFT',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('draft', $submittal->status);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSubmittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Other',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'rejected',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-SR-OTHER',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.submittals.index'), $headers);

        $response = $this->actingAs($this->user)->post(route('operator.submittals.start-revision', $otherSubmittal->id), [], $headers);

        $response->assertStatus(404);
    }

    public function test_missing_permission_returns_403(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeRejectedSubmittalWithRevision();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewer)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($viewer)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers);

        // The `rbac:submittal.submit` route middleware runs before the controller
        // and, per PR#220, friendly-redirects (302 + flashed `error`) plain
        // browser navigation instead of a raw 403 — matches the established
        // pattern in SubmittalUpdatePageTest::test_missing_submittal_edit_permission_returns_403.
        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('rejected', $submittal->status);
    }
}
