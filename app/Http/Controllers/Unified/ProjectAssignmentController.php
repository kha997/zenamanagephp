<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignUsersToProjectRequest;
use App\Http\Requests\AssignTeamsToProjectRequest;
use App\Http\Requests\SyncProjectUsersRequest;
use App\Http\Requests\SyncProjectTeamsRequest;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use App\Services\ProjectAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * ProjectAssignmentController
 * 
 * @deprecated This controller is deprecated. Use Api\V1\App\ProjectAssignmentsController for API routes.
 * This controller will be removed in a future version.
 * 
 * Controller for managing project assignments (users and teams)
 * Handles bulk operations, sync operations, and proper authorization
 */
class ProjectAssignmentController extends Controller
{
    protected ProjectAssignmentService $assignmentService;

    public function __construct(ProjectAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
        
        // Log deprecation warning
        Log::warning('Deprecated Unified controller called', [
            'controller' => static::class,
            'route' => request()->route()?->getName(),
            'traceId' => request()->header('X-Request-Id', uniqid('req_', true)),
            'message' => 'This controller is deprecated. Use Api\V1\App\ProjectAssignmentsController for API routes.'
        ]);
    }

    /**
     * Assign users to a project (single or bulk)
     * POST /api/v1/app/projects/{project}/assignments/users
     */
    public function assignUsers(AssignUsersToProjectRequest $request, Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('assignUsers', $project)) {
            return ApiResponse::error('Unauthorized to assign users to this project', 403);
        }
        
        try {
            $users = $request->input('users', []);
            $sync = $request->input('sync', false);
            
            if ($sync) {
                // Sync operation (replace all)
                $results = $this->assignmentService->syncProjectUsers(
                    $project->id,
                    $users,
                    $tenantId
                );
                
                return ApiResponse::success($results, 'Project users synced successfully');
            } else {
                // Bulk assign operation
                $results = $this->assignmentService->assignUsersToProject(
                    $project->id,
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
            }
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
     * Remove a user from a project
     * DELETE /api/v1/app/projects/{project}/assignments/users/{user}
     */
    public function removeUser(Project $project, string $user): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('removeUser', $project)) {
            return ApiResponse::error('Unauthorized to remove users from this project', 403);
        }
        
        try {
            $this->assignmentService->removeUserFromProject(
                $project->id,
                $user,
                $tenantId
            );
            
            return ApiResponse::success(null, 'User removed from project successfully');
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
     * Sync users to a project (replace all)
     * POST /api/v1/app/projects/{project}/assignments/users/sync
     */
    public function syncUsers(SyncProjectUsersRequest $request, Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('assignUsers', $project)) {
            return ApiResponse::error('Unauthorized to sync users for this project', 403);
        }
        
        try {
            $users = $request->input('users', []);
            
            $results = $this->assignmentService->syncProjectUsers(
                $project->id,
                $users,
                $tenantId
            );
            
            return ApiResponse::success($results, 'Project users synced successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to sync users: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Get assigned users for a project
     * GET /api/v1/app/projects/{project}/assignments/users
     */
    public function getUsers(Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - project members can view assignments
        if (!Gate::allows('view', $project)) {
            return ApiResponse::error('Unauthorized to view project assignments', 403);
        }
        
        try {
            $users = $this->assignmentService->getProjectUsers(
                $project->id,
                $tenantId
            );
            
            $formattedUsers = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->pivot->role_id ?? null,
                    'assigned_at' => $user->pivot->created_at?->toISOString()
                ];
            });
            
            return ApiResponse::success($formattedUsers, 'Project users retrieved successfully');
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
     * Assign teams to a project (single or bulk)
     * POST /api/v1/app/projects/{project}/assignments/teams
     */
    public function assignTeams(AssignTeamsToProjectRequest $request, Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('assignTeams', $project)) {
            return ApiResponse::error('Unauthorized to assign teams to this project', 403);
        }
        
        try {
            $teams = $request->input('teams', []);
            $sync = $request->input('sync', false);
            
            if ($sync) {
                // Sync operation (replace all)
                $results = $this->assignmentService->syncProjectTeams(
                    $project->id,
                    $teams,
                    $tenantId
                );
                
                return ApiResponse::success($results, 'Project teams synced successfully');
            } else {
                // Bulk assign operation
                $results = $this->assignmentService->assignTeamsToProject(
                    $project->id,
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
            }
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
     * Remove a team from a project
     * DELETE /api/v1/app/projects/{project}/assignments/teams/{team}
     */
    public function removeTeam(Project $project, string $team): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('removeTeam', $project)) {
            return ApiResponse::error('Unauthorized to remove teams from this project', 403);
        }
        
        try {
            $this->assignmentService->removeTeamFromProject(
                $project->id,
                $team,
                $tenantId
            );
            
            return ApiResponse::success(null, 'Team removed from project successfully');
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
     * Sync teams to a project (replace all)
     * POST /api/v1/app/projects/{project}/assignments/teams/sync
     */
    public function syncTeams(SyncProjectTeamsRequest $request, Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check
        if (!Gate::allows('assignTeams', $project)) {
            return ApiResponse::error('Unauthorized to sync teams for this project', 403);
        }
        
        try {
            $teams = $request->input('teams', []);
            
            $results = $this->assignmentService->syncProjectTeams(
                $project->id,
                $teams,
                $tenantId
            );
            
            return ApiResponse::success($results, 'Project teams synced successfully');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to sync teams: ' . $e->getMessage(),
                500,
                null,
                uniqid('err_', true)
            );
        }
    }

    /**
     * Get assigned teams for a project
     * GET /api/v1/app/projects/{project}/assignments/teams
     */
    public function getTeams(Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - project members can view assignments
        if (!Gate::allows('view', $project)) {
            return ApiResponse::error('Unauthorized to view project assignments', 403);
        }
        
        try {
            $teams = $this->assignmentService->getProjectTeams(
                $project->id,
                $tenantId
            );
            
            $formattedTeams = $teams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'role' => $team->pivot->role ?? null,
                    'joined_at' => $team->pivot->joined_at?->toISOString(),
                    'left_at' => $team->pivot->left_at?->toISOString()
                ];
            });
            
            return ApiResponse::success($formattedTeams, 'Project teams retrieved successfully');
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
     * Get all assignments for a project (users + teams)
     * GET /api/v1/app/projects/{project}/assignments
     */
    public function getAssignments(Project $project): JsonResponse
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        // Authorization check - project members can view assignments
        if (!Gate::allows('view', $project)) {
            return ApiResponse::error('Unauthorized to view project assignments', 403);
        }
        
        try {
            $assignments = $this->assignmentService->getProjectAssignments(
                $project->id,
                $tenantId
            );
            
            return ApiResponse::success($assignments, 'Project assignments retrieved successfully');
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

