<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUsersToTaskRequest;
use App\Http\Requests\AssignTeamsToTaskRequest;
use App\Models\Task;
use App\Policies\TaskPolicy;
use App\Services\TaskAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * TaskAssignmentController
 * 
 * @deprecated This controller is deprecated. Use Api\V1\App\TaskAssignmentsController for API routes.
 * This controller will be removed in a future version.
 * 
 * Controller for managing task assignments (users and teams)
 * Handles bulk operations and proper authorization
 */
class TaskAssignmentController extends Controller
{
    protected TaskAssignmentService $assignmentService;

    public function __construct(TaskAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
        
        // Log deprecation warning
        Log::warning('Deprecated Unified controller called', [
            'controller' => static::class,
            'route' => request()->route()?->getName(),
            'traceId' => request()->header('X-Request-Id', uniqid('req_', true)),
            'message' => 'This controller is deprecated. Use Api\V1\App\TaskAssignmentsController for API routes.'
        ]);
    }

    /**
     * Assign users to a task (bulk operation)
     * POST /api/v1/app/tasks/{task}/assignments/users
     */
    public function assignUsers(AssignUsersToTaskRequest $request, Task $task): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('update', $task)) {
            return ApiResponse::error('Unauthorized to assign users to this task', 403);
        }
        
        try {
            $users = $request->input('users', []);
            
            $results = $this->assignmentService->assignUsersToTask(
                $task->id,
                $users,
                $tenantId
            );
            
            $message = sprintf(
                'Assigned %d user(s) successfully. %d failed, %d skipped.',
                count($results['success']),
                count($results['failed']),
                count($results['skipped'])
            );
            
            return ApiResponse::success($results, $message);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to assign users: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Remove a user from a task
     * DELETE /api/v1/app/tasks/{task}/assignments/users/{user}
     */
    public function removeUser(Task $task, string $user): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('update', $task)) {
            return ApiResponse::error('Unauthorized to remove users from this task', 403);
        }
        
        try {
            $this->assignmentService->removeUserFromTask(
                $task->id,
                $user,
                $tenantId
            );
            
            return ApiResponse::success(null, 'User removed from task successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to remove user: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Get assigned users for a task
     * GET /api/v1/app/tasks/{task}/assignments/users
     */
    public function getUsers(Task $task): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - task viewers can view assignments
        if (!Gate::allows('view', $task)) {
            return ApiResponse::error('Unauthorized to view task assignments', 403);
        }
        
        try {
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
            
            return ApiResponse::success($formattedUsers, 'Task users retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve users: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Assign teams to a task (bulk operation)
     * POST /api/v1/app/tasks/{task}/assignments/teams
     */
    public function assignTeams(AssignTeamsToTaskRequest $request, Task $task): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('update', $task)) {
            return ApiResponse::error('Unauthorized to assign teams to this task', 403);
        }
        
        try {
            $teams = $request->input('teams', []);
            
            $results = $this->assignmentService->assignTeamsToTask(
                $task->id,
                $teams,
                $tenantId
            );
            
            $message = sprintf(
                'Assigned %d team(s) successfully. %d failed, %d skipped.',
                count($results['success']),
                count($results['failed']),
                count($results['skipped'])
            );
            
            return ApiResponse::success($results, $message);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to assign teams: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Remove a team from a task
     * DELETE /api/v1/app/tasks/{task}/assignments/teams/{team}
     */
    public function removeTeam(Task $task, string $team): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('update', $task)) {
            return ApiResponse::error('Unauthorized to remove teams from this task', 403);
        }
        
        try {
            $this->assignmentService->removeTeamFromTask(
                $task->id,
                $team,
                $tenantId
            );
            
            return ApiResponse::success(null, 'Team removed from task successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to remove team: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Get assigned teams for a task
     * GET /api/v1/app/tasks/{task}/assignments/teams
     */
    public function getTeams(Task $task): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - task viewers can view assignments
        if (!Gate::allows('view', $task)) {
            return ApiResponse::error('Unauthorized to view task assignments', 403);
        }
        
        try {
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
                    'team_description' => $assignment->team?->description,
                    'role' => $assignment->role,
                    'assigned_hours' => $assignment->assigned_hours,
                    'actual_hours' => $assignment->actual_hours,
                    'status' => $assignment->status,
                    'assigned_at' => $assignment->assigned_at?->toISOString(),
                ];
            });
            
            return ApiResponse::success($formattedTeams, 'Task teams retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve teams: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Get all assignments for a task (users + teams)
     * GET /api/v1/app/tasks/{task}/assignments
     */
    public function getAssignments(Task $task): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - task viewers can view assignments
        if (!Gate::allows('view', $task)) {
            return ApiResponse::error('Unauthorized to view task assignments', 403);
        }
        
        try {
            $assignments = $this->assignmentService->getTaskAssignments(
                $task->id,
                $tenantId
            );
            
            return ApiResponse::success($assignments, 'Task assignments retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve assignments: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }
}

