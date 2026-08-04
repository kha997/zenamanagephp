<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentApprovalsPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        $uploader = User::factory()->create(['tenant_id' => $this->tenant->id]);

        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $uploader->id,
            'created_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'status' => 'submitted',
            'metadata' => ['status' => 'submitted'],
        ], $overrides));
    }

    public function test_approvals_page_loads_without_is_active_error(): void
    {
        $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
    }

    public function test_approvals_page_without_document_approve_permission_is_blocked(): void
    {
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertStatus(302);
    }

    public function test_approvals_page_does_not_leak_exception_message(): void
    {
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $this->partialMock(\App\Http\Controllers\Web\DocumentController::class, function ($mock) {
            $mock->shouldReceive('decisionUsersFor')->andThrow(new \RuntimeException('secret-internal-db-detail-should-never-leak'));
        });

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertDontSee('secret-internal-db-detail-should-never-leak');
    }

    public function test_approved_document_shows_decision_actor_and_note_in_list(): void
    {
        $approver = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nguyễn Văn Duyệt']);
        $this->makeDocument([
            'status' => 'approved',
            'metadata' => [
                'status' => 'approved',
                'decision' => 'approved',
                'decision_by' => (string) $approver->id,
                'decision_at' => now()->toISOString(),
                'decision_note' => 'Đạt yêu cầu kỹ thuật',
            ],
        ]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertSee('Nguyễn Văn Duyệt');
        $response->assertSee('Đạt yêu cầu kỹ thuật');
    }

    public function test_submitted_document_shows_approve_and_reject_actions(): void
    {
        $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertSee(route('app.documents.workflow.approve', ['document' => Document::first()->id]), false);
        $response->assertSee(route('app.documents.workflow.reject', ['document' => Document::first()->id]), false);
    }

    public function test_draft_document_shows_no_decision_actions(): void
    {
        $this->makeDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'));

        $response->assertOk();
        $response->assertDontSee(route('app.documents.workflow.approve', ['document' => Document::first()->id]), false);
    }

    public function test_decision_actor_preload_does_not_grow_query_count_with_document_count(): void
    {
        $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
        for ($i = 0; $i < 5; $i++) {
            $this->makeDocument([
                'status' => 'approved',
                'metadata' => ['status' => 'approved', 'decision_by' => (string) $approver->id, 'decision_at' => now()->toISOString()],
            ]);
        }
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'))
            ->assertOk();
        $queryCountFor5 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $this->makeDocument([
                'status' => 'approved',
                'metadata' => ['status' => 'approved', 'decision_by' => (string) $approver->id, 'decision_at' => now()->toISOString()],
            ]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->get(route('app.documents.approvals'))
            ->assertOk();
        $queryCountFor10 = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $this->assertSame($queryCountFor5, $queryCountFor10, 'Số query không được tăng theo số document (N+1).');
    }
}
