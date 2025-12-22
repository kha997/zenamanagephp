<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\AssignUsersToProjectRequest;
use App\Http\Requests\AssignTeamsToProjectRequest;
use App\Http\Requests\SyncProjectUsersRequest;
use App\Http\Requests\SyncProjectTeamsRequest;
use App\Models\Project;
use App\Services\ProjectAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Project Assignments API Controller (V1)
 * 
 * Pure API controller for project assignment operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified ProjectAssignmentController for API routes.
 */
class ProjectAssignmentsController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectAssignmentService $assignmentService
    ) {}

    /**
     * Assign users to a project
     * 
     * @param AssignUsersToProjectRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function assignUsers(AssignUsersToProjectRequest $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('assignUsers', $project)) {
                return $this->errorResponse('Unauthorized to assign users to this project', 403, null, 'FORBIDDEN');
            }
            
            $users = $request->input('users', []);
            $sync = $request->input('sync', false);
            
            if ($sync) {
                $results = $this->assignmentService->syncProjectUsers(
                    $project->id,
                    $users,
                    $tenantId
                );
                
                return $this->successResponse($results, 'Project users synced successfully');
            } else {
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
                
                return $this->successResponse($results, $message);
            }
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to assign users: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_ASSIGN_USERS_FAILED'
            );
        }
    }

    /**
     * Remove a user from a project
     * 
     * @param Project $project
     * @param string $user
     * @return JsonResponse
     */
    public function removeUser(Project $project, string $user): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('removeUser', $project)) {
                return $this->errorResponse('Unauthorized to remove users from this project', 403, null, 'FORBIDDEN');
            }
            
            $this->assignmentService->removeUserFromProject(
                $project->id,
                $user,
                $tenantId
            );
            
            return $this->successResponse(null, 'User removed from project successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'user_id' => $user,
            ]);
            
            return $this->errorResponse(
                'Failed to remove user: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_REMOVE_USER_FAILED'
            );
        }
    }

    /**
     * Sync users to a project
     * 
     * @param SyncProjectUsersRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function syncUsers(SyncProjectUsersRequest $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('assignUsers', $project)) {
                return $this->errorResponse('Unauthorized to sync users for this project', 403, null, 'FORBIDDEN');
            }
            
            $users = $request->input('users', []);
            
            $results = $this->assignmentService->syncProjectUsers(
                $project->id,
                $users,
                $tenantId
            );
            
            return $this->successResponse($results, 'Project users synced successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to sync users: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_SYNC_USERS_FAILED'
            );
        }
    }

    /**
     * Get users assigned to a project
     * 
     * @param Project $project
     * @return JsonResponse
     */
    public function getUsers(Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('view', $project)) {
                return $this->errorResponse('Unauthorized to view project assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getAssignmentsForProject(
                $project->id,
                $tenantId
            );
            
            return $this->successResponse($assignments, 'Project users retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve users: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_GET_USERS_FAILED'
            );
        }
    }

    /**
     * Assign teams to a project
     * 
     * @param AssignTeamsToProjectRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function assignTeams(AssignTeamsToProjectRequest $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('assignTeams', $project)) {
                return $this->errorResponse('Unauthorized to assign teams to this project', 403, null, 'FORBIDDEN');
            }
            
            $teams = $request->input('teams', []);
            
            $results = $this->assignmentService->assignTeamsToProject(
                $project->id,
                $teams,
                $tenantId
            );
            
            return $this->successResponse($results, 'Teams assigned successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to assign teams: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_ASSIGN_TEAMS_FAILED'
            );
        }
    }

    /**
     * Remove a team from a project
     * 
     * @param Project $project
     * @param string $team
     * @return JsonResponse
     */
    public function removeTeam(Project $project, string $team): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('removeTeam', $project)) {
                return $this->errorResponse('Unauthorized to remove teams from this project', 403, null, 'FORBIDDEN');
            }
            
            $this->assignmentService->removeTeamFromProject(
                $project->id,
                $team,
                $tenantId
            );
            
            return $this->successResponse(null, 'Team removed from project successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'team_id' => $team,
            ]);
            
            return $this->errorResponse(
                'Failed to remove team: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_REMOVE_TEAM_FAILED'
            );
        }
    }

    /**
     * Sync teams to a project
     * 
     * @param SyncProjectTeamsRequest $request
     * @param Project $project
     * @return JsonResponse
     */
    public function syncTeams(SyncProjectTeamsRequest $request, Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('assignTeams', $project)) {
                return $this->errorResponse('Unauthorized to sync teams for this project', 403, null, 'FORBIDDEN');
            }
            
            $teams = $request->input('teams', []);
            
            $results = $this->assignmentService->syncProjectTeams(
                $project->id,
                $teams,
                $tenantId
            );
            
            return $this->successResponse($results, 'Project teams synced successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
            ]);
            
            return $this->errorResponse(
                'Failed to sync teams: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_SYNC_TEAMS_FAILED'
            );
        }
    }

    /**
     * Get teams assigned to a project
     * 
     * @param Project $project
     * @return JsonResponse
     */
    public function getTeams(Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('view', $project)) {
                return $this->errorResponse('Unauthorized to view project assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getTeamAssignmentsForProject(
                $project->id,
                $tenantId
            );
            
            return $this->successResponse($assignments, 'Project teams retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve teams: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_GET_TEAMS_FAILED'
            );
        }
    }

    /**
     * Get all assignments for a project
     * 
     * @param Project $project
     * @return JsonResponse
     */
    public function index(Project $project): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            if (!Gate::allows('view', $project)) {
                return $this->errorResponse('Unauthorized to view project assignments', 403, null, 'FORBIDDEN');
            }
            
            $assignments = $this->assignmentService->getAssignmentsForProject(
                $project->id,
                $tenantId
            );
            
            return $this->successResponse($assignments, 'Project assignments retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'project_id' => $project->id,
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve assignments: ' . $e->getMessage(),
                500,
                null,
                'PROJECT_GET_ASSIGNMENTS_FAILED'
            );
        }
    }
}

