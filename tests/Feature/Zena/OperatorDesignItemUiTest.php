<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorDesignItemUiTest extends TestCase
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
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_design_item_ui_full_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk()
            ->assertSee('Công việc thiết kế');

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk()
            ->assertSee('Tạo công việc thiết kế mới');

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Phoi canh mat tien',
                'item_type' => 'concept',
            ], $headers);

        $item = DesignItem::query()->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $create->assertSessionHas('success', 'Đã tạo công việc thiết kế');

        $this->actingAs($this->user)
            ->get(route('operator.design-items.show', $item->id), $headers)
            ->assertOk()
            ->assertSee('Phoi canh mat tien');

        $toInternalReview = $this->actingAs($this->user)
            ->post(route('operator.design-items.status', $item->id), [
                'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
            ], $headers);
        $toInternalReview->assertRedirect();
        $toInternalReview->assertSessionHas('success', 'Đã cập nhật trạng thái');

        $item->update(['due_to_client_at' => now()->addDays(2)->toDateString()]);

        Storage::fake('local');
        $upload = $this->actingAs($this->user)
            ->post(route('operator.design-items.documents.store', $item->id), [
                'file' => UploadedFile::fake()->create('concept.pdf', 40, 'application/pdf'),
            ], $headers);
        $upload->assertRedirect();
        $upload->assertSessionHas('success', 'Đã tải file lên');

        $toSentToClient = $this->actingAs($this->user)
            ->post(route('operator.design-items.status', $item->id), [
                'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            ], $headers);
        $toSentToClient->assertSessionHas('success', 'Đã cập nhật trạng thái');

        $item->refresh();
        $this->assertSame(DesignItem::STATUS_SENT_TO_CLIENT, (string) $item->review_status);
    }

    public function test_design_item_creation_accepts_description(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Phoi canh san vuon',
                'item_type' => 'concept',
                'description' => 'Mo ta duoc nhap thu cong hoac tu AI.',
            ], $headers);

        $item = DesignItem::query()->where('name', 'Phoi canh san vuon')->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $this->assertSame('Mo ta duoc nhap thu cong hoac tu AI.', $item->description);
    }

    public function test_design_item_creation_allows_blank_description(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk();

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Khong co mo ta',
                'item_type' => 'concept',
            ], $headers);

        $item = DesignItem::query()->where('name', 'Khong co mo ta')->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $this->assertNull($item->description);
    }

    public function test_design_items_create_page_shows_ai_suggest_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk()
            ->assertSee('Gợi ý AI')
            ->assertSee('description', false);
    }

    public function test_design_item_pages_require_authentication(): void
    {
        $this->get(route('operator.design-items.index'))->assertRedirect();
    }

    public function test_design_item_actions_denied_without_manage_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['design-item.view']);

        $this->actingAs($viewer)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Should be denied',
            ], $headers)
            ->assertStatus(302);

        $this->assertDatabaseMissing('design_items', ['name' => 'Should be denied']);
    }
}
