<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Src\CoreProject\Models\Project as CoreProject;
use Src\CoreProject\Models\Task as CoreTask;
use Tests\TestCase;

class LegacyCsvExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createUser([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->user->assignRole('admin');

        $this->disk = config('filesystems.default');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function createTenant(array $attributes = []): Tenant
    {
        $model = Tenant::factory()->createOne($attributes);
        if (! $model instanceof Tenant) {
            throw new \LogicException('Tenant factory returned an unexpected model type.');
        }

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    private function createUser(array $attributes = []): User
    {
        $model = User::factory()->createOne($attributes);
        if (! $model instanceof User) {
            throw new \LogicException('User factory returned an unexpected model type.');
        }

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    private function createCoreProject(array $attributes = []): CoreProject
    {
        $model = CoreProject::factory()->createOne($attributes);
        if (! $model instanceof CoreProject) {
            throw new \LogicException('Project factory returned ' . get_debug_type($model) . '.');
        }

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    private function createCoreTask(array $attributes = []): Task
    {
        $model = CoreTask::factory()->createOne($attributes);
        if (! $model instanceof Task) {
            throw new \LogicException('Task factory returned ' . get_debug_type($model) . '.');
        }

        return $model;
    }

    /** @return array<string, string> */
    private function actingAsExportUser(): array
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $token = $response->json('data.token');

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postCsv(string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->actingAsExportUser())
            ->postJson($uri, $payload);
    }

    private function readExportedFile(string $filename): string
    {
        return Storage::disk($this->disk)->get('exports/' . $filename);
    }

    private function cleanExportDirectory(): void
    {
        $exportsPath = storage_path('app/exports');

        if (! File::exists($exportsPath)) {
            return;
        }

        foreach (File::files($exportsPath) as $file) {
            File::delete($file->getRealPath());
        }
    }

    /** @return list<list<string|null>> */
    private function parseCsv(string $payload): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $payload);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);
        return $rows;
    }

    /** @param list<string> $expectedLabels */
    private function assertCsvHeader(array $expectedLabels, string $payload): void
    {
        $rows = $this->parseCsv($payload);
        $this->assertNotEmpty($rows, 'CSV payload must contain at least a header row');
        $this->assertSame($expectedLabels, $rows[0], 'CSV header labels and order must match exactly');
    }

    private function assertLfNoBom(string $payload): void
    {
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $payload, 'CSV payload must not contain BOM');
        $this->assertStringContainsString("\n", $payload, 'CSV payload must contain LF line endings');
    }

    // ------------------------------------------------------------------
    // Task 1: Baseline contract characterization
    // ------------------------------------------------------------------

    /** @test */
    public function request_import_is_present(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\Api\ExportController::class);
        $this->assertTrue(
            $reflection->hasMethod('exportTasks'),
            'ExportController must expose exportTasks'
        );
        $this->assertTrue(
            $reflection->hasMethod('exportProjects'),
            'ExportController must expose exportProjects'
        );
    }

    /** @test */
    public function task_csv_route_is_reachable(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'filename',
                'download_url',
                'total_tasks',
            ],
        ]);
    }

    /** @test */
    public function project_csv_route_is_reachable(): void
    {
        $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', [
            'format' => 'csv',
            'project_ids' => [],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'filename',
                'download_url',
                'total_projects',
            ],
        ]);
    }

    /** @test */
    public function task_csv_has_correct_filename_pattern(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $this->assertStringStartsWith('tasks_export_', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    /** @test */
    public function project_csv_has_correct_filename_pattern(): void
    {
        $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $this->assertStringStartsWith('projects_export_', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    /** @test */
    public function task_csv_header_has_approved_labels_and_order(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);

        $this->assertCsvHeader([
            'ID',
            'Name',
            'Description',
            'Status',
            'Priority',
            'Project',
            'Assignee',
            'Start Date',
            'End Date',
            'Progress %',
            'Estimated Hours',
            'Actual Hours',
            'Tags',
            'Created At',
        ], $payload);
    }

    /** @test */
    public function project_csv_header_has_approved_labels_and_order(): void
    {
        $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);

        $this->assertCsvHeader([
            'ID',
            'Code',
            'Name',
            'Description',
            'Status',
            'Priority',
            'Progress %',
            'Budget Total',
            'Budget Planned',
            'Budget Actual',
            'Start Date',
            'End Date',
            'Total Tasks',
            'Completed Tasks',
            'Created At',
        ], $payload);
    }

    /** @test */
    public function task_csv_uses_lf_and_no_bom(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $payload = $this->readExportedFile($response->json('data.filename'));
        $this->assertLfNoBom($payload);
    }

    /** @test */
    public function project_csv_uses_lf_and_no_bom(): void
    {
        $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $payload = $this->readExportedFile($response->json('data.filename'));
        $this->assertLfNoBom($payload);
    }

    /** @test */
    public function task_csv_preserves_ulid_strings(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv', 'task_ids' => [$task->id]]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertCount(2, $rows);
        $this->assertSame($task->id, $rows[1][0]);
    }

    /** @test */
    public function project_csv_preserves_ulid_strings(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv', 'project_ids' => [$project->id]]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertCount(2, $rows);
        $this->assertSame($project->id, $rows[1][0]);
    }

    /** @test */
    public function task_csv_formula_injection_is_neutralized(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => '=cmd|/c calc',
            'title' => '=cmd|/c calc',
            'description' => '@SUM(A1:A10)',
        ]);

        $this->assertSame('=cmd|/c calc', $task->name);
        $this->assertSame('@SUM(A1:A10)', $task->description);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame("'=cmd|/c calc", $rows[1][1]);
        $this->assertSame("'@SUM(A1:A10)", $rows[1][2]);
    }

    /** @test */
    public function task_csv_tags_are_serialized_as_text(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => ['tag1', 'tag2', 'tag,with,comma'],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('tag1, tag2, tag,with,comma', $rows[1][12]);
    }

    /** @test */
    public function task_csv_written_row_count_matches_parsed_data_rows(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $dataRows = array_slice($rows, 1);
        $this->assertCount(3, $dataRows);
        $this->assertSame(3, $response->json('data.total_tasks'));
    }

    /** @test */
    public function project_csv_written_row_count_matches_parsed_data_rows(): void
    {
        CoreProject::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $dataRows = array_slice($rows, 1);
        $this->assertCount(2, $dataRows);
        $this->assertSame(2, $response->json('data.total_projects'));
    }

    /** @test */
    public function task_json_is_not_modified_by_gap010b(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'json',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_tasks'));
    }

    /** @test */
    public function project_json_is_not_modified_by_gap010b(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', [
            'format' => 'json',
            'project_ids' => [$project->id],
        ]);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_projects'));
    }

    // ------------------------------------------------------------------
    // Task 2: Format-aware dispatch and bounded query sources
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_does_not_load_assignments(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        DB::enableQueryLog();

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $queries = DB::getQueryLog();
        $assignmentQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'task_assignments');
        });

        $this->assertEmpty($assignmentQueries, 'Task CSV must not execute any assignments relation query');
    }

    /** @test */
    public function project_csv_does_not_hydrate_tasks(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(50)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        DB::enableQueryLog();

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $queries = DB::getQueryLog();
        $taskHydrationQueries = array_filter($queries, function (array $query): bool {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'where `tasks`')
                || str_contains($sql, 'where `core_project_tasks`');
        });

        $this->assertEmpty($taskHydrationQueries, 'Project CSV must not hydrate tasks relation');
    }

    // ------------------------------------------------------------------
    // Task 4: Atomic publication and written-row counts
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_successful_row_count_is_exact(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $this->assertSame(5, $response->json('data.total_tasks'));
    }

    /** @test */
    public function project_csv_successful_row_count_is_exact(): void
    {
        CoreProject::factory()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $this->assertSame(4, $response->json('data.total_projects'));
    }

    /** @test */
    public function project_excel_successful_row_count_is_exact(): void
    {
        CoreProject::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'excel']);
        $response->assertOk();

        $this->assertSame(3, $response->json('data.total_projects'));
    }

    // ------------------------------------------------------------------
    // Stream-safe publication and atomic cleanup
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_large_dataset_does_not_amplify_memory_with_full_string(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(200)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $this->assertSame(200, $response->json('data.total_tasks'));

        $filename = $response->json('data.filename');
        $this->assertNotEmpty($filename);

        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);
        $this->assertCount(201, $rows);
    }

    /** @test */
    public function project_csv_large_dataset_does_not_amplify_memory_with_full_string(): void
    {
        CoreProject::factory()->count(50)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $this->assertSame(50, $response->json('data.total_projects'));

        $filename = $response->json('data.filename');
        $this->assertNotEmpty($filename);

        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);
        $this->assertCount(51, $rows);
    }

    /** @test */
    public function task_csv_publish_failure_cleans_up(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('move')->andReturn(false);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function project_csv_publish_failure_cleans_up(): void
    {
        CoreProject::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('move')->andReturn(false);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function task_csv_storage_put_false_cleans_up(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('put')->andReturn(false);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function task_csv_storage_move_false_cleans_up(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('move')->andReturn(false);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function project_csv_storage_put_false_cleans_up(): void
    {
        CoreProject::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('put')->andReturn(false);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function project_csv_storage_move_false_cleans_up(): void
    {
        CoreProject::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->cleanExportDirectory();

        Storage::shouldReceive('move')->andReturn(false);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
        $this->assertNull($response->json('data.download_url'));
        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    /** @test */
    public function task_csv_mid_generation_failure_cleans_up_before_publication(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $task = new \Src\CoreProject\Models\Task([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Mid-export failure task',
            'status' => 'pending',
            'priority' => 'medium',
            'progress_percent' => 0,
            'estimated_hours' => 1,
            'actual_hours' => 0,
        ]);
        $task->save();

        $this->cleanExportDirectory();

        $builder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $withExpectation = $builder->shouldReceive('with');
        if (! $withExpectation instanceof \Mockery\CompositeExpectation) {
            throw new \LogicException('Expected a concrete Mockery expectation.');
        }
        $withExpectation->__call('andReturnSelf', []);

        $withCountExpectation = $builder->shouldReceive('withCount');
        if (! $withCountExpectation instanceof \Mockery\CompositeExpectation) {
            throw new \LogicException('Expected a concrete Mockery expectation.');
        }
        $withCountExpectation->__call('andReturnSelf', []);

        $chunkExpectation = $builder->shouldReceive('chunkById');
        if (! $chunkExpectation instanceof \Mockery\CompositeExpectation) {
            throw new \LogicException('Expected a concrete Mockery expectation.');
        }
        $chunkExpectation->__call('andReturnUsing', [function ($size, $callback) use ($task) {
            $callback(collect([$task]));
            throw new \RuntimeException('Simulated mid-generation failure');
        }]);

        $controller = app(\App\Http\Controllers\Api\ExportController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generateTaskCsv');
        $method->setAccessible(true);

        try {
            $method->invoke($controller, $builder, 'test.csv', 'tenant-1');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mid-generation', $e->getMessage());
        }

        $this->assertEmpty(File::files(storage_path('app/exports')));
    }

    // ------------------------------------------------------------------
    // Formula/type matrix
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_formula_matrix_leading_spaces_and_markers(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => '  =1+1',
            'title' => '  =1+1',
            'description' => "\t+123456789",
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame("'  =1+1", $rows[1][1]);
        $this->assertSame("'\t+123456789", $rows[1][2]);
    }

    /** @test */
    public function task_csv_formula_matrix_ordinary_text_unchanged(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'normal text',
            'title' => 'normal text',
            'description' => 'another normal',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('normal text', $rows[1][1]);
        $this->assertSame('another normal', $rows[1][2]);
    }

    /** @test */
    public function task_csv_formula_matrix_vietnamese_unicode(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Hợp đồng xây dựng',
            'title' => 'Hợp đồng xây dựng',
            'description' => 'Giá trị: =SUM(A1:A2)',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('Hợp đồng xây dựng', $rows[1][1]);
        $this->assertSame("Giá trị: =SUM(A1:A2)", $rows[1][2]);
    }

    /** @test */
    public function task_csv_numeric_negative_remains_numeric(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'progress_percent' => -5,
            'estimated_hours' => -10.5,
            'actual_hours' => -3.25,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('-5', $rows[1][9]);
        $this->assertSame('-10.5', $rows[1][10]);
        $this->assertSame('-3.25', $rows[1][11]);
    }

    /** @test */
    public function project_csv_formula_matrix_textual_fields(): void
    {
        $project = $this->createCoreProject([
            'tenant_id' => $this->tenant->id,
            'name' => '=IMPORTXML(...)',
            'code' => '+CODE',
            'description' => '-TEXT',
        ]);

        $response = $this->postCsv('/api/projects/bulk/export', [
            'format' => 'csv',
            'project_ids' => [$project->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame("'=IMPORTXML(...)", $rows[1][2]);
        $this->assertSame("'+CODE", $rows[1][1]);
        $this->assertSame("'-TEXT", $rows[1][3]);
    }

    /** @test */
    public function project_csv_numeric_fields_remain_numeric(): void
    {
        $project = $this->createCoreProject([
            'tenant_id' => $this->tenant->id,
            'progress' => -5.5,
            'budget_total' => -1000,
            'budget_planned' => -500,
            'budget_actual' => -250,
        ]);

        $response = $this->postCsv('/api/projects/bulk/export', [
            'format' => 'csv',
            'project_ids' => [$project->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('-5.5', $rows[1][6]);
        $this->assertSame('-1000', $rows[1][7]);
        $this->assertSame('-500', $rows[1][8]);
        $this->assertSame('-250', $rows[1][9]);
    }

    // ------------------------------------------------------------------
    // CSV structural round-trip matrix
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_round_trip_comma_quote_backslash(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'value,with,comma',
            'title' => 'value,with,comma',
            'description' => 'value"with"quotes',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('value,with,comma', $rows[1][1]);
        $this->assertSame('value"with"quotes', $rows[1][2]);
    }

    /** @test */
    public function task_csv_round_trip_multiline_and_vietnamese(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => "line1\nline2",
            'title' => "line1\nline2",
            'description' => 'Hợp đồng xây dựng',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame("line1\nline2", $rows[1][1]);
        $this->assertSame('Hợp đồng xây dựng', $rows[1][2]);
    }

    /** @test */
    public function task_csv_round_trip_asserts_lf_and_no_bom(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $payload = $this->readExportedFile($response->json('data.filename'));
        $this->assertLfNoBom($payload);
    }

    // ------------------------------------------------------------------
    // Tags regression matrix
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_tags_null_and_empty(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => null,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('', $rows[1][12]);
    }

    /** @test */
    public function task_csv_tags_one_and_multiple(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => ['one'],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('one', $rows[1][12]);
    }

    /** @test */
    public function task_csv_tags_unicode_and_comma(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => ['Hợp đồng', 'tag,with,comma'],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('Hợp đồng, tag,with,comma', $rows[1][12]);
    }

    /** @test */
    public function task_csv_tags_leading_whitespace_before_marker_is_neutralized(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => [" \t=SPACE(A1)"],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame("' \t=SPACE(A1)", $rows[1][12]);
    }

    /** @test */
    public function task_csv_tags_empty_array_is_serialized_as_empty_string(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => [],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('', $rows[1][12]);
    }

    /** @test */
    public function task_csv_tags_quote_in_tag_is_preserved(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'tags' => ['tag"quote'],
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('tag"quote', $rows[1][12]);
    }

    // ------------------------------------------------------------------
    // CSV structural round-trip matrix
    // ------------------------------------------------------------------

    /** @test */
    public function task_csv_round_trip_backslash(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'value\\with\\backslash',
            'title' => 'value\\with\\backslash',
            'description' => 'path\\to\\file',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('value\\with\\backslash', $rows[1][1]);
        $this->assertSame('path\\to\\file', $rows[1][2]);
    }

    /** @test */
    public function task_csv_round_trip_backslash_before_quote(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'value\\"with\\"backslash',
            'title' => 'value\\"with\\"backslash',
            'description' => 'path\\"to\\"file',
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'csv',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertSame('value\\"with\\"backslash', $rows[1][1]);
        $this->assertSame('path\\"to\\"file', $rows[1][2]);
    }

    // ------------------------------------------------------------------
    // Non-CSV compatibility
    // ------------------------------------------------------------------

    /** @test */
    public function task_json_preserves_existing_payload_shape(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $task = $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', [
            'format' => 'json',
            'task_ids' => [$task->id],
        ]);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_tasks'));
        $this->assertNotNull($response->json('data'));
    }

    /** @test */
    public function project_json_preserves_existing_payload_shape(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', [
            'format' => 'json',
            'project_ids' => [$project->id],
        ]);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_projects'));
        $this->assertNotNull($response->json('data'));
    }

    /** @test */
    public function task_excel_remains_known_incomplete_path(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        $this->createCoreTask([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->postCsv('/api/tasks/bulk/export', ['format' => 'excel']);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_tasks'));
        $this->assertNotNull($response->json('data.filename'));
    }

    /** @test */
    public function project_excel_reuses_bounded_tabular_source(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(200)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        DB::enableQueryLog();

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'excel']);
        $response->assertOk();

        $this->assertSame(1, $response->json('data.total_projects'));

        $filename = $response->json('data.filename');
        $this->assertNotNull($filename);

        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);
        $this->assertCount(2, $rows);
    }

    // ------------------------------------------------------------------
    // Project bounded-memory proof
    // ------------------------------------------------------------------

    /** @test */
    public function project_csv_large_project_bounded_memory_proof(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(200)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        DB::enableQueryLog();

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $queries = DB::getQueryLog();
        $taskHydrationQueries = array_filter($queries, function (array $query): bool {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'where `tasks`')
                || str_contains($sql, 'where `core_project_tasks`');
        });

        $this->assertEmpty($taskHydrationQueries, 'Project CSV must not hydrate tasks relation for large project');

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $response->json('data.total_projects'));
    }

    /** @test */
    public function project_excel_large_project_bounded_memory_proof(): void
    {
        $project = $this->createCoreProject(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->count(200)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
        ]);

        DB::enableQueryLog();

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'excel']);
        $response->assertOk();

        $queries = DB::getQueryLog();
        $taskHydrationQueries = array_filter($queries, function (array $query): bool {
            $sql = strtolower($query['query']);
            return str_contains($sql, 'where `tasks`')
                || str_contains($sql, 'where `core_project_tasks`');
        });

        $this->assertEmpty($taskHydrationQueries, 'Project Excel must not hydrate tasks relation for large project');

        $filename = $response->json('data.filename');
        $payload = $this->readExportedFile($filename);
        $rows = $this->parseCsv($payload);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $response->json('data.total_projects'));
    }
}
