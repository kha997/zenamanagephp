<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Task;

final class ExportTenantProjectionService
{
    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @param SupportCollection<array-key, Model>|null $projects
     * @return SupportCollection<array-key, array<string, mixed>>
     */
    public function projectTasks(SupportCollection $tasks, string $tenantId, ?SupportCollection $projects = null): SupportCollection
    {
        $watcherIds = $tasks->flatMap(function (Task $task): array {
            $watchers = $task->getAttribute('watchers');
            if (is_string($watchers)) {
                $decoded = json_decode($watchers, true);
                return is_array($decoded) ? $decoded : [];
            }

            return is_array($watchers) ? $watchers : [];
        })->filter();
        $validUserIds = $this->sameTenantUserIds(
            $tasks->pluck('assignee_id')
                ->merge($tasks->pluck('assigned_to'))
                ->merge($tasks->pluck('created_by'))
                ->merge($tasks->pluck('updated_by'))
                ->merge($watcherIds),
            $tenantId
        );

        $validComponentKeys = $this->sameProjectComponentKeys($tasks, $tenantId);
        $validPhaseKeys = $this->sameProjectPhaseKeys($tasks);
        $validDependencyKeys = $this->sameProjectTaskKeys($tasks, 'dependencies_json', $tenantId);
        $validParentKeys = $this->sameProjectTaskKeys($tasks, 'parent_id', $tenantId);
        $validWorkInstanceKeys = $this->sameProjectWorkInstanceKeys($tasks, $tenantId);
        $validWorkInstanceStepKeys = $this->sameProjectWorkInstanceStepKeys($tasks, $tenantId);

        $safeProjectScalars = ($projects
            ? $this->projectScalarRows($projects, $tenantId)
            : $this->projectScalarRows($tasks->pluck('project'), $tenantId))
            ->keyBy('id');

        /** @var SupportCollection<array-key, array<string, mixed>> $result */
        $result = $tasks->map(function (Task $task) use ($tenantId, $validUserIds, $validComponentKeys, $validPhaseKeys, $validDependencyKeys, $validParentKeys, $validWorkInstanceKeys, $validWorkInstanceStepKeys, $safeProjectScalars): array {
            $projectId = (string) $task->getAttribute('project_id');
            $taskId = (string) $task->getAttribute('id');

            $dependencies = array_values(array_filter(
                $task->getAttribute('dependencies_json') ?? [],
                fn ($id) => isset($validDependencyKeys[$projectId . '|' . (string) $id])
            ));

            $watchers = $task->getAttribute('watchers');
            if (is_string($watchers)) {
                $decoded = json_decode($watchers, true);
                if (is_array($decoded)) {
                    $watchers = array_values(array_filter($decoded, fn ($id) => isset($validUserIds[(string) $id])));
                    $watchers = json_encode($watchers) ?: '[]';
                } else {
                    $watchers = '[]';
                }
            } elseif (is_array($watchers)) {
                $watchers = array_values(array_filter($watchers, fn ($id) => isset($validUserIds[(string) $id])));
            } elseif ($watchers !== null) {
                $watchers = [];
            }

            $parentId = $task->getAttribute('parent_id');
            $validParent = $parentId !== null && $parentId !== '' ? isset($validParentKeys[$projectId . '|' . (string) $parentId]) : false;

            $workInstanceId = $task->getAttribute('work_instance_id');
            $validWorkInstance = $workInstanceId !== null && $workInstanceId !== '' ? isset($validWorkInstanceKeys[$projectId . '|' . (string) $workInstanceId]) : false;

            $workInstanceStepId = $task->getAttribute('work_instance_step_id');
            $validStep = false;
            if ($workInstanceStepId !== null && $workInstanceStepId !== '' && $validWorkInstance) {
                $validStep = isset($validWorkInstanceStepKeys[$projectId . '|' . (string) $workInstanceId . '|' . (string) $workInstanceStepId]);
            }

            return [
                'id' => $taskId,
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'project' => $safeProjectScalars[$projectId] ?? [
                    'id' => $projectId,
                    'tenant_id' => $tenantId,
                    'code' => null,
                    'name' => null,
                    'description' => null,
                    'status' => null,
                    'priority' => null,
                    'progress' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'budget_total' => null,
                    'budget_planned' => null,
                    'budget_actual' => null,
                    'actual_cost' => null,
                    'actual_hours' => null,
                    'estimated_hours' => null,
                    'completion_percentage' => null,
                    'risk_level' => null,
                    'is_template' => false,
                    'last_activity_at' => null,
                    'tags' => [],
                    'settings' => [],
                    'created_at' => null,
                    'updated_at' => null,
                    'deleted_at' => null,
                    'client_id' => null,
                    'pm_id' => null,
                    'created_by' => null,
                ],
                'name' => (string) $task->getAttribute('name'),
                'title' => $task->getAttribute('title') !== null ? (string) $task->getAttribute('title') : null,
                'description' => (string) $task->getAttribute('description'),
                'status' => (string) $task->getAttribute('status'),
                'priority' => (string) $task->getAttribute('priority'),
                'progress_percent' => (float) $task->getAttribute('progress_percent'),
                'estimated_hours' => (float) $task->getAttribute('estimated_hours'),
                'actual_hours' => (float) $task->getAttribute('actual_hours'),
                'start_date' => $task->getAttribute('start_date')?->format('Y-m-d'),
                'end_date' => $task->getAttribute('end_date')?->format('Y-m-d'),
                'is_hidden' => (bool) $task->getAttribute('is_hidden'),
                'visibility' => (string) $task->getAttribute('visibility'),
                'client_approved' => (bool) $task->getAttribute('client_approved'),
                'assignee_id' => isset($validUserIds[(string) ($task->getAttribute('assignee_id') ?? '')]) ? (string) $task->getAttribute('assignee_id') : null,
                'component_id' => isset($validComponentKeys[$projectId . '|' . (string) ($task->getAttribute('component_id') ?? '')]) ? (string) $task->getAttribute('component_id') : null,
                'phase_id' => isset($validPhaseKeys[$projectId . '|' . (string) ($task->getAttribute('phase_id') ?? '')]) ? (string) $task->getAttribute('phase_id') : null,
                'dependencies_json' => $dependencies,
                'assigned_to' => isset($validUserIds[(string) ($task->getAttribute('assigned_to') ?? '')]) ? (string) $task->getAttribute('assigned_to') : null,
                'created_by' => isset($validUserIds[(string) $task->getAttribute('created_by')]) ? (string) $task->getAttribute('created_by') : null,
                'updated_by' => isset($validUserIds[(string) $task->getAttribute('updated_by')]) ? (string) $task->getAttribute('updated_by') : null,
                'watchers' => $watchers,
                'parent_id' => $validParent ? (string) $parentId : null,
                'work_instance_id' => $validWorkInstance ? (string) $workInstanceId : null,
                'work_instance_step_id' => $validStep ? (string) $workInstanceStepId : null,
                'tags' => $task->getAttribute('tags') ?? [],
                'created_at' => $task->getAttribute('created_at')?->format('Y-m-d H:i:s'),
                'updated_at' => $task->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
            ];
        });

        return $result;
    }

    /**
     * @param SupportCollection<array-key, Model> $projects
     * @return SupportCollection<array-key, array<string, mixed>>
     */
    public function projectScalarRows(SupportCollection $projects, string $tenantId): SupportCollection
    {
        $validUserIds = $this->sameTenantUserIds(
            $projects->pluck('client_id')->merge($projects->pluck('pm_id'))->merge($projects->pluck('created_by')),
            $tenantId
        );

        /** @var SupportCollection<array-key, array<string, mixed>> $result */
        $result = $projects->map(function (Model $project) use ($tenantId, $validUserIds): array {
            return [
                'id' => (string) $project->getAttribute('id'),
                'tenant_id' => $tenantId,
                'code' => $project->getAttribute('code'),
                'name' => $project->getAttribute('name'),
                'description' => $project->getAttribute('description'),
                'status' => $project->getAttribute('status'),
                'priority' => $project->getAttribute('priority'),
                'progress' => (float) $project->getAttribute('progress'),
                'start_date' => $project->getAttribute('start_date')?->format('Y-m-d'),
                'end_date' => $project->getAttribute('end_date')?->format('Y-m-d'),
                'budget_total' => (float) $project->getAttribute('budget_total'),
                'budget_planned' => (float) ($project->getAttribute('budget_planned') ?? 0),
                'budget_actual' => (float) ($project->getAttribute('budget_actual') ?? 0),
                'actual_cost' => (float) ($project->getAttribute('actual_cost') ?? 0),
                'estimated_hours' => (float) ($project->getAttribute('estimated_hours') ?? 0),
                'actual_hours' => (float) ($project->getAttribute('actual_hours') ?? 0),
                'completion_percentage' => (float) ($project->getAttribute('completion_percentage') ?? 0),
                'risk_level' => $project->getAttribute('risk_level'),
                'is_template' => (bool) $project->getAttribute('is_template'),
                'last_activity_at' => $project->getAttribute('last_activity_at')?->format('Y-m-d H:i:s'),
                'tags' => $project->getAttribute('tags') ?? [],
                'settings' => $project->getAttribute('settings') ?? [],
                'created_at' => $project->getAttribute('created_at')?->format('Y-m-d H:i:s'),
                'updated_at' => $project->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
                'deleted_at' => $project->getAttribute('deleted_at')?->format('Y-m-d H:i:s'),
                'client_id' => isset($validUserIds[(string) ($project->getAttribute('client_id') ?? '')]) ? (string) $project->getAttribute('client_id') : null,
                'pm_id' => isset($validUserIds[(string) ($project->getAttribute('pm_id') ?? '')]) ? (string) $project->getAttribute('pm_id') : null,
                'created_by' => isset($validUserIds[(string) $project->getAttribute('created_by')]) ? (string) $project->getAttribute('created_by') : null,
            ];
        });

        return $result;
    }

    /**
     * @param SupportCollection<array-key, Model> $projects
     * @return SupportCollection<array-key, array<string, mixed>>
     */
    public function projectTabularRows(SupportCollection $projects, string $tenantId): SupportCollection
    {
        $scalarRows = $this->projectScalarRows($projects, $tenantId);

        /** @var SupportCollection<array-key, array<string, mixed>> $result */
        $result = $scalarRows->map(function (array $row) use ($projects): array {
            $project = $projects->firstWhere('id', $row['id']);

            $row['tasks_count'] = (int) ($project->getAttribute('tasks_count') ?? 0);
            $row['completed_tasks_count'] = (int) ($project->getAttribute('completed_tasks_count') ?? 0);

            return $row;
        });

        return $result;
    }

    /**
     * @param SupportCollection<array-key, Model> $projects
     * @param SupportCollection<array-key, Task> $tasks
     * @return SupportCollection<array-key, array<string, mixed>>
     */
    public function projectJsonRows(SupportCollection $projects, SupportCollection $tasks, string $tenantId): SupportCollection
    {
        $scalarRows = $this->projectScalarRows($projects, $tenantId);
        $safeTasks = $this->projectTasks($tasks, $tenantId, $projects);
        $tasksByProjectId = $safeTasks->groupBy('project_id');

        /** @var SupportCollection<array-key, array<string, mixed>> $result */
        $result = $scalarRows->map(function (array $row) use ($tasksByProjectId): array {
            $projectTasks = $tasksByProjectId->get($row['id'], collect([]));
            $row['tasks'] = $projectTasks->values()->all();

            return $row;
        });

        return $result;
    }

    /**
     * @param SupportCollection<array-key, mixed> $candidateIds
     * @return array<string, true>
     */
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

    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @return array<string, true> keys are "project_id|component_id"
     */
    private function sameProjectComponentKeys(SupportCollection $tasks, string $tenantId): array
    {
        $pairs = [];
        foreach ($tasks as $task) {
            $projectId = (string) $task->getAttribute('project_id');
            $componentId = (string) $task->getAttribute('component_id');
            if ($projectId !== '' && $componentId !== '') {
                $pairs[] = [$projectId, $componentId];
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $componentIds = array_unique(array_column($pairs, 1));

        $valid = DB::table('components')
            ->whereIn('id', $componentIds)
            ->where('tenant_id', $tenantId)
            ->get(['id', 'project_id'])
            ->map(fn ($row) => (string) $row->project_id . '|' . (string) $row->id)
            ->all();

        return array_combine($valid, array_fill(0, count($valid), true)) ?: [];
    }

    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @return array<string, true> keys are "project_id|phase_id"
     */
    private function sameProjectPhaseKeys(SupportCollection $tasks): array
    {
        $pairs = [];
        foreach ($tasks as $task) {
            $projectId = (string) $task->getAttribute('project_id');
            $phaseId = (string) $task->getAttribute('phase_id');
            if ($projectId !== '' && $phaseId !== '') {
                $pairs[] = [$projectId, $phaseId];
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $phaseIds = array_unique(array_column($pairs, 1));

        $valid = DB::table('project_phases')
            ->whereIn('id', $phaseIds)
            ->get(['id', 'project_id'])
            ->map(fn ($row) => (string) $row->project_id . '|' . (string) $row->id)
            ->all();

        return array_combine($valid, array_fill(0, count($valid), true)) ?: [];
    }

    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @param string $attributeName
     * @param string $tenantId
     * @return array<string, true> keys are "project_id|task_id"
     */
    private function sameProjectTaskKeys(SupportCollection $tasks, string $attributeName, string $tenantId): array
    {
        $pairs = [];
        $rawIds = [];
        foreach ($tasks as $task) {
            $projectId = (string) $task->getAttribute('project_id');
            $value = $task->getAttribute($attributeName);
            if ($attributeName === 'dependencies_json') {
                if (!is_array($value)) {
                    continue;
                }
                foreach ($value as $id) {
                    $id = (string) $id;
                    if ($id !== '') {
                        $pairs[] = [$projectId, $id];
                        $rawIds[] = $id;
                    }
                }
            } else {
                $id = (string) ($value ?? '');
                if ($id !== '') {
                    $pairs[] = [$projectId, $id];
                    $rawIds[] = $id;
                }
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $rawIds = array_unique($rawIds);

        $valid = DB::table('tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->whereIn('t.id', $rawIds)
            ->where('t.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->get(['t.id', 'p.id as project_id'])
            ->map(fn ($row) => (string) $row->project_id . '|' . (string) $row->id)
            ->all();

        return array_combine($valid, array_fill(0, count($valid), true)) ?: [];
    }

    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @param string $tenantId
     * @return array<string, true> keys are "project_id|work_instance_id"
     */
    private function sameProjectWorkInstanceKeys(SupportCollection $tasks, string $tenantId): array
    {
        $pairs = [];
        $rawIds = [];
        foreach ($tasks as $task) {
            $projectId = (string) $task->getAttribute('project_id');
            $workInstanceId = (string) $task->getAttribute('work_instance_id');
            if ($projectId !== '' && $workInstanceId !== '') {
                $pairs[] = [$projectId, $workInstanceId];
                $rawIds[] = $workInstanceId;
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $rawIds = array_unique($rawIds);

        $valid = DB::table('work_instances as wi')
            ->join('projects as p', 'p.id', '=', 'wi.project_id')
            ->whereIn('wi.id', $rawIds)
            ->where('wi.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->get(['wi.id', 'wi.project_id'])
            ->map(fn ($row) => (string) $row->project_id . '|' . (string) $row->id)
            ->all();

        return array_combine($valid, array_fill(0, count($valid), true)) ?: [];
    }

    /**
     * @param SupportCollection<array-key, Task> $tasks
     * @param string $tenantId
     * @return array<string, true> keys are "project_id|work_instance_id|step_id"
     */
    private function sameProjectWorkInstanceStepKeys(SupportCollection $tasks, string $tenantId): array
    {
        $workInstanceIds = [];
        $stepIds = [];
        foreach ($tasks as $task) {
            $workInstanceId = (string) $task->getAttribute('work_instance_id');
            $stepId = (string) $task->getAttribute('work_instance_step_id');
            if ($workInstanceId !== '' && $stepId !== '') {
                $workInstanceIds[] = $workInstanceId;
                $stepIds[] = $stepId;
            }
        }

        if (empty($stepIds)) {
            return [];
        }

        $workInstanceIds = array_unique($workInstanceIds);
        $stepIds = array_unique($stepIds);

        $valid = DB::table('work_instance_steps as s')
            ->join('work_instances as wi', 'wi.id', '=', 's.work_instance_id')
            ->whereIn('s.id', $stepIds)
            ->where('s.tenant_id', $tenantId)
            ->where('wi.tenant_id', $tenantId)
            ->whereIn('wi.id', $workInstanceIds)
            ->get(['s.id', 'wi.project_id', 's.work_instance_id'])
            ->map(fn ($row) => (string) $row->project_id . '|' . (string) $row->work_instance_id . '|' . (string) $row->id)
            ->all();

        return array_combine($valid, array_fill(0, count($valid), true)) ?: [];
    }
}
