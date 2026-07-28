<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\RfiEscalationConflictException;
use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\RfiEscalationService;
use App\Services\RfiLifecycleService;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RfiController extends ApiBaseController
{
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private RfiEscalationService $escalationService,
        private RfiLifecycleService $lifecycleService,
    ) {
    }

    /**
     * Display a listing of RFIs.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $query = $this->rfiQuery();

            // Filter by project if specified
            if ($request->has('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            // Filter by status if specified
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by priority if specified
            if ($request->has('priority')) {
                $query->where('priority', $request->input('priority'));
            }

            // Filter by assigned user if specified
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->input('assigned_to'));
            }

            // Search functionality
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('rfi_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $rfis = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->listSuccessResponse($rfis, 'RFIs retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve RFIs: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created RFI.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
                'title' => 'required|string|max:255',
                'subject' => 'nullable|string|max:255',
                'question' => 'nullable|string',
                'description' => 'required|string',
                'priority' => 'required|in:low,medium,high,urgent',
                'due_date' => 'nullable|date|after:today',
                'assigned_to' => 'nullable|exists:users,id',
                'location' => 'nullable|string|max:255',
                'drawing_reference' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $project = $this->projectForTenant($request->input('project_id'));

            if (!$project) {
                return $this->notFound('Project not found');
            }

            $rfi = Rfi::create([
                'tenant_id' => $this->tenantId(),
                'project_id' => $project->id,
                'subject' => $request->input('subject', $request->input('title')),
                'question' => $request->input('question', $request->input('description')),
                'asked_by' => $request->input('asked_by', $user->id),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'priority' => $request->input('priority'),
                'due_date' => $request->input('due_date'),
                'assigned_to' => $request->input('assigned_to'),
                'location' => $request->input('location'),
                'drawing_reference' => $request->input('drawing_reference'),
                'status' => 'open',
                'created_by' => $user->id,
                'rfi_number' => $this->generateRfiNumber($request->input('project_id')),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.create',
                'rfi',
                (string) $rfi->id,
                201,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI created successfully', 201);
        } catch (\Exception $e) {
            return $this->serverError('Failed to create RFI: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified RFI.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id, [
                'project:id,name',
                'createdBy:id,name',
                'assignedTo:id,name',
            ]);

            return $this->successResponse($rfi, 'RFI retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve RFI: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified RFI.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'subject' => 'sometimes|string|max:255',
                'question' => 'sometimes|string',
                'description' => 'sometimes|string',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'assigned_to' => 'nullable|exists:users,id',
                'location' => 'nullable|string|max:255',
                'drawing_reference' => 'nullable|string|max:255',
                'status' => 'sometimes|in:open,answered,closed',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $rfi->update($request->only([
                'title', 'subject', 'question', 'description', 'priority', 'due_date', 
                'assigned_to', 'location', 'drawing_reference', 'status'
            ]));

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.update',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update RFI: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified RFI.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            $projectId = $rfi->project_id;
            $rfi->delete();

            $this->auditLogger->log(
                $request,
                'zena.rfi.delete',
                'rfi',
                (string) $rfi->id,
                200,
                $projectId,
                $this->tenantId()
            );

            return $this->successResponse(null, 'RFI deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete RFI: ' . $e->getMessage());
        }
    }

    /**
     * Assign RFI to a user.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            $validator = Validator::make($request->all(), [
                'assigned_to' => 'required|exists:users,id',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $rfi->update([
                'assigned_to' => $request->input('assigned_to'),
                'status' => 'in_progress',
                'assigned_at' => now(),
                'assignment_notes' => $request->input('notes'),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.assign',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI assigned successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to assign RFI: ' . $e->getMessage());
        }
    }

    /**
     * Respond to RFI.
     */
    public function respond(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            $validator = Validator::make($request->all(), [
                'response' => 'required|string',
                'status' => 'required|in:answered,closed',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $rfi->update([
                'response' => $request->input('response'),
                'status' => $request->input('status'),
                'responded_by' => $user->id,
                'responded_at' => now(),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.respond',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI response submitted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to respond to RFI: ' . $e->getMessage());
        }
    }

    /**
     * Close RFI.
     */
    public function close(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if ($rfi->status !== 'answered') {
                return $this->errorResponse('RFI must be answered before it can be closed', 400);
            }

            $rfi->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.close',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI closed successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to close RFI: ' . $e->getMessage());
        }
    }

    /**
     * Escalate RFI.
     */
    public function escalate(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if ($this->lifecycleService->isTerminal($rfi)) {
                return $this->errorResponse('Cannot escalate a closed or cancelled RFI', 422);
            }

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot escalate RFIs', 403);
            }

            if (!$this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id)) {
                return $this->errorResponse('Only the project manager or an admin can escalate this RFI', 403);
            }

            $validator = Validator::make($request->all(), [
                'escalation_reason' => 'required|string',
                'escalated_to' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $target = User::where('id', $request->input('escalated_to'))
                ->where('tenant_id', $this->tenantId())
                ->first();

            if (!$target) {
                return $this->errorResponse('Escalation target must belong to the same tenant', 422);
            }

            if (!$this->userIsActive($target)) {
                return $this->errorResponse('Escalation target must be an active user', 422);
            }

            if (!UserRoleProject::where('project_id', $rfi->project_id)->where('user_id', $target->id)->exists()
                && !$this->userHasAdminRole($target)) {
                return $this->errorResponse("Escalation target must be a member of this RFI's project", 422);
            }

            try {
                $this->escalationService->escalate($rfi, $target->id, $user->id, $request->input('escalation_reason'));
            } catch (RfiEscalationConflictException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            }

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log($request, 'zena.rfi.escalate', 'rfi', (string) $rfi->id, 200, $rfi->project_id, $this->tenantId());

            return $this->successResponse($rfi, 'RFI escalated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to escalate RFI: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the RFI's active escalation.
     */
    public function resolveEscalation(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot resolve escalations', 403);
            }

            // Deliberately no blanket "no active escalation" pre-check here: the
            // service distinguishes "never escalated" (404) from "already
            // resolved" (409) by inspecting escalation history, and a pre-check
            // on current_escalation_id alone would shadow that distinction on a
            // duplicate resolve request (current_escalation_id is cleared by the
            // first resolve, so a naive guard would always answer 404).
            $isTarget = $rfi->escalated_to === $user->id;
            $isPmOrAdmin = $this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id);

            if (!$isTarget && !$isPmOrAdmin) {
                return $this->errorResponse('Only the escalation target, project manager, or an admin can resolve this escalation', 403);
            }

            $validator = Validator::make($request->all(), ['resolution' => 'required|string']);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            try {
                $this->escalationService->resolveEscalation($rfi, $user->id, $request->input('resolution'));
            } catch (RfiEscalationConflictException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            } catch (\App\Exceptions\RfiEscalationNotFoundException $e) {
                return $this->errorResponse($e->getMessage(), 404);
            }

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log($request, 'zena.rfi.resolve_escalation', 'rfi', (string) $rfi->id, 200, $rfi->project_id, $this->tenantId());

            return $this->successResponse($rfi, 'Escalation resolved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to resolve escalation: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique RFI number.
     */
    private function generateRfiNumber(string $projectId): string
    {
        return DB::transaction(function () use ($projectId): string {
            $project = Project::find($projectId);
            $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';

            $lastRfi = Rfi::where('project_id', $projectId)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()
                ->first();

            $sequence = $lastRfi ? (int) substr($lastRfi->rfi_number, -4) + 1 : 1;

            return $projectCode . '-RFI-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    private function tenantId(): string
    {
        $tenantId = request()->attributes->get('tenant_id');

        if (!$tenantId && app()->bound('current_tenant_id')) {
            $tenantId = app('current_tenant_id');
        }

        if (!$tenantId) {
            throw new \RuntimeException('Tenant context missing');
        }

        return (string) $tenantId;
    }

    private function rfiQuery(array $relations = null): Builder
    {
        $relations ??= [
            'project:id,name',
            'createdBy:id,name',
            'assignedTo:id,name',
        ];

        return Rfi::with($relations)->where('tenant_id', $this->tenantId());
    }

    private function rfiForTenant(string $id, array $relations = null): Rfi
    {
        return $this->rfiQuery($relations)->where('id', $id)->firstOrFail();
    }

    private function projectForTenant(string $projectId): ?Project
    {
        return Project::where('id', $projectId)
            ->where('tenant_id', $this->tenantId())
            ->first();
    }

    private function userIsActive(User $user): bool
    {
        return (bool) $user->is_active;
    }

    private function userHasAdminRole(User $user): bool
    {
        return $user->roles()->whereIn('name', ['System Admin', 'Admin', 'super_admin', 'system_admin'])->exists();
    }

    private function actorIsProjectManagerOrAdminForProject(User $user, string $projectId): bool
    {
        if ($this->userHasAdminRole($user)) {
            return true;
        }

        return UserRoleProject::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->whereHas('role', fn ($q) => $q->where('name', 'project_manager'))
            ->exists();
    }
}
