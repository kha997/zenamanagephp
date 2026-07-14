<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemRevisionCycleTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private DesignItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Concept mặt đứng',
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'due_to_client_at' => now()->addDays(3)->toDateString(),
            'created_by' => (string) $this->user->id,
        ]);

        Document::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'uploaded_by' => (string) $this->user->id,
            'name' => 'concept.pdf',
            'original_name' => 'concept.pdf',
            'file_path' => 'design-items/test/concept.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'file_hash' => 'test-hash',
            'linked_entity_type' => Document::ENTITY_TYPE_DESIGN_ITEM,
            'linked_entity_id' => (string) $this->item->id,
        ]);
    }

    private function headersFor(User $user): array
    {
        $token = $user->createToken('design-item-revision-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function postStatus(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            route('api.zena.design-items.status', ['id' => (string) $this->item->id], false),
            $payload,
            $this->headersFor($this->user)
        );
    }

    public function test_revision_request_creates_numbered_history_and_increments_counter(): void
    {
        $this->postStatus([
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Đổi vật liệu mặt tiền',
        ])->assertStatus(200);

        $this->item->refresh();
        $this->assertSame(1, (int) $this->item->revision_count);

        $rev1 = $this->item->revisions()->first();
        $this->assertSame(1, (int) $rev1->revision_no);
        $this->assertSame('Đổi vật liệu mặt tiền', $rev1->client_feedback);
        $this->assertNotNull($rev1->requested_at);
        $this->assertNull($rev1->resolved_at);

        // Đưa lại vòng nội bộ → revision 1 được resolve
        $this->postStatus(['review_status' => DesignItem::STATUS_INTERNAL_REVIEW])->assertStatus(200);

        $this->assertNotNull($rev1->fresh()->resolved_at);
    }

    public function test_second_revision_gets_number_two(): void
    {
        $this->postStatus([
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Lần 1',
        ])->assertStatus(200);
        $this->postStatus(['review_status' => DesignItem::STATUS_INTERNAL_REVIEW])->assertStatus(200);
        $this->postStatus(['review_status' => DesignItem::STATUS_SENT_TO_CLIENT])->assertStatus(200);
        $this->postStatus([
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Lần 2',
        ])->assertStatus(200);

        $this->item->refresh();
        $this->assertSame(2, (int) $this->item->revision_count);
        $this->assertSame([1, 2], $this->item->revisions()->pluck('revision_no')->all());
        $this->assertNotNull($this->item->revisions()->where('revision_no', 1)->first()->resolved_at);
        $this->assertNull($this->item->revisions()->where('revision_no', 2)->first()->resolved_at);
    }
}
