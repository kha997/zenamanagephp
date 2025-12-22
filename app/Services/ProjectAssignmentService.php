<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProjectAssignmentService
 * 
 * Service layer for managing project assignments (users and teams)
 * Handles bulk operations, sync operations, and tenant isolation
 */
class ProjectAssignmentService
{
    use ServiceBaseTrait;

    /**
     * Assign a single user to a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $userId User ID (ULID)
     * @param string|null $roleId Optional role ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
     */
    public function assignUserToProject(
        string $projectId,
        string $userId,
        ?string $roleId = null,
        string $tenantId
    ): void {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        // Verify user exists and belongs to same tenant
        $user = User::where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$user) {
            $this->logError('User not found for assignment', null, [
                'user_id' => $userId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(404, 'User not found');
        }
        
        // Check if user is already assigned
        if ($project->users()->where('users.id', $userId)->exists()) {
            $this->logError('User already assigned to project', null, [
                'user_id' => $userId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(409, 'User is already assigned to this project');
        }
        
        // Attach user to project with role_id in pivot
        $pivotData = [];
        if ($roleId) {
            // Validate role exists and belongs to tenant
            $role = \Src\RBAC\Models\Role::where('id', $roleId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$role) {
                $this->logError('Role not found for assignment', null, [
                    'role_id' => $roleId,
                    'project_id' => $projectId,
                    'tenant_id' => $tenantId
                ]);
                abort(404, 'Role not found');
            }
            
            $pivotData['role_id'] = $roleId;
        }
        
        $project->users()->attach($userId, $pivotData);
        
        $this->logCrudOperation('user_assigned_to_project', $project, [
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Assign multiple users to a project (bulk operation)
     * 
     * @param string $projectId Project ID (ULID)
     * @param array $assignments Array of ['user_id' => string, 'role_id' => string|null]
     * @param string $tenantId Tenant ID (ULID)
     * @return array Results with success/failure for each assignment
     */
    public function assignUsersToProject(
        string $projectId,
        array $assignments,
        string $tenantId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];
        
        return $this->executeTransaction(function () use ($project, $assignments, $tenantId, &$results) {
            foreach ($assignments as $assignment) {
                $userId = $assignment['user_id'] ?? null;
                $roleId = $assignment['role_id'] ?? null;
                
                if (!$userId) {
                    $results['failed'][] = [
                        'user_id' => null,
                        'error' => 'user_id is required'
                    ];
                    continue;
                }
                
                try {
                    // Check if already assigned
                    if ($project->users()->where('users.id', $userId)->exists()) {
                        $results['skipped'][] = [
                            'user_id' => $userId,
                            'reason' => 'User already assigned'
                        ];
                        continue;
                    }
                    
                    // Verify user exists and belongs to same tenant
                    $user = User::where('id', $userId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    
                    if (!$user) {
                        $results['failed'][] = [
                            'user_id' => $userId,
                            'error' => 'User not found or tenant mismatch'
                        ];
                        continue;
                    }
                    
                    // Validate role if provided
                    $pivotData = [];
                    if ($roleId) {
                        $role = \Src\RBAC\Models\Role::where('id', $roleId)
                            ->where('tenant_id', $tenantId)
                            ->first();
                        
                        if (!$role) {
                            $results['failed'][] = [
                                'user_id' => $userId,
                                'error' => 'Role not found or tenant mismatch'
                            ];
                            continue;
                        }
                        
                        $pivotData['role_id'] = $roleId;
                    }
                    
                    $project->users()->attach($userId, $pivotData);
                    
                    $results['success'][] = [
                        'user_id' => $userId,
                        'role_id' => $roleId
                    ];
                } catch (\Exception $e) {
                    $this->logError('Failed to assign user in bulk operation', $e, [
                        'user_id' => $userId,
                        'project_id' => $project->id,
                        'tenant_id' => $tenantId
                    ]);
                    
                    $results['failed'][] = [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            if (!empty($results['success'])) {
                $this->logCrudOperation('users_bulk_assigned_to_project', $project, [
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed']),
                    'skipped_count' => count($results['skipped']),
                    'tenant_id' => $tenantId
                ]);
            }
            
            return $results;
        });
    }

    /**
     * Remove a user from a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $userId User ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function removeUserFromProject(
        string $projectId,
        string $userId,
        string $tenantId
    ): void {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        // Check if user is assigned
        if (!$project->users()->where('users.id', $userId)->exists()) {
            $this->logError('User not assigned to project', null, [
                'user_id' => $userId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(404, 'User is not assigned to this project');
        }
        
        // Detach user from project
        $project->users()->detach($userId);
        
        $this->logCrudOperation('user_removed_from_project', $project, [
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Sync project users (replace all with new list)
     * 
     * @param string $projectId Project ID (ULID)
     * @param array $assignments Array of ['user_id' => string, 'role_id' => string|null]
     * @param string $tenantId Tenant ID (ULID)
     * @return array Results with added/removed/updated counts
     */
    public function syncProjectUsers(
        string $projectId,
        array $assignments,
        string $tenantId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        return $this->executeTransaction(function () use ($project, $assignments, $tenantId) {
            // Get current assignments
            $currentUserIds = $project->users()->pluck('users.id')->toArray();
            
            // Prepare sync data
            $syncData = [];
            foreach ($assignments as $assignment) {
                $userId = $assignment['user_id'] ?? null;
                $roleId = $assignment['role_id'] ?? null;
                
                if (!$userId) {
                    continue;
                }
                
                // Verify user exists and belongs to same tenant
                $user = User::where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                
                if (!$user) {
                    continue;
                }
                
                $pivotData = [];
                if ($roleId) {
                    $role = \Src\RBAC\Models\Role::where('id', $roleId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    
                    if ($role) {
                        $pivotData['role_id'] = $roleId;
                    }
                }
                
                $syncData[$userId] = $pivotData;
            }
            
            // Perform sync
            $project->users()->sync($syncData);
            
            $newUserIds = array_keys($syncData);
            $added = array_diff($newUserIds, $currentUserIds);
            $removed = array_diff($currentUserIds, $newUserIds);
            $kept = array_intersect($currentUserIds, $newUserIds);
            
            $this->logCrudOperation('project_users_synced', $project, [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'kept_count' => count($kept),
                'tenant_id' => $tenantId
            ]);
            
            return [
                'added' => array_values($added),
                'removed' => array_values($removed),
                'kept' => array_values($kept),
                'total' => count($syncData)
            ];
        });
    }

    /**
     * Assign a single team to a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $teamId Team ID (ULID)
     * @param string $role Team role (contributor, reviewer, stakeholder)
     * @param string $tenantId Tenant ID (ULID)
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
     */
    public function assignTeamToProject(
        string $projectId,
        string $teamId,
        string $role = 'contributor',
        string $tenantId
    ): void {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        // Validate role
        $validRoles = ['contributor', 'reviewer', 'stakeholder'];
        if (!in_array($role, $validRoles)) {
            $this->logError('Invalid team role', null, [
                'role' => $role,
                'project_id' => $projectId,
                'team_id' => $teamId,
                'tenant_id' => $tenantId
            ]);
            abort(422, 'Invalid role. Must be one of: ' . implode(', ', $validRoles));
        }
        
        // Verify team exists and belongs to same tenant
        $team = Team::where('id', $teamId)
            ->where('tenant_id', $tenantId)
            ->first();
        
        if (!$team) {
            $this->logError('Team not found for assignment', null, [
                'team_id' => $teamId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(404, 'Team not found');
        }
        
        // Check if team is already assigned
        if ($project->teams()->where('teams.id', $teamId)->exists()) {
            $this->logError('Team already assigned to project', null, [
                'team_id' => $teamId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(409, 'Team is already assigned to this project');
        }
        
        // Attach team to project with role and joined_at
        $project->teams()->attach($teamId, [
            'role' => $role,
            'joined_at' => now()
        ]);
        
        $this->logCrudOperation('team_assigned_to_project', $project, [
            'team_id' => $teamId,
            'role' => $role,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Assign multiple teams to a project (bulk operation)
     * 
     * @param string $projectId Project ID (ULID)
     * @param array $assignments Array of ['team_id' => string, 'role' => string]
     * @param string $tenantId Tenant ID (ULID)
     * @return array Results with success/failure for each assignment
     */
    public function assignTeamsToProject(
        string $projectId,
        array $assignments,
        string $tenantId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        $validRoles = ['contributor', 'reviewer', 'stakeholder'];
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];
        
        return $this->executeTransaction(function () use ($project, $assignments, $tenantId, $validRoles, &$results) {
            foreach ($assignments as $assignment) {
                $teamId = $assignment['team_id'] ?? null;
                $role = $assignment['role'] ?? 'contributor';
                
                if (!$teamId) {
                    $results['failed'][] = [
                        'team_id' => null,
                        'error' => 'team_id is required'
                    ];
                    continue;
                }
                
                // Validate role
                if (!in_array($role, $validRoles)) {
                    $results['failed'][] = [
                        'team_id' => $teamId,
                        'error' => 'Invalid role. Must be one of: ' . implode(', ', $validRoles)
                    ];
                    continue;
                }
                
                try {
                    // Check if already assigned
                    if ($project->teams()->where('teams.id', $teamId)->exists()) {
                        $results['skipped'][] = [
                            'team_id' => $teamId,
                            'reason' => 'Team already assigned'
                        ];
                        continue;
                    }
                    
                    // Verify team exists and belongs to same tenant
                    $team = Team::where('id', $teamId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    
                    if (!$team) {
                        $results['failed'][] = [
                            'team_id' => $teamId,
                            'error' => 'Team not found or tenant mismatch'
                        ];
                        continue;
                    }
                    
                    $project->teams()->attach($teamId, [
                        'role' => $role,
                        'joined_at' => now()
                    ]);
                    
                    $results['success'][] = [
                        'team_id' => $teamId,
                        'role' => $role
                    ];
                } catch (\Exception $e) {
                    $this->logError('Failed to assign team in bulk operation', $e, [
                        'team_id' => $teamId,
                        'project_id' => $project->id,
                        'tenant_id' => $tenantId
                    ]);
                    
                    $results['failed'][] = [
                        'team_id' => $teamId,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            if (!empty($results['success'])) {
                $this->logCrudOperation('teams_bulk_assigned_to_project', $project, [
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed']),
                    'skipped_count' => count($results['skipped']),
                    'tenant_id' => $tenantId
                ]);
            }
            
            return $results;
        });
    }

    /**
     * Remove a team from a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $teamId Team ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function removeTeamFromProject(
        string $projectId,
        string $teamId,
        string $tenantId
    ): void {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        // Check if team is assigned
        if (!$project->teams()->where('teams.id', $teamId)->exists()) {
            $this->logError('Team not assigned to project', null, [
                'team_id' => $teamId,
                'project_id' => $projectId,
                'tenant_id' => $tenantId
            ]);
            abort(404, 'Team is not assigned to this project');
        }
        
        // Update left_at timestamp instead of detaching (soft remove)
        $project->teams()->updateExistingPivot($teamId, [
            'left_at' => now()
        ]);
        
        $this->logCrudOperation('team_removed_from_project', $project, [
            'team_id' => $teamId,
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Sync project teams (replace all with new list)
     * 
     * @param string $projectId Project ID (ULID)
     * @param array $assignments Array of ['team_id' => string, 'role' => string]
     * @param string $tenantId Tenant ID (ULID)
     * @return array Results with added/removed/updated counts
     */
    public function syncProjectTeams(
        string $projectId,
        array $assignments,
        string $tenantId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        $validRoles = ['contributor', 'reviewer', 'stakeholder'];
        
        return $this->executeTransaction(function () use ($project, $assignments, $tenantId, $validRoles) {
            // Get current assignments
            $currentTeamIds = $project->teams()->pluck('teams.id')->toArray();
            
            // Prepare sync data
            $syncData = [];
            foreach ($assignments as $assignment) {
                $teamId = $assignment['team_id'] ?? null;
                $role = $assignment['role'] ?? 'contributor';
                
                if (!$teamId) {
                    continue;
                }
                
                // Validate role
                if (!in_array($role, $validRoles)) {
                    continue;
                }
                
                // Verify team exists and belongs to same tenant
                $team = Team::where('id', $teamId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                
                if (!$team) {
                    continue;
                }
                
                $syncData[$teamId] = [
                    'role' => $role,
                    'joined_at' => now(),
                    'left_at' => null
                ];
            }
            
            // Perform sync
            $project->teams()->sync($syncData);
            
            $newTeamIds = array_keys($syncData);
            $added = array_diff($newTeamIds, $currentTeamIds);
            $removed = array_diff($currentTeamIds, $newTeamIds);
            $kept = array_intersect($currentTeamIds, $newTeamIds);
            
            $this->logCrudOperation('project_teams_synced', $project, [
                'added_count' => count($added),
                'removed_count' => count($removed),
                'kept_count' => count($kept),
                'tenant_id' => $tenantId
            ]);
            
            return [
                'added' => array_values($added),
                'removed' => array_values($removed),
                'kept' => array_values($kept),
                'total' => count($syncData)
            ];
        });
    }

    /**
     * Get all assignments for a project (users + teams)
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return array Combined assignments data
     */
    public function getProjectAssignments(
        string $projectId,
        string $tenantId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        $users = $this->getProjectUsers($projectId, $tenantId);
        $teams = $this->getProjectTeams($projectId, $tenantId);
        
        return [
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->pivot->role_id ?? null,
                    'assigned_at' => $user->pivot->created_at?->toISOString()
                ];
            })->values(),
            'teams' => $teams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'role' => $team->pivot->role ?? null,
                    'joined_at' => $team->pivot->joined_at?->toISOString(),
                    'left_at' => $team->pivot->left_at?->toISOString()
                ];
            })->values()
        ];
    }

    /**
     * Get assigned users for a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return Collection
     */
    public function getProjectUsers(
        string $projectId,
        string $tenantId
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        return $project->users()->get();
    }

    /**
     * Get assigned teams for a project
     * 
     * @param string $projectId Project ID (ULID)
     * @param string $tenantId Tenant ID (ULID)
     * @return Collection
     */
    public function getProjectTeams(
        string $projectId,
        string $tenantId
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        $project = Project::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        
        $this->validateModelOwnership($project, $tenantId);
        
        // Only get active teams (left_at is null)
        return $project->teams()
            ->wherePivotNull('left_at')
            ->get();
    }
}

