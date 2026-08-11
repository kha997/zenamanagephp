<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['design-item.view', 'design-item.manage']);

        $this->projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson($this->route('index'), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenantA, [], ['no_perm'], []);

        $response = $this->getJson($this->route('index'), $this->headersFor($noPerm));

        $response->assertStatus(403);
    }

    public function test_can_create_and_list_design_items(): void
    {
        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Phoi canh mat tien phuong an 2',
            'item_type' => 'concept',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonPath('data.review_status', DesignItem::STATUS_DRAFT)
            ->assertJsonPath('data.item_type', 'concept');

        $this->assertDatabaseHas('design_items', [
            'name' => 'Phoi canh mat tien phuong an 2',
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $index = $this->getJson($this->route('index'), $this->headersFor($this->userA));
        $index->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_create_requires_manage_permission(): void
    {
        $viewOnly = $this->createTenantUser($this->tenantA, [], ['viewer'], ['design-item.view']);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Should be denied',
        ], $this->headersFor($viewOnly));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('design_items', ['name' => 'Should be denied']);
    }

    public function test_create_rejects_project_from_another_tenant(): void
    {
        $projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $projectB->id,
            'name' => 'Cross tenant project',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_design_items_are_tenant_isolated(): void
    {
        DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant A item',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('index'), $this->headersFor($this->userB));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_can_show_and_update_design_item(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Ban ve ky thuat tang 1',
        ], $this->headersFor($this->userA));

        $itemId = $create->json('data.id');

        $show = $this->getJson($this->route('show', ['id' => $itemId]), $this->headersFor($this->userA));
        $show->assertStatus(200)->assertJsonPath('data.name', 'Ban ve ky thuat tang 1');

        $update = $this->putJson($this->route('update', ['id' => $itemId]), [
            'name' => 'Ban ve ky thuat tang 1 revised',
            'item_type' => 'technical',
        ], $this->headersFor($this->userA));

        $update->assertStatus(200)->assertJsonPath('data.item_type', 'technical');
    }

    public function test_show_from_other_tenant_is_not_found(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant A only',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('show', ['id' => $item->id]), $this->headersFor($this->userB));

        $response->assertStatus(404);
    }

    public function test_update_does_not_accept_review_status(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Should not skip state machine',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $this->putJson($this->route('update', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $item->refresh();
        $this->assertSame(DesignItem::STATUS_DRAFT, (string) $item->review_status);
    }

    public function test_full_status_loop_including_late_revision_after_approval(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Full loop item',
        ], $this->headersFor($this->userA));
        $itemId = $create->json('data.id');

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);

        // sent_to_client requires due_to_client_at + an attached document first — set the date via update,
        // attach a document via a direct factory-less DB write (upload endpoint is tested in Task 6).
        DesignItem::query()->whereKey($itemId)->update(['due_to_client_at' => now()->addDays(3)->toDateString()]);
        \App\Models\Document::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'uploaded_by' => (string) $this->userA->id,
            'name' => 'concept.pdf',
            'original_name' => 'concept.pdf',
            'file_path' => 'design-items/test/concept.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'file_hash' => 'test-hash',
            'linked_entity_type' => \App\Models\Document::ENTITY_TYPE_DESIGN_ITEM,
            'linked_entity_id' => (string) $itemId,
        ]);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $revision = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Doi mau tuong ngoai that',
        ], $this->headersFor($this->userA));
        $revision->assertStatus(200)->assertJsonPath('data.client_feedback_notes', 'Doi mau tuong ngoai that');

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $approve = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_APPROVED,
            'approval_evidence' => 'zalo',
        ], $this->headersFor($this->userA));
        $approve->assertStatus(200)->assertJsonPath('data.approval_evidence', 'zalo');

        // Late change request after approval — must be allowed, not a dead end.
        $lateRevision = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Khach doi lai sau khi da duyet',
        ], $this->headersFor($this->userA));
        $lateRevision->assertStatus(200);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);
        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);
        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_APPROVED,
            'approval_evidence' => 'email',
        ], $this->headersFor($this->userA))->assertStatus(200);

        $final = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_FINAL,
        ], $this->headersFor($this->userA));
        $final->assertStatus(200)->assertJsonPath('data.review_status', DesignItem::STATUS_FINAL);

        $events = \App\Models\EventRecord::query()
            ->where('aggregate_type', 'design_item')
            ->where('aggregate_id', $itemId)
            ->orderBy('occurred_at')
            ->get();

        $this->assertGreaterThanOrEqual(9, $events->count(), 'every status change must produce an EventRecord');
        $this->assertSame(DesignItem::STATUS_DRAFT, $events->first()->payload['from']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Invalid transition target',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
        $item->refresh();
        $this->assertSame(DesignItem::STATUS_DRAFT, (string) $item->review_status);
    }

    public function test_sent_to_client_requires_due_date_and_attachment(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Missing prerequisites',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
            'created_by' => (string) $this->userA->id,
        ]);

        // No due_to_client_at, no attachment yet.
        $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(422);

        $item->update(['due_to_client_at' => now()->addDay()->toDateString()]);

        // Due date set, but still no attachment.
        $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(422);
    }

    public function test_revision_requested_requires_feedback_notes(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'No feedback provided',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_approved_requires_evidence(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'No evidence provided',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_can_upload_and_list_document_versions(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Upload target',
        ], $this->headersFor($this->userA));
        $itemId = $create->json('data.id');

        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('concept-v1.pdf', 50, 'application/pdf');

        $upload1 = $this->post($this->route('documents.store', ['id' => $itemId]), [
            'file' => $file,
        ], $this->headersFor($this->userA));

        $upload1->assertStatus(201)->assertJsonPath('data.version_number', 1);

        $file2 = \Illuminate\Http\UploadedFile::fake()->create('concept-v2.pdf', 60, 'application/pdf');

        $upload2 = $this->post($this->route('documents.store', ['id' => $itemId]), [
            'file' => $file2,
            'comment' => 'Cap nhat theo phan hoi khach',
        ], $this->headersFor($this->userA));

        $upload2->assertStatus(201)->assertJsonPath('data.version_number', 2);

        $list = $this->getJson($this->route('documents.index', ['id' => $itemId]), $this->headersFor($this->userA));
        $list->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_design_item_first_upload_uses_canonical_creation_boundary_and_draft_not_submitted_state(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Canonical upload target',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->post($this->route('documents.store', ['id' => $item->id]), [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('canonical-design.pdf', 20, 'application/pdf'),
        ], $this->headersFor($this->userA))->assertCreated();

        $this->assertDatabaseHas('documents', [
            'tenant_id' => (string) $this->tenantA->id,
            'linked_entity_type' => Document::ENTITY_TYPE_DESIGN_ITEM,
            'linked_entity_id' => (string) $item->id,
            'status' => 'draft',
            'lifecycle_status' => 'draft',
            'approval_status' => 'not-submitted',
        ]);
    }

    public function test_design_item_upload_cannot_create_or_find_a_cross_tenant_document(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant-scoped upload target',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);
        $foreign = Document::factory()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'linked_entity_type' => Document::ENTITY_TYPE_DESIGN_ITEM,
            'linked_entity_id' => (string) $item->id,
        ]);
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->post($this->route('documents.store', ['id' => $item->id]), [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('tenant-scoped.pdf', 20, 'application/pdf'),
        ], $this->headersFor($this->userA))->assertCreated();

        $this->assertDatabaseHas('documents', ['id' => $foreign->id, 'tenant_id' => (string) $this->tenantB->id]);
        $this->assertDatabaseHas('documents', ['tenant_id' => (string) $this->tenantA->id, 'linked_entity_id' => (string) $item->id]);
    }

    public function test_upload_requires_manage_permission(): void
    {
        $viewOnly = $this->createTenantUser($this->tenantA, [], ['viewer'], ['design-item.view']);

        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'RBAC upload target',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf');

        $response = $this->post($this->route('documents.store', ['id' => $item->id]), [
            'file' => $file,
        ], $this->headersFor($viewOnly));

        $response->assertStatus(403);
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.design-items.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('design-item-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
