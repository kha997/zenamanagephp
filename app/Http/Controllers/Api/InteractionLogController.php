<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreInteractionLogRequest;
use App\Http\Requests\UpdateInteractionLogRequest;
use App\Http\Resources\InteractionLogResource;
use App\Http\Resources\InteractionLogCollection;
use App\Models\InteractionLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * API Controller for managing Interaction Logs
 * 
 * Handles CRUD operations for interaction logs with tenant isolation,
 * pagination, filtering, sorting, and client approval workflow.
 */
class InteractionLogController extends BaseApiController
{
    /**
     * Display a paginated listing of interaction logs
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate tenant access
            $this->validateTenantAccess($request);
            
            // Get pagination parameters
            $paginationParams = $this->getPaginationParams($request);
            
            // Get sorting parameters
            $sortingParams = $this->getSortingParams($request, [
                'created_at', 'type', 'visibility', 'client_approved'
            ]);
            
            // Get filtering parameters
            $filterParams = $this->getFilterParams($request, [
                'project_id', 'type', 'visibility', 'client_approved', 'tag_path'
            ]);
            
            // Build query with tenant isolation
            $query = InteractionLog::query()
                ->whereHas('project', function (Builder $query) use ($request) {
                    $query->where('tenant_id', $request->user()->tenant_id);
                })
                ->with(['project:id,name', 'linkedTask:id,name,status', 'createdByUser:id,name']);
            
            // Apply filters
            if (isset($filterParams['project_id'])) {
                $query->where('project_id', $filterParams['project_id']);
            }
            
            if (isset($filterParams['type'])) {
                $query->where('type', $filterParams['type']);
            }
            
            if (isset($filterParams['visibility'])) {
                $query->where('visibility', $filterParams['visibility']);
            }
            
            if (isset($filterParams['client_approved'])) {
                $query->where('client_approved', (bool) $filterParams['client_approved']);
            }
            
            if (isset($filterParams['tag_path'])) {
                $query->where('tag_path', 'LIKE', '%' . $filterParams['tag_path'] . '%');
            }
            
            // Apply date range filter if provided
            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }
            
            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }
            
            // Apply search in description
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where('description', 'LIKE', '%' . $searchTerm . '%');
            }
            
            // Apply sorting
            $query->orderBy($sortingParams['sort_by'], $sortingParams['sort_direction']);
            
            // Execute paginated query
            $interactionLogs = $query->paginate(
                $paginationParams['per_page'],
                ['*'],
                'page',
                $paginationParams['page']
            );
            
            return $this->paginatedResponse(
                new InteractionLogCollection($interactionLogs),
                'Interaction logs retrieved successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve interaction logs: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Store a newly created interaction log
     * 
     * @param StoreInteractionLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreInteractionLogRequest $request): JsonResponse
    {
        try {
            // Validate tenant access for the project
            $project = Project::where('id', $request->validated('project_id'))
                ->where('tenant_id', $request->user()->tenant_id)
                ->firstOrFail();
            
            // Check permissions
            Gate::authorize('create-interaction-log', $project);
            
            DB::beginTransaction();
            
            // Create interaction log
            $interactionLog = InteractionLog::create([
                'project_id' => $request->validated('project_id'),
                'linked_task_id' => $request->validated('linked_task_id'),
                'type' => $request->validated('type'),
                'description' => $request->validated('description'),
                'tag_path' => $request->validated('tag_path'),
                'visibility' => $request->validated('visibility'),
                'client_approved' => false, // Always start as not approved
                'created_by' => $request->user()->id,
            ]);
            
            // Handle attachments if provided
            if ($request->has('attachments')) {
                $this->handleAttachments($interactionLog, $request->validated('attachments'));
            }
            
            // Load relationships for response
            $interactionLog->load(['project:id,name', 'linkedTask:id,name,status', 'createdByUser:id,name']);
            
            DB::commit();
            
            return $this->successResponse(
                new InteractionLogResource($interactionLog),
                'Interaction log created successfully',
                201
            );
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to create interaction log: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Display the specified interaction log
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            // Find interaction log with tenant isolation
            $interactionLog = InteractionLog::whereHas('project', function (Builder $query) use ($request) {
                $query->where('tenant_id', $request->user()->tenant_id);
            })
            ->with([
                'project:id,name,tenant_id',
                'linkedTask:id,name,status',
                'createdByUser:id,name',
                'attachments'
            ])
            ->findOrFail($id);
            
            // Check permissions
            Gate::authorize('view-interaction-log', $interactionLog);
            
            return $this->successResponse(
                new InteractionLogResource($interactionLog),
                'Interaction log retrieved successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve interaction log: ' . $e->getMessage(),
                404
            );
        }
    }
    
    /**
     * Update the specified interaction log
     * 
     * @param UpdateInteractionLogRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateInteractionLogRequest $request, int $id): JsonResponse
    {
        try {
            // Find interaction log with tenant isolation
            $interactionLog = InteractionLog::whereHas('project', function (Builder $query) use ($request) {
                $query->where('tenant_id', $request->user()->tenant_id);
            })->findOrFail($id);
            
            // Check permissions
            Gate::authorize('update-interaction-log', $interactionLog);
            
            DB::beginTransaction();
            
            // Update only provided fields (partial update)
            $updateData = array_filter($request->validated(), function ($value) {
                return $value !== null;
            });
            
            // Remove created_by from update data (should not be changed)
            unset($updateData['created_by']);
            
            $interactionLog->update($updateData);
            
            // Handle attachments if provided
            if ($request->has('attachments')) {
                $this->handleAttachments($interactionLog, $request->validated('attachments'));
            }
            
            // Load relationships for response
            $interactionLog->load(['project:id,name', 'linkedTask:id,name,status', 'createdByUser:id,name']);
            
            DB::commit();
            
            return $this->successResponse(
                new InteractionLogResource($interactionLog),
                'Interaction log updated successfully'
            );
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to update interaction log: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Remove the specified interaction log
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        try {
            // Find interaction log with tenant isolation
            $interactionLog = InteractionLog::whereHas('project', function (Builder $query) use ($request) {
                $query->where('tenant_id', $request->user()->tenant_id);
            })->findOrFail($id);
            
            // Check permissions
            Gate::authorize('delete-interaction-log', $interactionLog);
            
            DB::beginTransaction();
            
            // Delete associated attachments first
            if ($interactionLog->attachments) {
                foreach ($interactionLog->attachments as $attachment) {
                    // Delete physical file
                    if (\Storage::exists($attachment->file_path)) {
                        \Storage::delete($attachment->file_path);
                    }
                    $attachment->delete();
                }
            }
            
            $interactionLog->delete();
            
            DB::commit();
            
            return $this->successResponse(
                null,
                'Interaction log deleted successfully'
            );
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to delete interaction log: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Approve interaction log for client visibility
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        try {
            // Find interaction log with tenant isolation
            $interactionLog = InteractionLog::whereHas('project', function (Builder $query) use ($request) {
                $query->where('tenant_id', $request->user()->tenant_id);
            })->findOrFail($id);
            
            // Check permissions
            Gate::authorize('approve-interaction-log', $interactionLog);
            
            // Validate that log is client-visible
            if ($interactionLog->visibility !== 'client') {
                return $this->failResponse(
                    'Only client-visible logs can be approved',
                    400
                );
            }
            
            // Update approval status
            $interactionLog->update([
                'client_approved' => true,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            
            // Load relationships for response
            $interactionLog->load(['project:id,name', 'linkedTask:id,name,status', 'createdByUser:id,name']);
            
            return $this->successResponse(
                new InteractionLogResource($interactionLog),
                'Interaction log approved successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to approve interaction log: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Get interaction logs for a specific project
     * 
     * @param int $projectId
     * @param Request $request
     * @return JsonResponse
     */
    public function byProject(int $projectId, Request $request): JsonResponse
    {
        try {
            // Validate project exists and belongs to tenant
            $project = Project::where('id', $projectId)
                ->where('tenant_id', $request->user()->tenant_id)
                ->firstOrFail();
            
            // Check permissions
            Gate::authorize('view-project-interaction-logs', $project);
            
            // Get pagination parameters
            $paginationParams = $this->getPaginationParams($request);
            
            // Build query
            $query = InteractionLog::where('project_id', $projectId)
                ->with(['linkedTask:id,name,status', 'createdByUser:id,name']);
            
            // Apply visibility filter for client users
            if (!$request->user()->can('view-internal-logs')) {
                $query->where('visibility', 'client')
                      ->where('client_approved', true);
            }
            
            // Apply sorting
            $query->orderBy('created_at', 'desc');
            
            // Execute paginated query
            $interactionLogs = $query->paginate(
                $paginationParams['per_page'],
                ['*'],
                'page',
                $paginationParams['page']
            );
            
            return $this->paginatedResponse(
                new InteractionLogCollection($interactionLogs),
                'Project interaction logs retrieved successfully'
            );
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve project interaction logs: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Handle file attachments for interaction log
     * 
     * @param InteractionLog $interactionLog
     * @param array $attachments
     * @return void
     */
    private function handleAttachments(InteractionLog $interactionLog, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            // Store file
            $filePath = $attachment->store(
                'interaction-logs/' . $interactionLog->id,
                'public'
            );
            
            // Create attachment record
            $interactionLog->attachments()->create([
                'filename' => $attachment->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $attachment->getSize(),
                'mime_type' => $attachment->getMimeType(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }
}