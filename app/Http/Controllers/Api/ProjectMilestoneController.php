<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProjectMilestoneController - API Controller cho Project Milestone management
 */
class ProjectMilestoneController extends BaseApiController
{
    /**
     * Get milestones for a project
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            $milestones = ProjectMilestone::byProject($projectId)
                ->ordered()
                ->get();

            return $this->successResponse([
                'project_id' => $projectId,
                'milestones' => $milestones,
                'statistics' => ProjectMilestone::getProjectStatistics($projectId)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get project milestones', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'user_id' => Auth::id()
            ]);
            
            return $this->errorResponse('Failed to retrieve milestones', 500);
        }
    }

    /**
     * Create a new milestone
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to create milestones', 403);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'target_date' => 'nullable|date|after_or_equal:today',
                'order' => 'nullable|integer|min:0'
            ]);

            DB::beginTransaction();

            $milestone = ProjectMilestone::create([
                'project_id' => $projectId,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'target_date' => $request->input('target_date'),
                'order' => $request->input('order', 0),
                'created_by' => $user->id
            ]);

            DB::commit();

            return $this->successResponse([
                'milestone' => $milestone->load('creator')
            ], 'Milestone created successfully', 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to create milestone', 500);
        }
    }

    /**
     * Get a specific milestone
     */
    public function show(string $projectId, string $milestoneId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            $milestone = ProjectMilestone::byProject($projectId)
                ->find($milestoneId);

            if (!$milestone) {
                return $this->errorResponse('Milestone not found', 404);
            }

            return $this->successResponse([
                'milestone' => $milestone->load('creator')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'user_id' => Auth::id()
            ]);
            
            return $this->errorResponse('Failed to retrieve milestone', 500);
        }
    }

    /**
     * Update a milestone
     */
    public function update(Request $request, string $projectId, string $milestoneId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to update milestones', 403);
            }

            $milestone = ProjectMilestone::byProject($projectId)
                ->find($milestoneId);

            if (!$milestone) {
                return $this->errorResponse('Milestone not found', 404);
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:1000',
                'target_date' => 'nullable|date',
                'order' => 'sometimes|integer|min:0'
            ]);

            DB::beginTransaction();

            $milestone->update($request->only([
                'name', 'description', 'target_date', 'order'
            ]));

            DB::commit();

            return $this->successResponse([
                'milestone' => $milestone->fresh()->load('creator')
            ], 'Milestone updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to update milestone', 500);
        }
    }

    /**
     * Delete a milestone
     */
    public function destroy(string $projectId, string $milestoneId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to delete milestones', 403);
            }

            $milestone = ProjectMilestone::byProject($projectId)
                ->find($milestoneId);

            if (!$milestone) {
                return $this->errorResponse('Milestone not found', 404);
            }

            DB::beginTransaction();

            $milestone->delete();

            DB::commit();

            return $this->successResponse(null, 'Milestone deleted successfully');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'user_id' => Auth::id()
            ]);
            
            return $this->errorResponse('Failed to delete milestone', 500);
        }
    }

    /**
     * Mark milestone as completed
     */
    public function complete(Request $request, string $projectId, string $milestoneId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to update milestones', 403);
            }

            $milestone = ProjectMilestone::byProject($projectId)
                ->find($milestoneId);

            if (!$milestone) {
                return $this->errorResponse('Milestone not found', 404);
            }

            DB::beginTransaction();

            $success = $milestone->markCompleted($user->id);

            DB::commit();

            if (!$success) {
                return $this->errorResponse('Milestone is already completed', 400);
            }

            return $this->successResponse([
                'milestone' => $milestone->fresh()->load('creator')
            ], 'Milestone marked as completed');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to complete milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'user_id' => Auth::id()
            ]);
            
            return $this->errorResponse('Failed to complete milestone', 500);
        }
    }

    /**
     * Cancel a milestone
     */
    public function cancel(Request $request, string $projectId, string $milestoneId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to update milestones', 403);
            }

            $milestone = ProjectMilestone::byProject($projectId)
                ->find($milestoneId);

            if (!$milestone) {
                return $this->errorResponse('Milestone not found', 404);
            }

            $request->validate([
                'reason' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            $success = $milestone->markCancelled($request->input('reason'));

            DB::commit();

            if (!$success) {
                return $this->errorResponse('Milestone is already cancelled', 400);
            }

            return $this->successResponse([
                'milestone' => $milestone->fresh()->load('creator')
            ], 'Milestone cancelled');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to cancel milestone', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'milestone_id' => $milestoneId,
                'user_id' => Auth::id()
            ]);
            
            return $this->errorResponse('Failed to cancel milestone', 500);
        }
    }

    /**
     * Reorder milestones
     */
    public function reorder(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify user has access to project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }

            // Check permission
            if (!$user->hasPermission('project.write')) {
                return $this->errorResponse('Insufficient permissions to reorder milestones', 403);
            }

            $request->validate([
                'milestone_ids' => 'required|array',
                'milestone_ids.*' => 'string|exists:project_milestones,id'
            ]);

            DB::beginTransaction();

            ProjectMilestone::reorderMilestones($projectId, $request->input('milestone_ids'));

            DB::commit();

            return $this->successResponse(null, 'Milestones reordered successfully');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to reorder milestones', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return $this->errorResponse('Failed to reorder milestones', 500);
        }
    }
}