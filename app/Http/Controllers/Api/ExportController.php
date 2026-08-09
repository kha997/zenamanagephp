<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportTenantProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

class ExportController extends Controller
{
    private const TASK_CSV_CHUNK_SIZE = 500;

    public function __construct(private ExportTenantProjectionService $projectionService)
    {
    }

    private function trustedTenantId(Request $request): string
    {
        $tenantId = trim((string) $request->attributes->get('tenant_id', ''));

        if ($tenantId === '') {
            throw new \RuntimeException('Trusted tenant context is required for export.');
        }

        return $tenantId;
    }

    public function exportTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'task_ids' => 'array',
                'format' => 'string|in:csv,excel,json',
                'filters' => 'array',
            ]);

            $format = $request->input('format', 'csv');
            $taskIds = $request->input('task_ids', []);
            $filters = $request->input('filters', []);
            $trustedTenantId = $this->trustedTenantId($request);

            $query = Task::query()
                ->where('tenant_id', $trustedTenantId)
                ->whereHas('project', fn ($projectQuery) => $projectQuery
                    ->where('tenant_id', $trustedTenantId)
                );

            if (!empty($taskIds)) {
                $query->whereIn('id', $taskIds);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            if (isset($filters['project_id'])) {
                $query->where('project_id', $filters['project_id']);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "tasks_export_{$timestamp}.{$format}";

            if ($format === 'csv') {
                $result = $this->generateTaskCsv($query, $filename, $trustedTenantId);
                $filePath = $result['path'];
                $total = $result['count'];
            } elseif ($format === 'excel') {
                $tasks = $query->with([
                    'project' => fn ($q) => $q->where('tenant_id', $trustedTenantId),
                ])->get();
                $safeTasks = $this->projectionService->projectTasks($tasks, $trustedTenantId);
                $filePath = $this->generateExcel($safeTasks, $filename);
                $total = $safeTasks->count();
            } else {
                $tasks = $query->with([
                    'project' => fn ($q) => $q->where('tenant_id', $trustedTenantId),
                ])->get();
                $safeTasks = $this->projectionService->projectTasks($tasks, $trustedTenantId);
                $filePath = $this->generateJson($safeTasks, $filename);
                $total = $safeTasks->count();
            }

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => Storage::url($filePath),
                    'total_tasks' => $total,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Export tasks error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_ids' => 'array',
                'format' => 'string|in:csv,excel,json',
            ]);

            $format = $request->input('format', 'csv');
            $projectIds = $request->input('project_ids', []);
            $trustedTenantId = $this->trustedTenantId($request);

            $query = Project::query()->where('tenant_id', $trustedTenantId);

            if (!empty($projectIds)) {
                $query->whereIn('id', $projectIds);
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "projects_export_{$timestamp}.{$format}";

            if ($format === 'csv') {
                $result = $this->generateProjectCsv($query, $filename, $trustedTenantId);
                $filePath = $result['path'];
                $total = $result['count'];
            } elseif ($format === 'excel') {
                $result = $this->generateProjectCsv($query, str_replace('.xlsx', '.csv', $filename), $trustedTenantId);
                $filePath = $result['path'];
                $total = $result['count'];
            } else {
                $projects = $query->get();
                $projectIds = $projects->pluck('id')->all();
                $taskQuery = Task::query()
                    ->where('tenant_id', $trustedTenantId)
                    ->whereIn('project_id', $projectIds);
                /** @var \Illuminate\Database\Eloquent\Builder<Task> $taskQuery */
                /** @phpstan-ignore method.notFound */
                $taskQuery = $taskQuery->whereHas('project', fn ($q) => $q->where('tenant_id', $trustedTenantId));
                $tasks = $taskQuery->get();
                $safeProjects = $this->projectionService->projectJsonRows($projects, $tasks, $trustedTenantId);
                $filePath = $this->generateProjectsJson($safeProjects, $filename);
                $total = $safeProjects->count();
            }

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => Storage::url($filePath),
                    'total_projects' => $total,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Export projects error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** @return array{path: string, count: int} */
    /** @param \Illuminate\Database\Eloquent\Builder<Task> $query */
    /** @phpstan-ignore missingType.generics, missingType.iterableValue */
    private function generateTaskCsv(\Illuminate\Database\Eloquent\Builder $query, string $filename, string $tenantId): array
    {
        $filePath = "exports/{$filename}";
        $partPath = $filePath . '.part';
        $temp = tmpfile();
        if ($temp === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV export.');
        }

        try {
            $written = fputcsv($temp, [
                'ID', 'Name', 'Description', 'Status', 'Priority', 'Project',
                'Assignee', 'Start Date', 'End Date', 'Progress %',
                'Estimated Hours', 'Actual Hours', 'Tags', 'Created At',
            ], ',', '"', '', "\n");
            if ($written === false) {
                throw new \RuntimeException('Unable to write CSV header.');
            }

            $exportedRowCount = 0;

            $query->with([
                'project' => fn ($q) => $q->where('tenant_id', $tenantId),
            ])->chunkById(self::TASK_CSV_CHUNK_SIZE, function ($tasks) use ($tenantId, $temp, &$exportedRowCount) {
                $safeTasks = $this->projectionService->projectTasks($tasks, $tenantId);
                foreach ($safeTasks as $safeTask) {
                    /** @var array<string, mixed> $safeTask */
                    $row = $this->taskCsvRow($safeTask);
                    $written = fputcsv($temp, $row, ',', '"', '', "\n");
                    if ($written === false) {
                        throw new \RuntimeException('Unable to write CSV row.');
                    }
                    $exportedRowCount++;
                }
            });

            rewind($temp);

            if (! Storage::put($partPath, $temp)) {
                throw new \RuntimeException('Unable to write temporary export artifact.');
            }

            if (! Storage::move($partPath, $filePath)) {
                throw new \RuntimeException('Unable to publish export artifact.');
            }

            /** @phpstan-ignore-line */
            return ['path' => $filePath, 'count' => $exportedRowCount];
        } catch (\Exception $e) {
            if (Storage::exists($partPath)) {
                Storage::delete($partPath);
            }
            throw $e;
        } finally {
            if (is_resource($temp)) {
                fclose($temp);
            }
        }
    }

    /**
     * @param array<string, mixed> $task
     * @return array<int, string>
     */
    private function taskCsvRow(array $task): array
    {
        return [
            (string) $task['id'],
            $this->neutralizeTextualFormula((string) ($task['name'] ?? '')),
            $this->neutralizeTextualFormula((string) ($task['description'] ?? '')),
            (string) ($task['status'] ?? ''),
            (string) ($task['priority'] ?? ''),
            $this->neutralizeTextualFormula((string) ($task['project']['name'] ?? 'N/A')),
            isset($task['assignee_id']) && $task['assignee_id'] !== '' ? 'User ' . $task['assignee_id'] : 'Unassigned',
            (string) ($task['start_date'] ?? ''),
            (string) ($task['end_date'] ?? ''),
            (string) (float) ($task['progress_percent'] ?? 0),
            (string) (float) ($task['estimated_hours'] ?? 0),
            (string) (float) ($task['actual_hours'] ?? 0),
            $this->serializeTags($task['tags'] ?? null),
            (string) ($task['created_at'] ?? ''),
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Project> $query */
    /** @return array{path: string, count: int} */
    /** @phpstan-ignore missingType.generics, missingType.iterableValue */
    private function generateProjectCsv(\Illuminate\Database\Eloquent\Builder $query, string $filename, string $tenantId): array
    {
        $filePath = "exports/{$filename}";
        $partPath = $filePath . '.part';
        $temp = tmpfile();
        if ($temp === false) {
            throw new \RuntimeException('Unable to create temporary stream for CSV export.');
        }

        try {
            $written = fputcsv($temp, [
                'ID', 'Code', 'Name', 'Description', 'Status', 'Priority',
                'Progress %', 'Budget Total', 'Budget Planned', 'Budget Actual',
                'Start Date', 'End Date', 'Total Tasks', 'Completed Tasks', 'Created At',
            ], ',', '"', '', "\n");
            if ($written === false) {
                throw new \RuntimeException('Unable to write CSV header.');
            }

            $exportedRowCount = 0;

            $query->withCount([
                'tasks as tasks_count' => fn ($q) => $q->where('tenant_id', $tenantId),
                'tasks as completed_tasks_count' => fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'completed'),
            ])->chunkById(500, function ($projects) use ($tenantId, $temp, &$exportedRowCount) {
                $safeRows = $this->projectionService->projectTabularRows($projects, $tenantId);
                foreach ($safeRows as $row) {
                    /** @var array<string, mixed> $row */
                    $csvRow = $this->projectCsvRow($row);
                    $written = fputcsv($temp, $csvRow, ',', '"', '', "\n");
                    if ($written === false) {
                        throw new \RuntimeException('Unable to write CSV row.');
                    }
                    $exportedRowCount++;
                }
            });

            rewind($temp);

            if (! Storage::put($partPath, $temp)) {
                throw new \RuntimeException('Unable to write temporary export artifact.');
            }

            if (! Storage::move($partPath, $filePath)) {
                throw new \RuntimeException('Unable to publish export artifact.');
            }

            /** @phpstan-ignore-line */
            return ['path' => $filePath, 'count' => $exportedRowCount];
        } catch (\Exception $e) {
            if (Storage::exists($partPath)) {
                Storage::delete($partPath);
            }
            throw $e;
        } finally {
            if (is_resource($temp)) {
                fclose($temp);
            }
        }
    }

    /**
     * @param array<string, mixed> $project
     * @return array<int, string>
     */
    private function projectCsvRow(array $project): array
    {
        return [
            (string) $project['id'],
            $this->neutralizeTextualFormula((string) ($project['code'] ?? '')),
            $this->neutralizeTextualFormula((string) ($project['name'] ?? '')),
            $this->neutralizeTextualFormula((string) ($project['description'] ?? '')),
            (string) ($project['status'] ?? ''),
            (string) ($project['priority'] ?? ''),
            (string) (float) ($project['progress'] ?? 0),
            (string) (float) ($project['budget_total'] ?? 0),
            (string) (float) ($project['budget_planned'] ?? 0),
            (string) (float) ($project['budget_actual'] ?? 0),
            (string) ($project['start_date'] ?? ''),
            (string) ($project['end_date'] ?? ''),
            (string) (int) ($project['tasks_count'] ?? 0),
            (string) (int) ($project['completed_tasks_count'] ?? 0),
            (string) ($project['created_at'] ?? ''),
        ];
    }

    private function neutralizeTextualFormula(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $trimmed = ltrim($value, " \t\r\n");

        if (isset($trimmed[0]) && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** @param array<int, string>|null $tags */
    private function serializeTags(?array $tags): string
    {
        if (empty($tags)) {
            return '';
        }

        return $this->neutralizeTextualFormula(implode(', ', $tags));
    }

    /** @param \Illuminate\Support\Collection<array-key, array<string, mixed>> $tasks */
    private function generateExcel($tasks, string $filename)
    {
        // For now, just generate CSV with .xlsx extension
        // In production, you'd
    }

    /** @param \Illuminate\Support\Collection<array-key, array<string, mixed>> $tasks */
    private function generateJson($tasks, string $filename): string
    {
        $filePath = "exports/{$filename}";

        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $tasks->count(),
                'format' => 'json',
            ],
            'tasks' => $tasks->all(),
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }

    /** @param \Illuminate\Support\Collection<array-key, array<string, mixed>> $projects */
    private function generateProjectsJson($projects, string $filename): string
    {
        $filePath = "exports/{$filename}";

        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $projects->count(),
                'format' => 'json',
            ],
            'projects' => $projects->all(),
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }
}
