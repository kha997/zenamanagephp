<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class WorkTemplateRequiredDocumentTypesTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['template.view', 'template.edit_draft']);
    }

    private function authHeaders(): array
    {
        $token = $this->user->createToken('required-doc-types-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_store_persists_required_document_types_on_step(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-1',
            'name' => 'Template With Document Requirements',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'issue-drawings',
                    'name' => 'Issue Drawings',
                    'type' => 'deliverable',
                    'order' => 1,
                    'required_document_types' => ['drawing', 'specification'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertCreated();

        $step = WorkTemplateStep::query()->where('step_key', 'issue-drawings')->firstOrFail();
        $this->assertSame(['drawing', 'specification'], $step->required_document_types);
    }

    public function test_store_rejects_invalid_document_type(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-2',
            'name' => 'Template With Bad Document Type',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'issue-drawings',
                    'name' => 'Issue Drawings',
                    'type' => 'deliverable',
                    'order' => 1,
                    'required_document_types' => ['not_a_real_type'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertStatus(422);
        $this->assertDatabaseMissing('work_templates', ['code' => 'WT-DOC-CHECK-2']);
    }

    public function test_store_allows_step_without_required_document_types(): void
    {
        $response = $this->postJson(route('api.zena.work-templates.store'), [
            'code' => 'WT-DOC-CHECK-3',
            'name' => 'Template Without Document Requirements',
            'status' => 'draft',
            'steps' => [
                [
                    'key' => 'plain-step',
                    'name' => 'Plain Step',
                    'type' => 'task',
                    'order' => 1,
                ],
            ],
        ], $this->authHeaders());

        $response->assertCreated();

        $step = WorkTemplateStep::query()->where('step_key', 'plain-step')->firstOrFail();
        $this->assertNull($step->required_document_types);
    }

    public function test_update_replaces_required_document_types(): void
    {
        $template = WorkTemplate::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'WT-DOC-CHECK-4',
            'status' => 'draft',
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);

        WorkTemplateVersion::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-initial',
            'content_json' => ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => false,
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);

        $response = $this->putJson(route('api.zena.work-templates.update', $template->id), [
            'steps' => [
                [
                    'key' => 'final-review',
                    'name' => 'Final Review',
                    'type' => 'approval',
                    'order' => 1,
                    'required_document_types' => ['report'],
                ],
            ],
        ], $this->authHeaders());

        $response->assertOk();

        $step = WorkTemplateStep::query()->where('step_key', 'final-review')->firstOrFail();
        $this->assertSame(['report'], $step->required_document_types);
    }
}
