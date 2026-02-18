<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\ZenaChangeRequest;
use App\Models\ZenaProject;
use App\Models\ZenaRfi;
use App\Models\ZenaSubmittal;
use App\Models\ZenaTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\DB;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;
use Tests\TestCase;

/**
 * @group slow
 */
class PerformanceTest extends TestCase
{
    use LazilyRefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    private const PROJECT_LISTING_COUNT = 35;
    private const TASK_LISTING_COUNT = 120;
    private const TASK_SEARCH_COUNT = 80;
    private const SEARCHABLE_TASK_COUNT = 5;
    private const TASK_STATUS_COUNT = 80;
    private const PAGINATION_TASK_COUNT = 150;
    private const COMPLEX_DEPENDENCY_COUNT = 40;
    private const MEMORY_TASK_COUNT = 160;
    private const CONCURRENT_REQUEST_COUNT = 6;
    private const MEMORY_LIMIT_BYTES = 30 * 1024 * 1024;
    private const STATUS_OPTIONS = ['todo', 'in_progress', 'done', 'pending'];
    private const RFI_LISTING_COUNT = 60;
    private const SUBMITTAL_LISTING_COUNT = 60;
    private const CHANGE_REQUEST_LISTING_COUNT = 60;
    private const QUERY_COUNT_TASKS = 60;

    protected Tenant $tenant;
    protected User $user;
    protected ZenaProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Builder::hasGlobalMacro('search')) {
            Builder::macro('search', function (string $term) {
                return $this->where(function ($query) use ($term) {
                    $query->where('name', 'like', '%' . $term . '%')
                          ->orWhere('description', 'like', '%' . $term . '%');
                });
            });
        }

        $this->apiActingAsTenantAdmin();

        $this->tenant = $this->apiFeatureTenant;
        $this->user = $this->apiFeatureUser;

        $this->project = ZenaProject::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'pm_id' => $this->user->id,
        ]);

        DB::table('zena_projects')->insert([
            'id' => $this->project->id,
            'code' => $this->project->code,
            'name' => $this->project->name,
            'description' => $this->project->description ?? 'Performance seed project',
            'client_id' => $this->user->id,
            'status' => 'planning',
            'start_date' => $this->project->start_date,
            'end_date' => $this->project->end_date,
            'budget' => $this->project->budget_total ?? $this->project->budget ?? 0,
            'settings' => json_encode($this->project->settings ?? []),
            'created_at' => $this->project->created_at ?? now(),
            'updated_at' => $this->project->updated_at ?? now(),
        ]);
    }

    public function test_project_listing_performance(): void
    {
        ZenaProject::factory()->count(self::PROJECT_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'pm_id' => $this->user->id,
        ]);

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('projects.index')),
            self::PROJECT_LISTING_COUNT + 1
        );

        $this->assertNotEmpty($data);
    }

    public function test_task_listing_performance(): void
    {
        ZenaTask::factory()->count(self::TASK_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('tasks.index')),
            self::TASK_LISTING_COUNT
        );

        $this->assertNotEmpty($data);
    }

    public function test_rfi_listing_performance(): void
    {
        ZenaRfi::factory()->count(self::RFI_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'asked_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('rfis.index')),
            self::RFI_LISTING_COUNT
        );

        $this->assertNotEmpty($data);
    }

    public function test_submittal_listing_performance(): void
    {
        ZenaSubmittal::factory()->count(self::SUBMITTAL_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'submitted_by' => $this->user->id,
            'reviewed_by' => $this->user->id,
        ]);

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('submittals.index')),
            self::SUBMITTAL_LISTING_COUNT
        );

        $this->assertNotEmpty($data);
    }

    public function test_change_request_listing_performance(): void
    {
        ZenaChangeRequest::factory()->count(self::CHANGE_REQUEST_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('change-requests.index')),
            self::CHANGE_REQUEST_LISTING_COUNT
        );

        $this->assertNotEmpty($data);
    }

    public function test_search_performance(): void
    {
        ZenaTask::factory()->count(self::TASK_SEARCH_COUNT - self::SEARCHABLE_TASK_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        foreach (range(1, self::SEARCHABLE_TASK_COUNT) as $index) {
            ZenaTask::factory()->create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'created_by' => $this->user->id,
                'title' => 'Performance Search ' . $index,
                'name' => 'Performance Search ' . $index,
            ]);
        }

        $response = $this->apiGet($this->zena('tasks.index', query: ['search' => 'Performance Search 1']));

        $data = $this->assertPaginatedResponse($response, 1);

        $this->assertNotEmpty($data);

    }

    public function test_filtering_performance(): void
    {
        $statusCount = count(self::STATUS_OPTIONS);

        for ($i = 0; $i < self::TASK_STATUS_COUNT; $i++) {
            ZenaTask::factory()->create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'created_by' => $this->user->id,
                'status' => self::STATUS_OPTIONS[$i % $statusCount],
            ]);
        }

        $expectedTodos = intdiv(self::TASK_STATUS_COUNT, $statusCount) + (self::TASK_STATUS_COUNT % $statusCount > 0 ? 1 : 0);

        $response = $this->apiGet($this->zena('tasks.index', query: ['status' => 'todo']));

        $data = $this->assertPaginatedResponse($response, $expectedTodos);

        $this->assertSame($expectedTodos, $response->json('meta.pagination.total', 0));
        $this->assertNotEmpty($data);
    }

    public function test_pagination_performance(): void
    {
        ZenaTask::factory()->count(self::PAGINATION_TASK_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet($this->zena('tasks.index', query: [
            'per_page' => 25,
            'page' => 2,
        ]));

        $data = $this->assertPaginatedResponse($response, self::PAGINATION_TASK_COUNT);

        $this->assertSame(2, $response->json('meta.pagination.page'));
        $this->assertSame(25, $response->json('meta.pagination.per_page'));
        $this->assertNotEmpty($data);
    }

    public function test_complex_query_performance(): void
    {
        $tasks = ZenaTask::factory()->count(self::COMPLEX_DEPENDENCY_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        for ($i = 1; $i < $tasks->count(); $i++) {
            $tasks[$i]->update([
                'dependencies_json' => [$tasks[$i - 1]->ulid],
            ]);
        }

        $data = $this->assertPaginatedResponse(
            $this->apiGet($this->zena('tasks.index', query: ['with_dependencies' => 'true'])),
            self::COMPLEX_DEPENDENCY_COUNT
        );

        $this->assertNotEmpty($data);
    }

    public function test_concurrent_request_performance(): void
    {
        ZenaTask::factory()->count(self::TASK_LISTING_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        collect(range(1, self::CONCURRENT_REQUEST_COUNT))
            ->map(fn () => $this->apiGet($this->zena('tasks.index')))
            ->each(fn (TestResponse $response) => $response->assertStatus(200));
    }

    public function test_memory_usage(): void
    {
        $initialMemory = memory_get_usage();

        ZenaTask::factory()->count(self::MEMORY_TASK_COUNT)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet($this->zena('tasks.index'));

        $memoryUsed = memory_get_usage() - $initialMemory;

        $this->assertLessThan(self::MEMORY_LIMIT_BYTES, $memoryUsed);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_database_query_count(): void
    {
        ZenaTask::factory()->count(self::QUERY_COUNT_TASKS)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        config(['sanctum.update_last_used_at' => false]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->apiGet($this->zena('tasks.index'));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, count($queries));
    }

    private function assertPaginatedResponse(TestResponse $response, int $minimumTotal = 0): array
    {
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status',
                'data',
                'meta' => [
                    'pagination' => [
                        'page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        if ($minimumTotal > 0) {
            $this->assertGreaterThanOrEqual(
                $minimumTotal,
                $response->json('meta.pagination.total', 0)
            );
        }

        return $response->json('data', []);
    }

}
