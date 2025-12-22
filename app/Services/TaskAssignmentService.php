<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\Team;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Uid\Ulid;

/**
 * Task Assignment Service - Business logic for task assignment management
 */
class TaskAssignmentService
{
    /**
     * Get assignments for a task
     */
    public function getAssignmentsForTask(string $taskId): Collection
    {
        return TaskAssignment::where('task_id', $taskId)
            ->with('user')
            ->get();
    }

    /**
     * Get assignments for a user within an optional tenant scope and filters.
     */
    public function getAssignmentsForUser(string|Ulid $userId, string|Ulid|null $tenantId = null, array $filters = []): Collection
    {
        $normalizedUserId = self::normalizeId($userId);
        $tenantKey = self::normalizeNullableId($tenantId);

        $query = TaskAssignment::where('user_id', $normalizedUserId)
            ->when($tenantKey, fn ($query) => $query->where('tenant_id', $tenantKey))
            ->with(['task.project', 'task.component']);

        if (!empty($filters['status'])) {
            $query->whereHas('task', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        if (!empty($filters['project_id'])) {
            $query->whereHas('task', function ($q) use ($filters) {
                $q->where('project_id', $filters['project_id']);
            });
        }

        return $query->get();
    }

    /**
     * Get assignments grouped by type for a task and tenant.
     */
    public function getTaskAssignments(string|Ulid $taskId, string|Ulid $tenantId): array
    {
        $normalizedTaskId = self::normalizeId($taskId);
        $normalizedTenantId = self::normalizeId($tenantId);

        $assignments = TaskAssignment::where('task_id', $normalizedTaskId)
            ->where('tenant_id', $normalizedTenantId)
            ->get();

        return [
            'users' => $assignments
                ->where('assignment_type', TaskAssignment::TYPE_USER)
                ->values()
                ->toArray(),
            'teams' => $assignments
                ->where('assignment_type', TaskAssignment::TYPE_TEAM)
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Get assignments for a team within a tenant.
     */
    public function getAssignmentsForTeam(string|Ulid $teamId, string|Ulid $tenantId): Collection
    {
        $normalizedTeamId = self::normalizeId($teamId);
        $normalizedTenantId = self::normalizeId($tenantId);

        return TaskAssignment::where('team_id', $normalizedTeamId)
            ->where('tenant_id', $normalizedTenantId)
            ->where('assignment_type', TaskAssignment::TYPE_TEAM)
            ->with('task')
            ->get();
    }

    /**
     * Create a new task assignment
     */
    public function createAssignment(array $data): TaskAssignment
    {
        $assignmentType = $data['assignment_type'] ?? TaskAssignment::TYPE_USER;
        $tenantId = self::normalizeNullableId($data['tenant_id'] ?? null);
        $taskId = self::normalizeId($data['task_id']);

        try {
            DB::beginTransaction();

            $taskQuery = Task::where('id', $taskId);
            if ($tenantId) {
                $taskQuery->where('tenant_id', $tenantId);
            }

            $task = $taskQuery->first();
            if (!$task) {
                throw new \Exception($tenantId ? 'Task not found or tenant mismatch' : 'Task not found');
            }

            $assignmentUserId = null;

            if ($assignmentType === TaskAssignment::TYPE_USER) {
                $userId = self::normalizeId($data['user_id']);
                $userQuery = User::where('id', $userId);
                if ($tenantId) {
                    $userQuery->where('tenant_id', $tenantId);
                }

                $user = $userQuery->first();
                if (!$user) {
                    throw new \Exception($tenantId ? 'User not found or tenant mismatch' : 'User not found');
                }

                $existingAssignment = TaskAssignment::where('task_id', $taskId)
                    ->where('user_id', $userId)
                    ->where('assignment_type', TaskAssignment::TYPE_USER)
                    ->first();
                $assignmentUserId = $userId;
            } else {
                $teamId = self::normalizeId($data['team_id']);
                $teamQuery = Team::where('id', $teamId);
                if ($tenantId) {
                    $teamQuery->where('tenant_id', $tenantId);
                }

                $team = $teamQuery->first();
                if (!$team) {
                    throw new \Exception($tenantId ? 'Team not found or tenant mismatch' : 'Team not found');
                }
                $assignmentUserId = $team->team_lead_id ?? $team->created_by;
                if (!$assignmentUserId) {
                    throw new \Exception('Cannot assign team without a fallback user');
                }

                $existingAssignment = TaskAssignment::where('task_id', $taskId)
                    ->where('team_id', $teamId)
                    ->where('assignment_type', TaskAssignment::TYPE_TEAM)
                    ->first();
            }

            if ($existingAssignment) {
                throw new \Exception('Assignment already exists');
            }

            $payload = [
                'task_id' => $taskId,
                'assignment_type' => $assignmentType,
                'role' => $data['role'] ?? TaskAssignment::ROLE_ASSIGNEE,
                'tenant_id' => $tenantId ?? $task->tenant_id,
                'assigned_at' => $data['assigned_at'] ?? now(),
                'user_id' => $assignmentUserId,
            ];

            if ($assignmentType === TaskAssignment::TYPE_TEAM) {
                $payload['team_id'] = $teamId;
            } else {
                $payload['team_id'] = null;
            }

            $assignment = TaskAssignment::forceCreate($payload);

            DB::commit();

            return $assignment->load(['user', 'team', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignment creation failed', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Assign a single user to a task within a tenant.
     */
    public function assignUserToTask(string|Ulid $taskId, string|Ulid $userId, string|Ulid $tenantId): TaskAssignment
    {
        return $this->createAssignment([
            'task_id' => $taskId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'assignment_type' => TaskAssignment::TYPE_USER,
        ]);
    }

    /**
     * Assign multiple users to a task and return success/failure buckets.
     */
    public function assignUsersToTask(string|Ulid $taskId, array $assignments, string|Ulid $tenantId): array
    {
        $results = ['success' => [], 'failed' => [], 'skipped' => []];

        foreach ($assignments as $assignmentData) {
            try {
                $payload = array_merge($assignmentData, [
                    'task_id' => $taskId,
                    'tenant_id' => $tenantId,
                    'assignment_type' => TaskAssignment::TYPE_USER,
                ]);

                $results['success'][] = $this->createAssignment($payload);
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'user_id' => $assignmentData['user_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Assign a single team to a task within a tenant.
     */
    public function assignTeamToTask(string|Ulid $taskId, string|Ulid $teamId, string|Ulid $tenantId): TaskAssignment
    {
        return $this->createAssignment([
            'task_id' => $taskId,
            'team_id' => $teamId,
            'tenant_id' => $tenantId,
            'assignment_type' => TaskAssignment::TYPE_TEAM,
        ]);
    }

    /**
     * Assign multiple teams to a task and return success/failure buckets.
     */
    public function assignTeamsToTask(string|Ulid $taskId, array $assignments, string|Ulid $tenantId): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($assignments as $assignmentData) {
            try {
                $payload = array_merge($assignmentData, [
                    'task_id' => $taskId,
                    'tenant_id' => $tenantId,
                    'assignment_type' => TaskAssignment::TYPE_TEAM,
                ]);

                $results['success'][] = $this->createAssignment($payload);
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'team_id' => $assignmentData['team_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Remove a user from a task within a tenant.
     */
    public function removeUserFromTask(string|Ulid $taskId, string|Ulid $userId, string|Ulid $tenantId): bool
    {
        $normalizedTaskId = self::normalizeId($taskId);
        $normalizedUserId = self::normalizeId($userId);
        $normalizedTenantId = self::normalizeId($tenantId);

        $query = TaskAssignment::where('task_id', $normalizedTaskId)
            ->where('user_id', $normalizedUserId)
            ->where('assignment_type', TaskAssignment::TYPE_USER);

        if ($normalizedTenantId) {
            $query->where(function ($subQuery) use ($normalizedTenantId) {
                $subQuery->where('tenant_id', $normalizedTenantId)
                         ->orWhereNull('tenant_id');
            });
        }

        return $query->delete() > 0;
    }

    /**
     * Remove a team from a task within a tenant.
     */
    public function removeTeamFromTask(string|Ulid $taskId, string|Ulid $teamId, string|Ulid $tenantId): bool
    {
        $normalizedTaskId = self::normalizeId($taskId);
        $normalizedTeamId = self::normalizeId($teamId);
        $normalizedTenantId = self::normalizeId($tenantId);

        return TaskAssignment::where('task_id', $normalizedTaskId)
            ->where('team_id', $normalizedTeamId)
            ->where('assignment_type', TaskAssignment::TYPE_TEAM)
            ->where('tenant_id', $normalizedTenantId)
            ->delete() > 0;
    }

    /**
     * Update an existing assignment
     */
    public function updateAssignment(string $assignmentId, array $data): ?TaskAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = TaskAssignment::find($assignmentId);
            if (!$assignment) {
                return null;
            }

            $assignment->update([
                'split_percent' => $data['split_percent'] ?? $assignment->split_percent,
                'role' => $data['role'] ?? $assignment->role,
            ]);

            DB::commit();

            return $assignment->load(['user', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignment update failed', [
                'assignment_id' => $assignmentId,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete an assignment
     */
    public function deleteAssignment(string $assignmentId): bool
    {
        try {
            $assignment = TaskAssignment::find($assignmentId);
            if (!$assignment) {
                return false;
            }

            $assignment->delete();

            return true;

        } catch (\Exception $e) {
            Log::error('Task assignment deletion failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Assign multiple users to a task
     */
    public function assignMultipleUsers(string $taskId, array $assignments): Collection
    {
        try {
            DB::beginTransaction();

            $createdAssignments = collect();

            foreach ($assignments as $assignmentData) {
                $assignmentData['task_id'] = $taskId;
                $assignment = $this->createAssignment($assignmentData);
                $createdAssignments->push($assignment);
            }

            DB::commit();

            return $createdAssignments;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Multiple user assignment failed', [
                'task_id' => $taskId,
                'assignments' => $assignments,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update all assignments for a task
     */
    public function updateTaskAssignments(string $taskId, array $assignments): Collection
    {
        try {
            DB::beginTransaction();

            // Delete existing assignments
            TaskAssignment::where('task_id', $taskId)->delete();

            // Create new assignments
            $createdAssignments = collect();
            foreach ($assignments as $assignmentData) {
                $assignmentData['task_id'] = $taskId;
                $assignment = TaskAssignment::create($assignmentData);
                $createdAssignments->push($assignment);
            }

            DB::commit();

            return $createdAssignments->load(['user', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignments update failed', [
                'task_id' => $taskId,
                'assignments' => $assignments,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get assignment statistics for a user
     */
    public function getUserAssignmentStats(string $userId): array
    {
        $assignments = TaskAssignment::where('user_id', $userId)
            ->with('task')
            ->get();

        $stats = [
            'total_assignments' => $assignments->count(),
            'by_status' => $assignments->groupBy('task.status')->map->count(),
            'by_priority' => $assignments->groupBy('task.priority')->map->count(),
            'total_split_percent' => $assignments->sum('split_percent'),
            'by_project' => $assignments->groupBy('task.project_id')->map->count(),
        ];

        return $stats;
    }

    /**
     * Get assignment statistics for a task
     */
    public function getTaskAssignmentStats(string $taskId): array
    {
        $assignments = TaskAssignment::where('task_id', $taskId)
            ->with('user')
            ->get();

        $stats = [
            'total_assignments' => $assignments->count(),
            'total_split_percent' => $assignments->sum('split_percent'),
            'by_role' => $assignments->groupBy('role')->map->count(),
            'assigned_users' => $assignments->pluck('user.name')->toArray(),
        ];

        return $stats;
    }

    /**
     * Check if user is assigned to task
     */
    public function isUserAssignedToTask(string|Ulid $userId, string|Ulid $taskId, string|Ulid|null $tenantId = null): bool
    {
        $normalizedUserId = self::normalizeId($userId);
        $normalizedTaskId = self::normalizeId($taskId);
        $tenantKey = self::normalizeNullableId($tenantId);

        $query = TaskAssignment::where('user_id', $normalizedUserId)
            ->where('task_id', $normalizedTaskId)
            ->where('assignment_type', TaskAssignment::TYPE_USER);

        if ($tenantKey) {
            $query->where('tenant_id', $tenantKey);
        }

        return $query->exists();
    }

    /**
     * Check if team is assigned to task
     */
    public function isTeamAssignedToTask(string|Ulid $teamId, string|Ulid $taskId, string|Ulid|null $tenantId = null): bool
    {
        $normalizedTeamId = self::normalizeId($teamId);
        $normalizedTaskId = self::normalizeId($taskId);
        $tenantKey = self::normalizeNullableId($tenantId);

        $query = TaskAssignment::where('team_id', $normalizedTeamId)
            ->where('task_id', $normalizedTaskId)
            ->where('assignment_type', TaskAssignment::TYPE_TEAM);

        if ($tenantKey) {
            $query->where('tenant_id', $tenantKey);
        }

        return $query->exists();
    }

    /**
     * Get user's workload (total split percentage)
     */
    public function getUserWorkload(string $userId): float
    {
        return TaskAssignment::where('user_id', $userId)
            ->whereHas('task', function ($q) {
                $q->whereIn('status', ['pending', 'in_progress']);
            })
            ->sum('split_percent');
    }

    /**
     * Normalize an identifier to string.
     *
     * @param string|Ulid $value
     * @return string
     */
    private static function normalizeId(string|Ulid $value): string
    {
        return (string) $value;
    }

    /**
     * Normalize a nullable identifier to string.
     *
     * @param string|Ulid|null $value
     * @return string|null
     */
    private static function normalizeNullableId(string|Ulid|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
