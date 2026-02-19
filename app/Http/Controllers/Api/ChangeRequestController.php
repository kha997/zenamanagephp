<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChangeRequestController extends BaseApiController
{
    use ZenaContractResponseTrait;
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

            $tenantId = $this->resolveTenantId($request);
            if (!$tenantId) {
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

            $tenantId = $this->resolveTenantId($request);
            if (!$tenantId) {
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
                'change_number' => $this->generateChangeRequestNumber($request->input('project_id')),
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
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $changeRequest = ChangeRequest::with(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name'])
                ->find($id);

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            return $this->successResponse($changeRequest, 'Change request retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve change request: ' . $e->getMessage());
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

            $changeRequest = ChangeRequest::find($id);

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
                'status' => 'sometimes|in:draft,submitted,pending_approval,approved,rejected,implemented',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $changeRequest->update($request->only([
                'title', 'description', 'change_type', 'impact_analysis',
                'cost_impact', 'schedule_impact_days', 'priority',
                'justification', 'alternatives_considered', 'status'
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
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $changeRequest = ChangeRequest::find($id);

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

            $changeRequest = ChangeRequest::find($id);

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

            $changeRequest->load(['project:id,name', 'requestedBy:id,name']);

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

            $changeRequest = ChangeRequest::find($id);

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

            DB::commit();

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

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

            $changeRequest = ChangeRequest::find($id);

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

            $changeRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_comments' => $request->input('rejection_comments'),
                'rejected_by' => $user->id,
                'rejected_at' => now(),
            ]);

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

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

            $changeRequest = ChangeRequest::find($id);

            if (!$changeRequest) {
                return $this->notFound('Change request not found');
            }

            if ($changeRequest->status !== 'approved') {
                return $this->errorResponse('Only approved change requests can be applied', 400);
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => 'implemented',
                'implemented_by' => $user->id,
                'implemented_at' => now(),
            ]);

            // Create baseline snapshot before applying changes
            $this->createBaselineSnapshot($changeRequest);

            DB::commit();

            $changeRequest->load(['project:id,name', 'requestedBy:id,name', 'approvedBy:id,name']);

            return $this->successResponse($changeRequest, 'Change request applied successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Failed to apply change request: ' . $e->getMessage());
        }
    }

    /**
     * Get impact analysis for change request.
     */
    public function getImpactAnalysis(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $changeRequest = ChangeRequest::find($id);

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
    private function generateChangeRequestNumber(string $projectId): string
    {
        $project = Project::find($projectId);
        $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';
        
        $lastChangeRequest = ChangeRequest::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $sequence = $lastChangeRequest ? (int) substr($lastChangeRequest->change_number, -4) + 1 : 1;
        $sequenceString = (string) $sequence;
        
        return $projectCode . '-CR-' . str_pad($sequenceString, 4, '0', STR_PAD_LEFT);
    }

    private function resolveTenantId(Request $request): ?string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : null;
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
    private function createBaselineSnapshot(ChangeRequest $changeRequest): void
    {
        // This would create a snapshot of the current project state
        // Implementation depends on the baseline management system
        // For now, we'll just log the action
        \Log::info('Creating baseline snapshot for change request: ' . $changeRequest->id);
    }
}
