<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\KnowledgeArticle;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class KnowledgeArticleModelTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    public function test_create_sop_article(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $article = KnowledgeArticle::query()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_SOP,
            'title' => 'Quy trình nghiệm thu phần thô',
            'category' => 'thô',
            'body' => 'Bước 1: kiểm tra cốt thép...',
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $this->assertSame(KnowledgeArticle::TYPE_SOP, $article->type);
        $this->assertSame(KnowledgeArticle::STATUS_DRAFT, $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_checklist_items_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $article = KnowledgeArticle::query()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_CHECKLIST,
            'title' => 'Checklist nghiệm thu điện',
            'checklist_items' => [
                ['text' => 'Kiểm tra tiếp địa', 'done' => false],
                ['text' => 'Kiểm tra CB chống giật', 'done' => false],
            ],
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $fresh = KnowledgeArticle::find($article->id);
        $this->assertIsArray($fresh->checklist_items);
        $this->assertCount(2, $fresh->checklist_items);
        $this->assertSame('Kiểm tra tiếp địa', $fresh->checklist_items[0]['text']);
    }

    public function test_tags_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $article = KnowledgeArticle::query()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_LESSON_LEARNED,
            'title' => 'Bài học chống thấm sân thượng',
            'body' => 'Nội dung...',
            'tags' => ['chong-tham', 'san-thuong'],
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $fresh = KnowledgeArticle::find($article->id);
        $this->assertSame(['chong-tham', 'san-thuong'], $fresh->tags);
    }

    public function test_lesson_learned_can_link_to_project(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);
        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $article = KnowledgeArticle::query()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => KnowledgeArticle::TYPE_LESSON_LEARNED,
            'title' => 'Bài học',
            'body' => 'Nội dung',
            'project_id' => (string) $project->id,
            'status' => KnowledgeArticle::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $this->assertSame($project->id, $article->project()->first()->id);
    }
}
