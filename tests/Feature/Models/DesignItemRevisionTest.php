<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\DesignItem;
use App\Models\DesignItemRevision;
use App\Models\Project;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemRevisionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_revision_model_uses_tenant_scope_trait(): void
    {
        $this->assertContains(TenantScope::class, class_uses_recursive(DesignItemRevision::class));
    }

    public function test_design_item_has_ordered_revisions_and_counter_default(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $item = DesignItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Concept mặt đứng',
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $this->assertSame(0, (int) $item->revision_count);

        foreach ([2, 1] as $no) {
            DesignItemRevision::query()->create([
                'tenant_id' => (string) $tenant->id,
                'design_item_id' => (string) $item->id,
                'revision_no' => $no,
                'client_feedback' => "feedback {$no}",
                'requested_by' => (string) $user->id,
                'requested_at' => now(),
            ]);
        }

        $this->assertSame([1, 2], $item->revisions()->pluck('revision_no')->all());
    }
}
