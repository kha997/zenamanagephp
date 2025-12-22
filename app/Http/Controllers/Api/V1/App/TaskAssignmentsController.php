<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\AssignUsersToTaskRequest;
use App\Http\Requests\AssignTeamsToTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Task Assignments API Controller (V1)
 * 
 * Pure API controller for task assignment operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified TaskAssignmentController for API routes.
 */
class TaskAssignmentsController extends BaseApiV1Controller
{
    public function __construct(
        private TaskAssignmentService $assignmentService
    ) {}

    /**
     * Assign users to a task
     * 
     * @param AssignUsersToTaskRequest $request
     * @param Task $task
     * @return JsonResponse
     */
    public function assignUsers(AssignUsersToTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanUpdateTask($task)) {
                return $this->errorResponse('Unauthorized to assign users to this task', 403, null, 'FORBIDDEN');
            }
            
            $users = $request->input('users', []);
            
            $results = $this->assignmentService->assignUsersToTask(
                $task->id,
                $users,
                $tenantId
            );
            $skippedCount = count($results['skipped'] ?? []);
            $message = sprintf(
                'Assigned %d user(s) successfully. %d failed, %d skipped.',
                count($results['success']),
                count($results['failed']),
                $skippedCount
            );
            
            return $this->successResponse($results, $message);
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to assign users: ' . $e->getMessage(),
                500,
                null,
                'TASK_ASSIGN_USERS_FAILED'
            );
        }
    }

    /**
     * Remove a user from a task
     * 
     * @param Task $task
     * @param string $user
     * @return JsonResponse
     */
    public function removeUser(Request $request, Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanUpdateTask($task)) {
                return $this->errorResponse('Unauthorized to remove users from this task', 403, null, 'FORBIDDEN');
            }
            
            $routeUser = $request->route('user');
            $userIdInput = $request->input('user_id') ?? $request->query('user_id') ?? $routeUser;
            if ($userIdInput instanceof User) {
                $userIdInput = $userIdInput->id;
            }

            $userId = $userIdInput ? (string) $userIdInput : null;
            if (!$userId) {
                return $this->errorResponse('User identifier is required', 422, null, 'VALIDATION_FAILED');
            }

            $deleted = $this->assignmentService->removeUserFromTask(
                $task->id,
                $userId,
                $tenantId
            );

            if (!$deleted) {
                return $this->errorResponse(
                    'Assignment not found or already removed',
                    404,
                    null,
                    'TASK_ASSIGNMENT_NOT_FOUND'
                );
            }
            
            return $this->successResponse(null, 'User removed from task successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
                'user_id' => $user,
            ]);
            
            return $this->errorResponse(
                'Failed to remove user: ' . $e->getMessage(),
                500,
                null,
                'TASK_REMOVE_USER_FAILED'
            );
        }
    }

    /**
     * Get assigned users for a task
     * 
     * @param Task $task
     * @return JsonResponse
     */
    public function getUsers(Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanViewTask($task)) {
                return $this->errorResponse('Unauthorized to view task assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getAssignmentsForTask(
                $task->id,
                $tenantId
            );
            
            $userAssignments = $assignments->filter(fn($a) => $a->isUserAssignment());
            
            $formattedUsers = $userAssignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'user_name' => $assignment->user?->name,
                    'user_email' => $assignment->user?->email,
                    'role' => $assignment->role,
                    'assigned_hours' => $assignment->assigned_hours,
                    'actual_hours' => $assignment->actual_hours,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at?->toISOString(),
                ];
            });
            
            return $this->successResponse($formattedUsers, 'Task users retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve users: ' . $e->getMessage(),
                500,
                null,
                'TASK_GET_USERS_FAILED'
            );
        }
    }

    /**
     * Assign teams to a task
     * 
     * @param AssignTeamsToTaskRequest $request
     * @param Task $task
     * @return JsonResponse
     */
    public function assignTeams(AssignTeamsToTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanUpdateTask($task)) {
                return $this->errorResponse('Unauthorized to assign teams to this task', 403, null, 'FORBIDDEN');
            }
            
            $teams = $request->input('teams', []);
            
            $results = $this->assignmentService->assignTeamsToTask(
                $task->id,
                $teams,
                $tenantId
            );
            
            return $this->successResponse($results, 'Teams assigned successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to assign teams: ' . $e->getMessage(),
                500,
                null,
                'TASK_ASSIGN_TEAMS_FAILED'
            );
        }
    }

    /**
     * Remove a team from a task
     * 
     * @param Task $task
     * @param string $team
     * @return JsonResponse
     */
    public function removeTeam(Task $task, string $team): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanUpdateTask($task)) {
                return $this->errorResponse('Unauthorized to remove teams from this task', 403, null, 'FORBIDDEN');
            }
            
            $this->assignmentService->removeTeamFromTask(
                $task->id,
                $team,
                $tenantId
            );
            
            return $this->successResponse(null, 'Team removed from task successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
                'team_id' => $team,
            ]);
            
            return $this->errorResponse(
                'Failed to remove team: ' . $e->getMessage(),
                500,
                null,
                'TASK_REMOVE_TEAM_FAILED'
            );
        }
    }

    /**
     * Get teams assigned to a task
     * 
     * @param Task $task
     * @return JsonResponse
     */
    public function getTeams(Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanViewTask($task)) {
                return $this->errorResponse('Unauthorized to view task assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getAssignmentsForTask(
                $task->id,
                $tenantId
            );
            
            $teamAssignments = $assignments->filter(fn($a) => $a->isTeamAssignment());
            
            $formattedTeams = $teamAssignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'team_id' => $assignment->team_id,
                    'team_name' => $assignment->team?->name,
                    'role' => $assignment->role,
                    'assigned_hours' => $assignment->assigned_hours,
                    'actual_hours' => $assignment->actual_hours,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at?->toISOString(),
                ];
            });
            
            return $this->successResponse($formattedTeams, 'Task teams retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve teams: ' . $e->getMessage(),
                500,
                null,
                'TASK_GET_TEAMS_FAILED'
            );
        }
    }

    /**
     * Get all assignments for a task
     * 
     * @param Task $task
     * @return JsonResponse
     */
    public function index(Task $task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!$this->userCanViewTask($task)) {
                return $this->errorResponse('Unauthorized to view task assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getAssignmentsForTask(
                $task->id,
                $tenantId
            );
            
            return $this->successResponse($assignments, 'Task assignments retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $task->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve assignments: ' . $e->getMessage(),
                500,
                null,
                'TASK_GET_ASSIGNMENTS_FAILED'
            );
        }
    }

    private function userCanUpdateTask(Task $task): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ((string) $user->tenant_id !== (string) $task->tenant_id) {
            return false;
        }

        $userId = (string) $user->id;

        if ((string) $task->created_by === $userId) {
            return true;
        }

        if ((string) $task->assignee_id === $userId) {
            return true;
        }

        return ($user->role ?? null) === 'pm';
    }

    private function userCanViewTask(Task $task): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return (string) $user->tenant_id === (string) $task->tenant_id;
    }
}
