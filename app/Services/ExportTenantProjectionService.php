<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

final class ExportTenantProjectionService
{
    /** @return SupportCollection<int, array<string, mixed>> */
    public function projectTasks(SupportCollection $tasks, string $tenantId, ?SupportCollection $projects = null): SupportCollection
    {
        $validUserIds = $this->sameTenantUserIds(
            $tasks->pluck('assignee_id')->merge($tasks->pluck('assigned_to'))->merge($tasks->pluck('created_by'))->merge($tasks->pluck('updated_by')),
            $tenantId
        );

        $validComponentIds = $this->sameProjectComponentIds($tasks);
        $validPhaseIds = $this->sameProjectPhaseIds($tasks);
        $eligibleTaskIds = $this->eligibleReferencedTaskIds($tasks, $tenantId);
        $validWorkInstanceIds = $this->validWorkInstanceIds($tasks->pluck('work_instance_id'), $tenantId);
        $validWorkInstanceStepIds = $this->validWorkInstanceStepIds($tasks->pluck('work_instance_step_id'), $tenantId, $validWorkInstanceIds);

        $safeProjectScalars = $projects
            ? $this->projectScalarRows($projects, $tenantId)
            : $this->projectScalarRows($tasks->pluck('project'), $tenantId);

        return $tasks->map(function (Task $task) use ($tenantId, $validUserIds, $validComponentIds, $validPhaseIds, $eligibleTaskIds, $validWorkInstanceIds, $validWorkInstanceStepIds, $safeProjectScalars): array {
            $dependencies = array_values(array_filter(
                $task->dependencies_json ?? [],
                fn ($id) => isset($eligibleTaskIds[(string) $id])
            ));

            $watchers = $task->watchers ?? [];
            if (!is_array($watchers)) {
                $watchers = [];
            }
            $watchers = array_values(array_filter($watchers, fn ($id) => isset($validUserIds[(string) $id])));

            return [
                'id' => (string) $task->id,
                'tenant_id' => $tenantId,
                'project_id' => (string) $task->project_id,
                'project' => $safeProjectScalars[(string) $task->project_id] ?? [
                    'id' => (string) $task->project_id,
                    'tenant_id' => $tenantId,
                    'code' => null,
                    'name' => null,
                    'description' => null,
                    'status' => null,
                    'priority' => null,
                    'progress' => null,
                    'budget_total' => null,
                    'budget_planned' => null,
                    'budget_actual' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'client_id' => null,
                    'pm_id' => null,
                    'created_by' => null,
                    'tags' => [],
                    'settings' => [],
                ],
                'name' => $task->name,
                'title' => $task->title ?? null,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'progress_percent' => (float) $task->progress_percent,
                'estimated_hours' => (float) $task->estimated_hours,
                'actual_hours' => (float) $task->actual_hours,
                'start_date' => $task->start_date?->format('Y-m-d'),
                'end_date' => $task->end_date?->format('Y-m-d'),
                'is_hidden' => (bool) $task->is_hidden,
                'visibility' => $task->visibility,
                'client_approved' => (bool) $task->client_approved,
                'assignee_id' => isset($validUserIds[(string) $task->assignee_id]) ? (string) $task->assignee_id : null,
                'component_id' => isset($validComponentIds[(string) $task->component_id]) ? (string) $task->component_id : null,
                'phase_id' => isset($validPhaseIds[(string) $task->phase_id]) ? (string) $task->phase_id : null,
                'dependencies_json' => $dependencies,
                'assigned_to' => isset($validUserIds[(string) ($task->assigned_to ?? '')]) ? (string) $task->assigned_to : null,
                'created_by' => isset($validUserIds[(string) $task->created_by]) ? (string) $task->created_by : null,
                'updated_by' => isset($validUserIds[(string) $task->updated_by]) ? (string) $task->updated_by : null,
                'watchers' => $watchers,
                'parent_id' => isset($eligibleTaskIds[(string) ($task->parent_id ?? '')]) ? (string) $task->parent_id : null,
                'work_instance_id' => isset($validWorkInstanceIds[(string) ($task->work_instance_id ?? '')]) ? (string) $task->work_instance_id : null,
                'work_instance_step_id' => isset($validWorkInstanceStepIds[(string) ($task->work_instance_step_id ?? '')]) ? (string) $task->work_instance_step_id : null,
                'tags' => $task->tags ?? [],
                'created_at' => $task->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $task->updated_at?->format('Y-m-d H:i:s'),
            ];
        });
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function projectScalarRows(SupportCollection $projects, string $tenantId): SupportCollection
    {
        $validUserIds = $this->sameTenantUserIds(
            $projects->pluck('client_id')->merge($projects->pluck('pm_id'))->merge($projects->pluck('created_by')),
            $tenantId
        );

        return $projects->map(function (Project $project) use ($tenantId, $validUserIds): array {
            return [
                'id' => (string) $project->id,
                'tenant_id' => $tenantId,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'priority' => $project->priority,
                'progress' => (float) $project->progress,
                'budget_total' => (float) $project->budget_total,
                'budget_planned' => (float) ($project->budget_planned ?? 0),
                'budget_actual' => (float) ($project->budget_actual ?? 0),
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'client_id' => isset($validUserIds[(string) $project->client_id]) ? (string) $project->client_id : null,
                'pm_id' => isset($validUserIds[(string) $project->pm_id]) ? (string) $project->pm_id : null,
                'created_by' => isset($validUserIds[(string) $project->created_by]) ? (string) $project->created_by : null,
                'tags' => $project->tags ?? [],
                'settings' => $project->settings ?? [],
            ];
        });
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function projectTabularRows(SupportCollection $projects, string $tenantId): SupportCollection
    {
        $scalarRows = $this->projectScalarRows($projects, $tenantId);

        return $scalarRows->map(function (array $row) use ($projects): array {
            $project = $projects->firstWhere('id', $row['id']);

            $row['tasks_count'] = (int) ($project->tasks_count ?? 0);
            $row['completed_tasks_count'] = (int) ($project->completed_tasks_count ?? 0);

            return $row;
        });
    }

    /** @return SupportCollection<int, array<string, mixed>> */
    public function projectJsonRows(SupportCollection $projects, SupportCollection $tasks, string $tenantId): SupportCollection
    {
        $scalarRows = $this->projectScalarRows($projects, $tenantId);
        $safeTasks = $this->projectTasks($tasks, $tenantId, $projects);
        $tasksByProjectId = $safeTasks->groupBy('project_id');

        return $scalarRows->map(function (array $row) use ($tasksByProjectId): array {
            $projectTasks = $tasksByProjectId->get($row['id'], collect([]));
            $row['tasks'] = $projectTasks->values()->all();

            return $row;
        });
    }

    /** @return array<string, true> */
    private function sameTenantUserIds(SupportCollection $candidateIds, string $tenantId): array
    {
        $ids = $candidateIds
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $validIds = DB::table('users')
            ->whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }

    /** @return array<string, true> */
    private function sameProjectComponentIds(SupportCollection $tasks): array
    {
        $projectIds = $tasks->pluck('project_id')->filter()->unique()->values()->all();
        $componentIds = $tasks->pluck('component_id')->filter()->unique()->values()->all();

        if (empty($projectIds) || empty($componentIds)) {
            return [];
        }

        $validIds = DB::table('components')
            ->whereIn('id', $componentIds)
            ->whereIn('project_id', $projectIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }

    /** @return array<string, true> */
    private function sameProjectPhaseIds(SupportCollection $tasks): array
    {
        $projectIds = $tasks->pluck('project_id')->filter()->unique()->values()->all();
        $phaseIds = $tasks->pluck('phase_id')->filter()->unique()->values()->all();

        if (empty($projectIds) || empty($phaseIds)) {
            return [];
        }

        $validIds = DB::table('project_phases')
            ->whereIn('id', $phaseIds)
            ->whereIn('project_id', $projectIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }

    /** @return array<string, true> */
    private function eligibleReferencedTaskIds(SupportCollection $tasks, string $tenantId): array
    {
        $candidateIds = $tasks->pluck('dependencies_json')
            ->flatten()
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($candidateIds)) {
            return [];
        }

        $validIds = DB::table('tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->whereIn('t.id', $candidateIds)
            ->where('t.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->pluck('t.id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }

    /** @return array<string, true> */
    private function validWorkInstanceIds(SupportCollection $candidateIds, string $tenantId): array
    {
        $ids = $candidateIds->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        $validIds = DB::table('work_instances')
            ->whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }

    /** @return array<string, true> */
    private function validWorkInstanceStepIds(SupportCollection $candidateIds, string $tenantId, array $validWorkInstanceIds): array
    {
        $ids = $candidateIds->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

        if (empty($ids) || empty($validWorkInstanceIds)) {
            return [];
        }

        $validIds = DB::table('work_instance_steps as wis')
            ->join('work_instances as wi', 'wi.id', '=', 'wis.work_instance_id')
            ->whereIn('wis.id', $ids)
            ->where('wi.tenant_id', $tenantId)
            ->pluck('wis.id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_combine($validIds, array_fill(0, count($validIds), true)) ?: [];
    }
}
