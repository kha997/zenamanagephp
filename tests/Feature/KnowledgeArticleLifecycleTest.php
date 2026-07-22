<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class KnowledgeArticleLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    private function manager(Tenant $tenant): User
    {
        return $this->createTenantUser($tenant, [], ['admin'], ['knowledge.view', 'knowledge.manage']);
    }

    private function viewer(Tenant $tenant): User
    {
        return $this->createTenantUser($tenant, [], [], ['knowledge.view']);
    }

    public function test_create_sop_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $response = $this->post(route('operator.knowledge.store'), [
            'type' => KnowledgeArticle::TYPE_SOP,
            'title' => 'Quy trình nghiệm thu phần thô',
            'category' => 'thô',
            'body' => 'Bước 1...',
        ]);

        $article = KnowledgeArticle::query()->where('title', 'Quy trình nghiệm thu phần thô')->firstOrFail();
        $response->assertRedirect(route('operator.knowledge.show', $article->id));
        $response->assertSessionHas('success');
        $this->assertSame(KnowledgeArticle::STATUS_DRAFT, $article->status);
        $this->assertSame((string) $user->id, $article->created_by);
    }

    public function test_create_checklist_with_items(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $response = $this->post(route('operator.knowledge.store'), [
            'type' => KnowledgeArticle::TYPE_CHECKLIST,
            'title' => 'Checklist nghiệm thu điện',
            'checklist_items' => [
                ['text' => 'Kiểm tra tiếp địa'],
                ['text' => 'Kiểm tra CB chống giật'],
            ],
        ]);

        $article = KnowledgeArticle::query()->where('title', 'Checklist nghiệm thu điện')->firstOrFail();
        $response->assertRedirect(route('operator.knowledge.show', $article->id));
        $this->assertCount(2, $article->checklist_items);
        $this->assertFalse($article->checklist_items[0]['done']);
    }

    public function test_publish_requires_content(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $article = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_SOP,
            'body' => null,
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $response = $this->post(route('operator.knowledge.publish', $article->id));

        $response->assertSessionHas('error');
        $article->refresh();
        $this->assertSame(KnowledgeArticle::STATUS_DRAFT, $article->status);
    }

    public function test_publish_checklist_requires_items(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $article = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_CHECKLIST,
            'body' => null,
            'checklist_items' => null,
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $response = $this->post(route('operator.knowledge.publish', $article->id));

        $response->assertSessionHas('error');
        $article->refresh();
        $this->assertSame(KnowledgeArticle::STATUS_DRAFT, $article->status);
    }

    public function test_publish_happy_path_and_unpublish(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $article = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_SOP,
            'body' => 'Nội dung đầy đủ',
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $response = $this->post(route('operator.knowledge.publish', $article->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $article->refresh();
        $this->assertSame(KnowledgeArticle::STATUS_PUBLISHED, $article->status);
        $this->assertNotNull($article->published_at);

        $response = $this->post(route('operator.knowledge.unpublish', $article->id));
        $response->assertSessionHas('success');
        $article->refresh();
        $this->assertSame(KnowledgeArticle::STATUS_DRAFT, $article->status);
    }

    public function test_cannot_edit_published_article(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $article = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'created_by' => (string) $user->id,
        ]);

        $this->get(route('operator.knowledge.edit', $article->id))->assertNotFound();

        $response = $this->post(route('operator.knowledge.update', $article->id), [
            'type' => $article->type,
            'title' => 'Đổi tiêu đề',
        ]);
        $response->assertSessionHas('error');
        $article->refresh();
        $this->assertNotSame('Đổi tiêu đề', $article->title);
    }

    public function test_destroy_only_allowed_for_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        $published = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'created_by' => (string) $user->id,
        ]);

        $response = $this->delete(route('operator.knowledge.destroy', $published->id));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('knowledge_articles', ['id' => $published->id]);

        $draft = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $response = $this->delete(route('operator.knowledge.destroy', $draft->id));
        $response->assertRedirect(route('operator.knowledge.index'));
        $this->assertDatabaseMissing('knowledge_articles', ['id' => $draft->id]);
    }

    public function test_lesson_learned_links_to_project(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $response = $this->post(route('operator.knowledge.store'), [
            'type' => KnowledgeArticle::TYPE_LESSON_LEARNED,
            'title' => 'Bài học chống thấm',
            'body' => 'Nội dung',
            'project_id' => (string) $project->id,
        ]);

        $article = KnowledgeArticle::query()->where('title', 'Bài học chống thấm')->firstOrFail();
        $response->assertRedirect(route('operator.knowledge.show', $article->id));
        $this->assertSame((string) $project->id, $article->project_id);
    }

    public function test_index_filters_by_type_and_search(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->manager($tenant);
        $this->actingAs($user);

        KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_SOP,
            'title' => 'Quy trình A',
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'created_by' => (string) $user->id,
        ]);
        KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_CHECKLIST,
            'title' => 'Checklist B',
            'status' => KnowledgeArticle::STATUS_PUBLISHED,
            'published_at' => now(),
            'created_by' => (string) $user->id,
        ]);

        $response = $this->get(route('operator.knowledge.index', ['type' => 'sop']));
        $response->assertOk();
        $response->assertSee('Quy trình A');
        $response->assertDontSee('Checklist B');

        $response = $this->get(route('operator.knowledge.index', ['q' => 'Checklist']));
        $response->assertSee('Checklist B');
        $response->assertDontSee('Quy trình A');
    }

    public function test_draft_hidden_from_viewer_without_manage_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->manager($tenant);
        $viewer = $this->viewer($tenant);

        $draft = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'title' => 'Nháp bí mật',
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $manager->id,
        ]);

        $this->actingAs($viewer);
        $response = $this->get(route('operator.knowledge.index', ['status' => 'draft']));
        $response->assertDontSee('Nháp bí mật');
    }

    public function test_cross_tenant_article_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = $this->manager($tenantA);
        $userB = $this->manager($tenantB);

        $article = KnowledgeArticle::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'created_by' => (string) $userB->id,
        ]);

        $this->actingAs($userA);
        $this->get(route('operator.knowledge.show', $article->id))->assertNotFound();
    }

    public function test_manage_permission_required_for_write_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->viewer($tenant);
        $this->actingAs($viewer);

        $response = $this->post(route('operator.knowledge.store'), [
            'type' => KnowledgeArticle::TYPE_SOP,
            'title' => 'Không có quyền',
            'body' => 'x',
        ]);

        $response->assertStatus(302);
    }
}
