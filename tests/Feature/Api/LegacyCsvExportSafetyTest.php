<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->user->assignRole('admin');

        Storage::fake('local');
        $this->disk = config('filesystems.default');
    }

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

    private function postCsv(string $uri, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->actingAsExportUser())
            ->postJson($uri, $payload);
    }

    private function readExportedFile(string $filename): string
    {
        return Storage::disk($this->disk)->get('exports/' . $filename);
    }

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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->create([
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
        CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->create([
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
        CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $filename = $response->json('data.filename');
        $this->assertStringStartsWith('projects_export_', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    /** @test */
    public function task_csv_header_has_approved_labels_and_order(): void
    {
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->create([
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
        CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        CoreTask::factory()->create([
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
        CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postCsv('/api/projects/bulk/export', ['format' => 'csv']);
        $response->assertOk();

        $payload = $this->readExportedFile($response->json('data.filename'));
        $this->assertLfNoBom($payload);
    }

    /** @test */
    public function task_csv_preserves_ulid_strings(): void
    {
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = CoreTask::factory()->create([
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = CoreTask::factory()->create([
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = CoreTask::factory()->create([
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = CoreTask::factory()->create([
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);

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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = CoreTask::factory()->create([
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
        $assignmentQueries = array_filter($queries, function (array $query) use ($task): bool {
            return str_contains($query['query'], 'task_assignments');
        });

        $this->assertEmpty($assignmentQueries, 'Task CSV must not execute any assignments relation query');
    }

    /** @test */
    public function project_csv_does_not_hydrate_tasks(): void
    {
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
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
        $project = CoreProject::factory()->create(['tenant_id' => $this->tenant->id]);
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
}
