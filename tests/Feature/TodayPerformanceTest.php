<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ANALYTICAL_MAX_QUERIES = 20;

    private function seedFixture(Tenant $tenant, \App\Models\User $viewer, Project $project, int $rowCount): void
    {
        foreach (range(1, $rowCount) as $i) {
            Task::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $viewer->id,
                'status' => Task::STATUS_PENDING,
                'name' => "Việc {$i}", 'title' => "Việc {$i}",
            ]);
            DesignItem::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $viewer->id,
                'review_status' => DesignItem::STATUS_DRAFT,
            ]);
            ProjectMilestone::create([
                'project_id' => (string) $project->id,
                'name' => "Milestone {$i}",
                'target_date' => now()->addDays($i)->toDateString(),
                'status' => ProjectMilestone::STATUS_PENDING,
            ]);
        }
    }

    private function countQueriesForTodayPage(Tenant $tenant, \App\Models\User $viewer): int
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('app.today'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_does_not_grow_with_rendered_row_count(): void
    {
        $tenantSmall = Tenant::factory()->create();
        $viewerSmall = $this->createTenantUser($tenantSmall, [], ['admin'], ['task.view']);
        $projectSmall = Project::factory()->create(['tenant_id' => (string) $tenantSmall->id]);
        $this->seedFixture($tenantSmall, $viewerSmall, $projectSmall, 3);
        $smallCount = $this->countQueriesForTodayPage($tenantSmall, $viewerSmall);

        $tenantLarge = Tenant::factory()->create();
        $viewerLarge = $this->createTenantUser($tenantLarge, [], ['admin'], ['task.view']);
        $projectLarge = Project::factory()->create(['tenant_id' => (string) $tenantLarge->id]);
        $this->seedFixture($tenantLarge, $viewerLarge, $projectLarge, 40);
        $largeCount = $this->countQueriesForTodayPage($tenantLarge, $viewerLarge);

        // Cho phép sai lệch bằng 0 — số query phải giống hệt nhau dù số row
        // tăng từ 3 lên 40, vì mọi query đều dùng get()/whereIn() 1 lần, không
        // lặp theo số bản ghi. Nếu có N+1 thật, $largeCount sẽ lớn hơn hẳn.
        $this->assertSame($smallCount, $largeCount, 'Query count must not scale with rendered row count.');
    }

    public function test_page_query_count_stays_at_or_below_documented_ceiling(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createTenantUser($tenant, [], ['admin'], ['task.view']);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $this->seedFixture($tenant, $viewer, $project, 40);

        $count = $this->countQueriesForTodayPage($tenant, $viewer);

        $this->assertLessThanOrEqual(
            self::ANALYTICAL_MAX_QUERIES,
            $count,
            'Query count exceeded the documented analytical ceiling (see Task 11 Step 3) — this is a regression to fix, not a number to raise.'
        );
    }

    public function test_page_p95_style_single_sample_under_500ms(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createTenantUser($tenant, [], ['admin'], ['task.view']);
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $start = microtime(true);
        $this->actingAs($viewer)->get(route('app.today'))->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $elapsedMs, 'Today page should respond in under 500ms per PROJECT_CONSTITUTION.md Appendix A.8.');
    }
}
