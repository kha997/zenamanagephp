<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalUpdatePageTest extends TestCase
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
            'name' => 'Update Test Project',
            'code' => 'PRJ-UPD-001',
        ]);
    }

    private function makeSubmittal(array $overrides = []): Submittal
    {
        return Submittal::query()->create(array_merge([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Original title',
            'description' => 'Original description',
            'submittal_type' => 'shop_drawing',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-UPD-' . Str::random(4),
        ], $overrides));
    }

    public function test_update_saves_allowed_fields_while_draft(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'New title',
            'description' => 'New description',
            'submittal_type' => 'material_sample',
            'specification_section' => 'Sec 5',
            'due_date' => '2026-08-01',
        ], $headers);

        $response->assertRedirect(route('operator.submittals.show', $submittal->id));
        $response->assertSessionHas('success');

        $submittal->refresh();
        $this->assertSame('New title', $submittal->title);
        $this->assertSame('New description', $submittal->description);
        $this->assertSame('material_sample', $submittal->submittal_type);
    }

    public function test_update_ignores_immutable_fields_even_if_submitted(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'New title',
            'status' => 'approved',
            'package_no' => 'HACKED-PKG',
            'submittal_number' => 'HACKED-NUM',
        ], $headers);

        $submittal->refresh();
        $this->assertSame('draft', $submittal->status);
        $this->assertNotSame('HACKED-PKG', $submittal->package_no);
        $this->assertNotSame('HACKED-NUM', $submittal->submittal_number);
    }

    public function test_update_blocked_when_status_is_submitted(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'submitted']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Should not apply',
        ], $headers);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }

    public function test_update_blocked_when_status_is_approved(): void
    {
        $submittal = $this->makeSubmittal(['status' => 'approved']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Should not apply',
        ], $headers);

        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }

    public function test_validation_failure_returns_old_input_in_named_bag(): void
    {
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'submittal_type' => 'not-a-real-type',
        ], $headers);

        $response->assertSessionHasErrorsIn('submittalUpdate', ['submittal_type']);
        $response->assertSessionHasInput('submittal_type', 'not-a-real-type');
    }

    public function test_vendor_validation_only_fires_when_contractor_changes(): void
    {
        Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VD-001',
            'name' => 'Renamed Away Vendor',
            'is_active' => true,
        ]);
        $submittal = $this->makeSubmittal(['contractor' => 'A Vendor That No Longer Exists By This Name']);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        // Editing only the title, leaving the stale contractor value untouched, must succeed.
        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Edited title only',
            'contractor' => 'A Vendor That No Longer Exists By This Name',
        ], $headers);

        $response->assertSessionDoesntHaveErrors('submittalUpdate');
        $submittal->refresh();
        $this->assertSame('Edited title only', $submittal->title);
    }

    public function test_vendor_validation_fires_when_contractor_actually_changes_to_unknown_name(): void
    {
        $submittal = $this->makeSubmittal(['contractor' => null]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'x',
            'contractor' => 'A Vendor That Was Never Created',
        ], $headers);

        $response->assertSessionHasErrorsIn('submittalUpdate', ['contractor']);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSubmittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Other tenant submittal',
            'description' => 'x',
            'submittal_type' => 'other',
            'status' => 'draft',
            'submitted_by' => (string) $this->user->id,
            'submittal_number' => 'SUB-OTHER-001',
        ]);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($this->user)->get(route('operator.submittals.index'), $headers);

        $response = $this->actingAs($this->user)->put(route('operator.submittals.update', $otherSubmittal->id), [
            'title' => 'x',
        ], $headers);

        $response->assertStatus(404);
    }

    public function test_missing_submittal_edit_permission_returns_403(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['submittal_viewer'], ['submittal.view']);
        $submittal = $this->makeSubmittal();
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Establish session so csrf_token() is available for the subsequent PUT.
        $this->actingAs($viewer)->get(route('operator.submittals.show', $submittal->id), $headers);

        $response = $this->actingAs($viewer)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'x',
        ], $headers);

        // The `rbac:submittal.edit` route middleware runs before the controller
        // and, per PR#220, friendly-redirects (302 + flashed `error`) plain
        // browser navigation instead of a raw 403 — matches the established
        // pattern in OperatorSubmittalUiTest::test_submittal_actions_denied_without_permission.
        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $submittal->refresh();
        $this->assertSame('Original title', $submittal->title);
    }
}
