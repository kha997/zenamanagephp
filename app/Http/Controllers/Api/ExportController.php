<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

class ExportController extends Controller
{
    private const TASK_CSV_CHUNK_SIZE = 500;

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
                $result = $this->generateTaskCsv($query, $filename);
                $filePath = $result['path'];
                $total = $result['count'];
            } elseif ($format === 'excel') {
                $tasks = $query->with(['project', 'assignments'])->get();
                $filePath = $this->generateExcel($tasks, $filename);
                $total = $tasks->count();
            } else {
                $tasks = $query->with(['project', 'assignments'])->get();
                $filePath = $this->generateJson($tasks, $filename);
                $total = $tasks->count();
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
                $result = $this->generateProjectCsv($query, $filename);
                $filePath = $result['path'];
                $total = $result['count'];
            } elseif ($format === 'excel') {
                $result = $this->generateProjectCsv($query, str_replace('.xlsx', '.csv', $filename));
                $filePath = $result['path'];
                $total = $result['count'];
            } else {
                $projects = $query->with(['tasks'])->get();
                $filePath = $this->generateProjectsJson($projects, $filename);
                $total = $projects->count();
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
    private function generateTaskCsv(\Illuminate\Database\Eloquent\Builder $query, string $filename): array
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

            $query->with('project')->chunkById(self::TASK_CSV_CHUNK_SIZE, function ($tasks) use ($temp, &$exportedRowCount) {
                foreach ($tasks as $task) {
                    /** @var Task $task */
                    $row = $this->taskCsvRow($task);
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

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: string, 9: string, 10: string, 11: string, 12: string, 13: string} */
    private function taskCsvRow(Task $task): array
    {
        return [
            $task->id,
            $this->neutralizeTextualFormula((string) $task->name),
            $this->neutralizeTextualFormula((string) ($task->description ?? '')),
            $task->status,
            $task->priority,
            $this->neutralizeTextualFormula((string) ($task->project->name ?? 'N/A')),
            $task->getAttribute('assignee_id') ? 'User ' . $task->getAttribute('assignee_id') : 'Unassigned',
            $task->start_date?->format('Y-m-d') ?? '',
            $task->end_date?->format('Y-m-d') ?? '',
            (string) (float) $task->progress_percent,
            (string) (float) $task->estimated_hours,
            (string) (float) $task->actual_hours,
            $this->serializeTags($task->getAttribute('tags')),
            $task->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Project> $query */
    /** @return array{path: string, count: int} */
    /** @phpstan-ignore missingType.generics, missingType.iterableValue */
    private function generateProjectCsv(\Illuminate\Database\Eloquent\Builder $query, string $filename): array
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
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
            ])->chunkById(500, function ($projects) use ($temp, &$exportedRowCount) {
                foreach ($projects as $project) {
                    /** @var Project $project */
                    $row = $this->projectCsvRow($project);
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

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: string, 9: string, 10: string, 11: string, 12: string, 13: string, 14: string} */
    private function projectCsvRow(Project $project): array
    {
        return [
            $project->id,
            $this->neutralizeTextualFormula((string) ($project->code ?? '')),
            $this->neutralizeTextualFormula((string) $project->name),
            $this->neutralizeTextualFormula((string) ($project->description ?? '')),
            $project->status,
            $project->getAttribute('priority'),
            (string) (float) $project->progress,
            (string) (float) $project->getAttribute('budget_total'),
            (string) (float) ($project->getAttribute('budget_planned') ?? 0),
            (string) (float) ($project->getAttribute('budget_actual') ?? 0),
            $project->start_date?->format('Y-m-d') ?? '',
            $project->end_date?->format('Y-m-d') ?? '',
            (string) (int) $project->getAttribute('tasks_count'),
            (string) (int) $project->getAttribute('completed_tasks_count'),
            $project->created_at?->format('Y-m-d H:i:s') ?? '',
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

    private function generateExcel($tasks, $filename)
    {
        // For now, just generate CSV with .xlsx extension
        // In production, you'd 
    }

    private function generateJson($tasks, $filename): string
    {
        $filePath = "exports/{$filename}";

        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $tasks->count(),
                'format' => 'json',
            ],
            'tasks' => $tasks->toArray(),
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }

    private function generateProjectsJson($projects, $filename): string
    {
        $filePath = "exports/{$filename}";

        $data = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_records' => $projects->count(),
                'format' => 'json',
            ],
            'projects' => $projects->toArray(),
        ];

        Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }
}
