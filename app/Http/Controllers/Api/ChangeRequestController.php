<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\AuditLog;
use App\Models\Baseline;
use App\Models\BaselineHistory;
use App\Models\ChangeRequest;
use App\Models\Component;
use App\Models\CrLink;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Services\ErrorEnvelopeService;
use App\Services\ZenaAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChangeRequestController extends BaseApiController
{
    use ZenaContractResponseTrait;

    private const TIMELINE_ACTION_STATUS_MAP = [
        'zena.change_request.submit' => ChangeRequest::STATUS_SUBMITTED,
        'zena.change_request.approve' => ChangeRequest::STATUS_APPROVED,
        'zena.change_request.reject' => ChangeRequest::STATUS_REJECTED,
        'zena.change_request.apply' => ChangeRequest::STATUS_IMPLEMENTED,
    ];

    private const MUTABLE_LINK_TYPES = [
        CrLink::LINKED_TYPE_TASK,
        CrLink::LINKED_TYPE_COMPONENT,
    ];

    public function __construct(private ZenaAuditLogger $auditLogger)
    {
    }

    private function createWorkflowNotification(
        ChangeRequest $changeRequest,
        string $recipientUserId,
        string $type,
        string $title,
        ?string $body,
        string $eventKey
    ): void {
        Notification::create([
            'tenant_id' => (string) $changeRequest->tenant_id,
            'user_id' => $recipientUserId,
            'type' => $type,
            'priority' => Notification::PRIORITY_NORMAL,
            'title' => $title,
            'body' => $body,
            'channel' => Notification::CHANNEL_INAPP,
            'event_key' => $eventKey,
            'project_id' => $changeRequest->project_id,
            'data' => [
                'change_request_id' => (string) $changeRequest->id,
                'change_request_status' => (string) $changeRequest->status,
            ],
        ]);
    }

    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : '';
    }

    /**
     * Display a listing of change requests.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $query = ChangeRequest::with(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);
            $query->where('tenant_id', $tenantId);

            // Filter by project if specified
            if ($request->has('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            // Filter by status if specified
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by change type if specified
            if ($request->has('change_type')) {
                $query->where('change_type', $request->input('change_type'));
            }

            // Search functionality
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('change_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $changeRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->zenaSuccessResponse($changeRequests, 'Change requests retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve change requests: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created change request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $validator = Validator::make($request->all(), [
                'project_id' => [
                    'required',
                    'string',
                    Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
                ],
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'change_type' => 'required|in:scope,cost,schedule,quality,design,other',
                'impact_analysis' => 'required|string',
                'cost_impact' => 'nullable|numeric|min:0',
                'schedule_impact_days' => 'nullable|integer|min:0',
                'priority' => 'required|in:low,medium,high,urgent',
                'justification' => 'required|string',
                'alternatives_considered' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $changeRequest = ChangeRequest::create([
                'tenant_id' => $tenantId,
                'project_id' => $request->input('project_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'change_type' => $request->input('change_type'),
                'impact_analysis' => $request->input('impact_analysis'),
                'cost_impact' => $request->input('cost_impact'),
                'schedule_impact_days' => $request->input('schedule_impact_days'),
                'priority' => $request->input('priority'),
                'justification' => $request->input('justification'),
                'alternatives_considered' => $request->input('alternatives_considered'),
                'status' => 'draft',
                'requested_at' => now(),
                'requested_by' => $user->id,
                'change_number' => $this->generateChangeRequestNumber($request->input('project_id'), $tenantId),
            ]);

            $changeRequest->load(['project:id,name', 'requestedBy:id,name']);

            return $this->zenaSuccessResponse($changeRequest, 'Change request created successfully', 201);
        } catch (\Exception $e) {
            return $this->serverError('Failed to create change request: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified change request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::with(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name'])
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $payload = $changeRequest->toArray();
            $payload['affected_scope_summary'] = $this->buildAffectedScopeSummary($changeRequest, $tenantId);

            return $this->successResponse($payload, 'Change request retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve change request: ' . $e->getMessage());
        }
    }

    public function attachLink(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $validator = Validator::make($request->all(), [
                'linked_type' => ['required', 'string', Rule::in(CrLink::VALID_LINKED_TYPES)],
                'linked_id' => ['required', 'string'],
                'link_description' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $linkedType = (string) $request->input('linked_type');
            if (!in_array($linkedType, self::MUTABLE_LINK_TYPES, true)) {
                return $this->validationError([
                    'linked_type' => ['Only task and component links can be mutated on this path.'],
                ]);
            }

            $target = $this->resolveLinkableTarget(
                $changeRequest,
                $tenantId,
                $linkedType,
                (string) $request->input('linked_id')
            );

            if ($target === null) {
                return $this->notFound(ucfirst($linkedType) . ' not found');
            }

            $link = CrLink::firstOrCreate(
                [
                    'change_request_id' => (string) $changeRequest->id,
                    'linked_type' => $linkedType,
                    'linked_id' => (string) $target->getKey(),
                ],
                [
                    'link_description' => $request->input('link_description'),
                ]
            );

            if ($link->wasRecentlyCreated === false && $request->filled('link_description') && $link->link_description !== $request->input('link_description')) {
                $link->forceFill([
                    'link_description' => $request->input('link_description'),
                ])->save();
            }

            return $this->successResponse([
                'change_request_id' => (string) $changeRequest->id,
                'linked_type' => $link->linked_type,
                'linked_id' => (string) $link->linked_id,
                'link_description' => $link->link_description,
            ], 'Change request link attached successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to attach change request link: ' . $e->getMessage());
        }
    }

    public function detachLink(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $validator = Validator::make($request->all(), [
                'linked_type' => ['required', 'string', Rule::in(CrLink::VALID_LINKED_TYPES)],
                'linked_id' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $linkedType = (string) $request->input('linked_type');
            if (!in_array($linkedType, self::MUTABLE_LINK_TYPES, true)) {
                return $this->validationError([
                    'linked_type' => ['Only task and component links can be mutated on this path.'],
                ]);
            }

            $deleted = CrLink::query()
                ->where('change_request_id', (string) $changeRequest->id)
                ->where('linked_type', $linkedType)
                ->where('linked_id', (string) $request->input('linked_id'))
                ->delete();

            if ($deleted === 0) {
                return $this->notFound('Change request link not found');
            }

            return $this->successResponse([
                'change_request_id' => (string) $changeRequest->id,
                'linked_type' => $linkedType,
                'linked_id' => (string) $request->input('linked_id'),
                'removed' => true,
            ], 'Change request link detached successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to detach change request link: ' . $e->getMessage());
        }
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $entries = AuditLog::query()
                ->where('tenant_id', $tenantId)
                ->where('entity_type', 'change_request')
                ->where('entity_id', (string) $changeRequest->id)
                ->whereIn('action', array_keys(self::TIMELINE_ACTION_STATUS_MAP))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(function (AuditLog $auditLog): array {
                    return [
                        'audit_log_id' => (string) $auditLog->id,
                        'action' => (string) $auditLog->action,
                        'status' => self::TIMELINE_ACTION_STATUS_MAP[$auditLog->action] ?? null,
                        'occurred_at' => $auditLog->created_at?->toISOString(),
                        'user_id' => $auditLog->user_id !== null ? (string) $auditLog->user_id : null,
                        'route' => $auditLog->route,
                        'method' => $auditLog->method,
                        'status_code' => $auditLog->status_code,
                    ];
                })
                ->values();

            return $this->zenaSuccessResponse($entries);
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve change request timeline: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified change request.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'change_type' => 'sometimes|in:scope,cost,schedule,quality,design,other',
                'impact_analysis' => 'sometimes|string',
                'cost_impact' => 'nullable|numeric|min:0',
                'schedule_impact_days' => 'nullable|integer|min:0',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'justification' => 'sometimes|string',
                'alternatives_considered' => 'nullable|string',
                'status' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $changeRequest->update($request->only([
                'title', 'description', 'change_type', 'impact_analysis',
                'cost_impact', 'schedule_impact_days', 'priority',
                'justification', 'alternatives_considered'
            ]));

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

            return $this->successResponse($changeRequest, 'Change request updated successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update change request: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified change request.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $changeRequest->delete();

            return $this->successResponse(null, 'Change request deleted successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete change request: ' . $e->getMessage());
        }
    }

    /**
     * Submit change request for approval.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            if ($changeRequest->status !== 'draft') {
                return $this->errorResponse('Only draft change requests can be submitted', 400);
            }

            $changeRequest->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            if (is_string($changeRequest->assigned_to) && $changeRequest->assigned_to !== '') {
                $this->createWorkflowNotification(
                    $changeRequest,
                    $changeRequest->assigned_to,
                    'change_request_submitted',
                    'Change request submitted for approval',
                    $changeRequest->title,
                    'change_request.submitted'
                );
            }

            $changeRequest->load(['project:id,name', 'requestedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.change_request.submit',
                'change_request',
                (string) $changeRequest->id,
                200,
                $changeRequest->project_id,
                $tenantId,
                (string) $user->id
            );

            return $this->successResponse($changeRequest, 'Change request submitted successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to submit change request: ' . $e->getMessage());
        }
    }

    /**
     * Approve change request.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $validator = Validator::make($request->all(), [
                'approval_comments' => 'nullable|string',
                'approved_cost' => 'nullable|numeric|min:0',
                'approved_schedule_days' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            if ($changeRequest->status !== ChangeRequest::STATUS_SUBMITTED) {
                return $this->errorResponse('Only submitted change requests can be approved', 400);
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => 'approved',
                'approval_comments' => $request->input('approval_comments'),
                'approved_cost' => $request->input('approved_cost'),
                'approved_schedule_days' => $request->input('approved_schedule_days'),
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // Update project budget and schedule if approved
            if ($changeRequest->status === 'approved') {
                $project = $changeRequest->project;
                if ($project) {
                    if ($request->input('approved_cost')) {
                        $project->increment('budget_total', $request->input('approved_cost'));
                    }
                    if ($request->input('approved_schedule_days')) {
                        $project->increment('end_date', $request->input('approved_schedule_days'));
                    }
                }
            }

            if (is_string($changeRequest->requested_by) && $changeRequest->requested_by !== '') {
                $this->createWorkflowNotification(
                    $changeRequest,
                    $changeRequest->requested_by,
                    'change_request_approved',
                    'Change request approved',
                    $changeRequest->title,
                    'change_request.approved'
                );
            }

            DB::commit();

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.change_request.approve',
                'change_request',
                (string) $changeRequest->id,
                200,
                $changeRequest->project_id,
                $tenantId,
                (string) $user->id
            );

            return $this->successResponse($changeRequest, 'Change request approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Failed to approve change request: ' . $e->getMessage());
        }
    }

    /**
     * Reject change request.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string',
                'rejection_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            if ($changeRequest->status !== ChangeRequest::STATUS_SUBMITTED) {
                return $this->errorResponse('Only submitted change requests can be rejected', 400);
            }

            $changeRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_comments' => $request->input('rejection_comments'),
                'rejected_by' => $user->id,
                'rejected_at' => now(),
            ]);

            if (is_string($changeRequest->requested_by) && $changeRequest->requested_by !== '') {
                $this->createWorkflowNotification(
                    $changeRequest,
                    $changeRequest->requested_by,
                    'change_request_rejected',
                    'Change request rejected',
                    $changeRequest->title,
                    'change_request.rejected'
                );
            }

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.change_request.reject',
                'change_request',
                (string) $changeRequest->id,
                200,
                $changeRequest->project_id,
                $tenantId,
                (string) $user->id
            );

            return $this->successResponse($changeRequest, 'Change request rejected successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to reject change request: ' . $e->getMessage());
        }
    }

    /**
     * Apply approved change request to project.
     */
    public function apply(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            if ($changeRequest->status !== 'approved') {
                return $this->errorResponse('Only approved change requests can be applied', 400);
            }

            $validator = Validator::make($request->all(), [
                'implementation_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => 'implemented',
                'implementation_notes' => $request->input('implementation_notes'),
                'implemented_by' => $user->id,
                'implemented_at' => now(),
            ]);

            $task = $this->createCanonicalTaskDelta($changeRequest, (string) $user->id);
            $this->createTaskLinkBack($changeRequest, $task);
            $this->createBaselineSnapshot($changeRequest, (string) $user->id);

            DB::commit();

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.change_request.apply',
                'change_request',
                (string) $changeRequest->id,
                200,
                $changeRequest->project_id,
                $tenantId,
                (string) $user->id
            );

            return $this->successResponse($changeRequest, 'Change request applied successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Failed to apply change request: ' . $e->getMessage());
        }
    }

    /**
     * Get impact analysis for change request.
     */
    public function getImpactAnalysis(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $tenantId = $this->tenantId($request);
            if ($tenantId === '') {
                return ErrorEnvelopeService::error(
                    'TENANT_REQUIRED',
                    'Tenant context missing',
                    [],
                    400,
                    ErrorEnvelopeService::getCurrentRequestId()
                );
            }

            $changeRequest = ChangeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($id)
                ->first();

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            $impactAnalysis = [
                'cost_impact' => [
                    'estimated_cost' => $changeRequest->cost_impact,
                    'approved_cost' => $changeRequest->approved_cost,
                    'impact_percentage' => $this->calculateCostImpactPercentage($changeRequest),
                ],
                'schedule_impact' => [
                    'estimated_days' => $changeRequest->schedule_impact_days,
                    'approved_days' => $changeRequest->approved_schedule_days,
                    'impact_percentage' => $this->calculateScheduleImpactPercentage($changeRequest),
                ],
                'scope_impact' => [
                    'change_type' => $changeRequest->change_type,
                    'description' => $changeRequest->description,
                    'impact_analysis' => $changeRequest->impact_analysis,
                ],
                'risk_assessment' => $this->assessRisks($changeRequest),
            ];

            return $this->successResponse($impactAnalysis, 'Impact analysis retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve impact analysis: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique change request number.
     */
    private function generateChangeRequestNumber(string $projectId, string $tenantId): string
    {
        $project = Project::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($projectId)
            ->first();
        $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';
        
        $lastChangeRequest = ChangeRequest::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $sequence = $lastChangeRequest ? (int) substr($lastChangeRequest->change_number, -4) + 1 : 1;
        $sequenceString = (string) $sequence;
        
        return $projectCode . '-CR-' . str_pad($sequenceString, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate cost impact percentage.
     */
    private function calculateCostImpactPercentage(ChangeRequest $changeRequest): float
    {
        $project = $changeRequest->project;
        if (!$project || !$project->budget) {
            return 0;
        }

        $costImpact = $changeRequest->approved_cost ?? $changeRequest->cost_impact ?? 0;
        return round(($costImpact / $project->budget) * 100, 2);
    }

    /**
     * Calculate schedule impact percentage.
     */
    private function calculateScheduleImpactPercentage(ChangeRequest $changeRequest): float
    {
        $project = $changeRequest->project;
        if (!$project || !$project->start_date || !$project->end_date) {
            return 0;
        }

        $totalDays = $project->start_date->diffInDays($project->end_date);
        $scheduleImpact = $changeRequest->approved_schedule_days ?? $changeRequest->schedule_impact_days ?? 0;
        
        return $totalDays > 0 ? round(($scheduleImpact / $totalDays) * 100, 2) : 0;
    }

    /**
     * Assess risks for change request.
     */
    private function assessRisks(ChangeRequest $changeRequest): array
    {
        $risks = [];

        // Cost risk assessment
        if ($changeRequest->cost_impact > 10000) {
            $risks[] = [
                'type' => 'cost_overrun',
                'severity' => 'high',
                'description' => 'Significant cost impact may affect project budget',
            ];
        }

        // Schedule risk assessment
        if ($changeRequest->schedule_impact_days > 30) {
            $risks[] = [
                'type' => 'schedule_delay',
                'severity' => 'high',
                'description' => 'Significant schedule impact may affect project timeline',
            ];
        }

        // Priority risk assessment
        if ($changeRequest->priority === 'urgent') {
            $risks[] = [
                'type' => 'urgency_risk',
                'severity' => 'medium',
                'description' => 'Urgent change request requires immediate attention',
            ];
        }

        return $risks;
    }

    /**
     * Create baseline snapshot before applying changes.
     */
    private function createCanonicalTaskDelta(ChangeRequest $changeRequest, string $userId): Task
    {
        $approvedDays = (int) ($changeRequest->approved_schedule_days ?? $changeRequest->impact_days ?? 0);

        $task = Task::create([
            'tenant_id' => (string) $changeRequest->tenant_id,
            'project_id' => (string) $changeRequest->project_id,
            'name' => 'CR delta: ' . $changeRequest->title,
            'description' => $changeRequest->implementation_notes
                ?? $changeRequest->description
                ?? 'Canonical delta task created from applied change request.',
            'status' => Task::STATUS_PENDING,
            'priority' => $this->normalizeTaskPriority((string) $changeRequest->priority),
            'start_date' => now()->toDateString(),
            'end_date' => $approvedDays > 0 ? now()->addDays($approvedDays)->toDateString() : now()->toDateString(),
        ]);

        if ($changeRequest->task_id !== $task->id) {
            $changeRequest->forceFill(['task_id' => $task->id])->save();
        }

        return $task;
    }

    private function createTaskLinkBack(ChangeRequest $changeRequest, Task $task): void
    {
        CrLink::firstOrCreate(
            [
                'change_request_id' => (string) $changeRequest->id,
                'linked_type' => CrLink::LINKED_TYPE_TASK,
                'linked_id' => (string) $task->id,
            ],
            [
                'link_description' => 'Canonical task delta created during apply()',
            ]
        );
    }

    private function createBaselineSnapshot(ChangeRequest $changeRequest, string $userId): Baseline
    {
        $project = $changeRequest->project;
        $latestBaselineVersion = (int) Baseline::query()
            ->where('project_id', $changeRequest->project_id)
            ->where('type', Baseline::TYPE_CONTRACT)
            ->max('version');

        $approvedDays = (int) ($changeRequest->approved_schedule_days ?? $changeRequest->impact_days ?? 0);
        $startDate = $project?->start_date?->toDateString() ?? now()->toDateString();
        $endDate = $project?->end_date?->copy()?->addDays($approvedDays)->toDateString()
            ?? now()->addDays(max($approvedDays, 1))->toDateString();
        $cost = (float) ($changeRequest->approved_cost ?? $changeRequest->impact_cost ?? 0);

        $baseline = Baseline::create([
            'project_id' => (string) $changeRequest->project_id,
            'type' => Baseline::TYPE_CONTRACT,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cost' => $cost,
            'linked_contract_id' => (string) $changeRequest->id,
            'version' => $latestBaselineVersion + 1,
            'note' => 'Applied from change request ' . ($changeRequest->change_number ?? $changeRequest->id),
            'created_by' => $userId,
        ]);

        BaselineHistory::create([
            'baseline_id' => (string) $baseline->id,
            'from_version' => $latestBaselineVersion,
            'to_version' => (int) $baseline->version,
            'note' => 'Canonical baseline delta recorded during apply()',
            'created_by' => $userId,
        ]);

        return $baseline;
    }

    private function normalizeTaskPriority(string $priority): string
    {
        return match ($priority) {
            'urgent' => Task::PRIORITY_CRITICAL,
            'critical' => Task::PRIORITY_CRITICAL,
            'high' => Task::PRIORITY_HIGH,
            'low' => Task::PRIORITY_LOW,
            default => Task::PRIORITY_MEDIUM,
        };
    }

    private function buildAffectedScopeSummary(ChangeRequest $changeRequest, string $tenantId): array
    {
        $taskIds = CrLink::query()
            ->where('change_request_id', (string) $changeRequest->id)
            ->where('linked_type', CrLink::LINKED_TYPE_TASK)
            ->pluck('linked_id');

        $componentIds = CrLink::query()
            ->where('change_request_id', (string) $changeRequest->id)
            ->where('linked_type', CrLink::LINKED_TYPE_COMPONENT)
            ->pluck('linked_id');

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $changeRequest->project_id)
            ->whereIn('id', $taskIds)
            ->get(['id', 'project_id', 'component_id', 'name', 'title', 'status', 'priority'])
            ->map(fn (Task $task): array => [
                'id' => (string) $task->id,
                'project_id' => (string) $task->project_id,
                'component_id' => $task->component_id !== null ? (string) $task->component_id : null,
                'name' => $task->name,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
            ])
            ->values()
            ->all();

        $components = Component::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $changeRequest->project_id)
            ->whereIn('id', $componentIds)
            ->get(['id', 'project_id', 'parent_component_id', 'name', 'status', 'type'])
            ->map(fn (Component $component): array => [
                'id' => (string) $component->id,
                'project_id' => (string) $component->project_id,
                'parent_component_id' => $component->parent_component_id !== null ? (string) $component->parent_component_id : null,
                'name' => $component->name,
                'status' => $component->status,
                'type' => $component->type,
            ])
            ->values()
            ->all();

        $documents = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $changeRequest->project_id)
            ->where('linked_entity_type', Document::ENTITY_TYPE_CR)
            ->where('linked_entity_id', (string) $changeRequest->id)
            ->get(['id', 'project_id', 'title', 'linked_entity_type', 'linked_entity_id', 'status', 'document_type'])
            ->map(fn (Document $document): array => [
                'id' => (string) $document->id,
                'project_id' => (string) $document->project_id,
                'title' => $document->title,
                'linked_entity_type' => $document->linked_entity_type,
                'linked_entity_id' => $document->linked_entity_id,
                'status' => $document->status,
                'document_type' => $document->document_type,
            ])
            ->values()
            ->all();

        return [
            'tasks' => $tasks,
            'components' => $components,
            'documents' => $documents,
        ];
    }

    private function resolveLinkableTarget(
        ChangeRequest $changeRequest,
        string $tenantId,
        string $linkedType,
        string $linkedId
    ): Task|Component|null {
        return match ($linkedType) {
            CrLink::LINKED_TYPE_TASK => Task::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', (string) $changeRequest->project_id)
                ->whereKey($linkedId)
                ->first(),
            CrLink::LINKED_TYPE_COMPONENT => Component::query()
                ->where('tenant_id', $tenantId)
                ->where('project_id', (string) $changeRequest->project_id)
                ->whereKey($linkedId)
                ->first(),
            default => null,
        };
    }
}
