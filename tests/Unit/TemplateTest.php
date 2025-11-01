<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['id' => 'test-tenant-1']);
        $this->user = User::factory()->create([
            'id' => 'test-user-1',
            'tenant_id' => $this->tenant->id,
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function it_can_create_a_template()
    {
        $template = Template::create([
            'id' => Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Template',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Phase 1',
                        'duration_days' => 5,
                        'tasks' => []
                    ]
                ]
            ],
            'version' => 1,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals($this->tenant->id, $template->tenant_id);
        $this->assertEquals(Template::CATEGORY_PROJECT, $template->category);
        $this->assertEquals(Template::STATUS_DRAFT, $template->status);
        $this->assertFalse($template->is_public);
        $this->assertTrue($template->is_active);
        $this->assertEquals(1, $template->version);
        $this->assertEquals(0, $template->usage_count);
    }

    /** @test */
    public function it_can_get_template_phases()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_data' => [
                'phases' => [
                    ['name' => 'Phase 1', 'duration_days' => 5],
                    ['name' => 'Phase 2', 'duration_days' => 10]
                ]
            ]
        ]);

        $phases = $template->getPhases();

        $this->assertCount(2, $phases);
        $this->assertEquals('Phase 1', $phases[0]['name']);
        $this->assertEquals('Phase 2', $phases[1]['name']);
    }

    /** @test */
    public function it_can_get_template_tasks()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_data' => [
                'tasks' => [
                    ['name' => 'Task 1', 'duration_days' => 3],
                    ['name' => 'Task 2', 'duration_days' => 5]
                ]
            ]
        ]);

        $tasks = $template->getTasks();

        $this->assertCount(2, $tasks);
        $this->assertEquals('Task 1', $tasks[0]['name']);
        $this->assertEquals('Task 2', $tasks[1]['name']);
    }

    /** @test */
    public function it_can_calculate_estimated_duration()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_data' => [
                'tasks' => [
                    ['name' => 'Task 1', 'duration_days' => 3],
                    ['name' => 'Task 2', 'duration_days' => 5],
                    ['name' => 'Task 3', 'duration_days' => 2]
                ]
            ]
        ]);

        $duration = $template->getEstimatedDuration();

        $this->assertEquals(10, $duration);
    }

    /** @test */
    public function it_can_calculate_estimated_cost()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_data' => [
                'tasks' => [
                    ['name' => 'Task 1', 'estimated_cost' => 100.50],
                    ['name' => 'Task 2', 'estimated_cost' => 200.75],
                    ['name' => 'Task 3', 'estimated_cost' => 150.25]
                ]
            ]
        ]);

        $cost = $template->getEstimatedCost();

        $this->assertEquals(451.50, $cost);
    }

    /** @test */
    public function it_can_validate_template()
    {
        $validTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Valid Template',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => ['phases' => []],
            'status' => Template::STATUS_DRAFT
        ]);

        $this->assertTrue($validTemplate->isValid());

        $invalidTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '', // Invalid: empty name
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => ['phases' => []],
            'status' => Template::STATUS_DRAFT
        ]);

        $this->assertFalse($invalidTemplate->isValid());
    }

    /** @test */
    public function it_can_check_if_template_can_be_used()
    {
        $usableTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_PUBLISHED,
            'is_active' => true,
            'name' => 'Usable Template',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => ['phases' => []]
        ]);

        $this->assertTrue($usableTemplate->canBeUsed());

        $unusableTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_DRAFT, // Not published
            'is_active' => true,
            'name' => 'Unusable Template',
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => ['phases' => []]
        ]);

        $this->assertFalse($unusableTemplate->canBeUsed());
    }

    /** @test */
    public function it_can_increment_usage_count()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'usage_count' => 5
        ]);

        $template->incrementUsage();

        $this->assertEquals(6, $template->fresh()->usage_count);
    }

    /** @test */
    public function it_can_publish_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_DRAFT
        ]);

        $result = $template->publish();

        $this->assertTrue($result);
        $this->assertEquals(Template::STATUS_PUBLISHED, $template->fresh()->status);
    }

    /** @test */
    public function it_can_archive_template()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_PUBLISHED
        ]);

        $result = $template->archive();

        $this->assertTrue($result);
        $this->assertEquals(Template::STATUS_ARCHIVED, $template->fresh()->status);
    }

    /** @test */
    public function it_can_duplicate_template()
    {
        $originalTemplate = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Template',
            'usage_count' => 10
        ]);

        $duplicate = $originalTemplate->duplicate('Duplicated Template', $this->user->id);

        $this->assertInstanceOf(Template::class, $duplicate);
        $this->assertEquals('Duplicated Template', $duplicate->name);
        $this->assertEquals($this->tenant->id, $duplicate->tenant_id);
        $this->assertEquals(1, $duplicate->version);
        $this->assertEquals(Template::STATUS_DRAFT, $duplicate->status);
        $this->assertEquals(0, $duplicate->usage_count);
        $this->assertEquals($this->user->id, $duplicate->created_by);
        $this->assertNotEquals($originalTemplate->id, $duplicate->id);
    }

    /** @test */
    public function it_can_filter_by_tenant()
    {
        $otherTenant = Tenant::factory()->create(['id' => 'other-tenant-1']);
        
        Template::factory()->create(['tenant_id' => $this->tenant->id]);
        Template::factory()->create(['tenant_id' => $otherTenant->id]);

        $templates = Template::byTenant($this->tenant->id)->get();

        $this->assertCount(1, $templates);
        $this->assertEquals($this->tenant->id, $templates->first()->tenant_id);
    }

    /** @test */
    public function it_can_filter_by_category()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_PROJECT
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => Template::CATEGORY_TASK
        ]);

        $projectTemplates = Template::byTenant($this->tenant->id)->byCategory(Template::CATEGORY_PROJECT)->get();

        $this->assertCount(1, $projectTemplates);
        $this->assertEquals(Template::CATEGORY_PROJECT, $projectTemplates->first()->category);
    }

    /** @test */
    public function it_can_filter_active_templates()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false
        ]);

        $activeTemplates = Template::byTenant($this->tenant->id)->active()->get();

        $this->assertCount(1, $activeTemplates);
        $this->assertTrue($activeTemplates->first()->is_active);
    }

    /** @test */
    public function it_can_filter_public_templates()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_public' => true
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_public' => false
        ]);

        $publicTemplates = Template::byTenant($this->tenant->id)->public()->get();

        $this->assertCount(1, $publicTemplates);
        $this->assertTrue($publicTemplates->first()->is_public);
    }

    /** @test */
    public function it_can_filter_published_templates()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_PUBLISHED
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Template::STATUS_DRAFT
        ]);

        $publishedTemplates = Template::byTenant($this->tenant->id)->published()->get();

        $this->assertCount(1, $publishedTemplates);
        $this->assertEquals(Template::STATUS_PUBLISHED, $publishedTemplates->first()->status);
    }

    /** @test */
    public function it_can_get_popular_templates()
    {
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'usage_count' => 100
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'usage_count' => 50
        ]);
        Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'usage_count' => 200
        ]);

        $popularTemplates = Template::byTenant($this->tenant->id)->popular(2)->get();

        $this->assertCount(2, $popularTemplates);
        $this->assertEquals(200, $popularTemplates->first()->usage_count);
        $this->assertEquals(100, $popularTemplates->last()->usage_count);
    }

    /** @test */
    public function it_has_relationships()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id
        ]);

        $this->assertInstanceOf(Tenant::class, $template->tenant);
        $this->assertInstanceOf(User::class, $template->creator);
        $this->assertInstanceOf(User::class, $template->updater);
    }

    /** @test */
    public function it_can_have_versions()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $version = TemplateVersion::factory()->create([
            'template_id' => $template->id,
            'version' => 1,
            'created_by' => $this->user->id
        ]);

        $this->assertTrue($template->versions->contains($version));
        $this->assertEquals(1, $template->versions->count());
    }

    /** @test */
    public function it_can_have_projects()
    {
        $template = Template::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_id' => $template->id
        ]);

        $this->assertTrue($template->projects->contains($project));
        $this->assertEquals(1, $template->projects->count());
    }
}