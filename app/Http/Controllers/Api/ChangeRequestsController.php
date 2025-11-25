<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Models\ChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChangeRequestsController extends Controller
{
    use ResolvesTenantContext;

    /**
     * Get tenant ID from request context (throws if not found)
     */
    protected function getTenantId(Request $request): string
    {
        $tenantId = $this->resolveActiveTenantIdFromRequest($request);
        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID not found for user');
        }
        return $tenantId;
    }
    /**
     * Display a listing of change requests
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId($request);
            $query = ChangeRequest::where('tenant_id', $tenantId);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('change_number', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('project_id')) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->filled('change_type')) {
                $query->where('change_type', $request->change_type);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $changeRequests = $query->with(['project', 'requester'])->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $changeRequests->items(),
                'meta' => [
                    'total' => $changeRequests->total(),
                    'per_page' => $changeRequests->perPage(),
                    'current_page' => $changeRequests->currentPage(),
                    'last_page' => $changeRequests->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created change request
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'project_id' => 'required|string|exists:projects,id',
                'change_type' => 'nullable|string|in:scope,schedule,budget,quality,other',
                'priority' => 'nullable|string|in:low,medium,high,urgent',
                'estimated_cost' => 'nullable|numeric|min:0',
                'estimated_days' => 'nullable|integer|min:0',
                'due_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            
            DB::beginTransaction();

            $tenantId = $this->getTenantId($request);
            
            // Generate change number if not provided
            $changeNumber = $request->get('change_number');
            if (!$changeNumber) {
                $count = ChangeRequest::where('tenant_id', $tenantId)->count();
                $changeNumber = 'CR-' . str_pad((string)($count + 1), 6, '0', STR_PAD_LEFT);
            }

            $changeRequest = ChangeRequest::create([
                'tenant_id' => $tenantId,
                'project_id' => $request->project_id,
                'change_number' => $changeNumber,
                'title' => $request->title,
                'description' => $request->description,
                'change_type' => $request->change_type ?? 'scope',
                'priority' => $request->priority ?? 'medium',
                'status' => ChangeRequest::STATUS_DRAFT,
                'estimated_cost' => $request->estimated_cost,
                'estimated_days' => $request->estimated_days,
                'due_date' => $request->due_date,
                'requested_by' => $user->id,
                'requested_at' => now(),
            ]);

            DB::commit();

            Log::info('Change request created via API', [
                'change_request_id' => $changeRequest->id,
                'title' => $changeRequest->title,
                'tenant_id' => $changeRequest->tenant_id,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $changeRequest,
                'message' => 'Change request created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Change request creation failed via API', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'created_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to create change request', 500);
        }
    }

    /**
     * Display the specified change request
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($id);

            $changeRequest->load(['project', 'requester', 'approvals', 'comments']);

            return response()->json([
                'success' => true,
                'data' => $changeRequest
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified change request
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($id);

            // Only allow updates when in draft status
            if ($changeRequest->status !== ChangeRequest::STATUS_DRAFT) {
                return $this->errorResponse('Change request can only be updated when in draft status', 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'change_type' => 'sometimes|string|in:scope,schedule,budget,quality,other',
                'priority' => 'sometimes|string|in:low,medium,high,urgent',
                'estimated_cost' => 'nullable|numeric|min:0',
                'estimated_days' => 'nullable|integer|min:0',
                'due_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $changeRequest->update($request->only([
                'title', 'description', 'change_type', 'priority',
                'estimated_cost', 'estimated_days', 'due_date'
            ]));

            DB::commit();

            Log::info('Change request updated via API', [
                'change_request_id' => $changeRequest->id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $changeRequest,
                'message' => 'Change request updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Change request update failed via API', [
                'error' => $e->getMessage(),
                'change_request_id' => $changeRequest->id,
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update change request', 500);
        }
    }

    /**
     * Remove the specified change request
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($id);

            // Only allow deletion when in draft status
            if ($changeRequest->status !== ChangeRequest::STATUS_DRAFT) {
                return $this->errorResponse('Change request can only be deleted when in draft status', 403);
            }

            DB::beginTransaction();

            $title = $changeRequest->title;
            $changeRequest->delete();

            DB::commit();

            Log::info('Change request deleted via API', [
                'change_request_id' => $changeRequest->id,
                'title' => $title,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Change request deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Change request deletion failed via API', [
                'error' => $e->getMessage(),
                'change_request_id' => $changeRequest->id,
                'deleted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to delete change request', 500);
        }
    }

    /**
     * Submit change request for approval
     */
    public function submit(Request $request, string $changeRequest): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($changeRequest);

            // Only allow submit when in draft status
            if ($changeRequest->status !== ChangeRequest::STATUS_DRAFT) {
                return $this->errorResponse('Change request can only be submitted when in draft status', 403);
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
                'requested_at' => now(),
            ]);

            DB::commit();

            Log::info('Change request submitted via API', [
                'change_request_id' => $changeRequest->id,
                'submitted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $changeRequest,
                'message' => 'Change request submitted for approval'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse('Failed to submit change request', 500);
        }
    }

    /**
     * Approve change request
     */
    public function approve(Request $request, string $changeRequest): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($changeRequest);

            // Only allow approve when awaiting approval
            if ($changeRequest->status !== ChangeRequest::STATUS_AWAITING_APPROVAL) {
                return $this->errorResponse('Change request can only be approved when awaiting approval', 403);
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => ChangeRequest::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_notes' => $request->get('decision_note'),
            ]);

            DB::commit();

            Log::info('Change request approved via API', [
                'change_request_id' => $changeRequest->id,
                'approved_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $changeRequest,
                'message' => 'Change request approved'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse('Failed to approve change request', 500);
        }
    }

    /**
     * Reject change request
     */
    public function reject(Request $request, string $changeRequest): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId($request);
            $changeRequest = ChangeRequest::where('tenant_id', $tenantId)->findOrFail($changeRequest);

            // Only allow reject when awaiting approval
            if ($changeRequest->status !== ChangeRequest::STATUS_AWAITING_APPROVAL) {
                return $this->errorResponse('Change request can only be rejected when awaiting approval', 403);
            }

            $validator = Validator::make($request->all(), [
                'decision_note' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $changeRequest->update([
                'status' => ChangeRequest::STATUS_REJECTED,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $request->get('decision_note'),
            ]);

            DB::commit();

            Log::info('Change request rejected via API', [
                'change_request_id' => $changeRequest->id,
                'rejected_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $changeRequest,
                'message' => 'Change request rejected'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse('Failed to reject change request', 500);
        }
    }

    /**
     * Get KPIs for change requests
     */
    public function getKpis(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', 'week');

            $tenantId = $this->getTenantId($request);
            $query = ChangeRequest::where('tenant_id', $tenantId);

            // Apply period filter
            if ($period === 'week') {
                $query->where('created_at', '>=', now()->subWeek());
            } elseif ($period === 'month') {
                $query->where('created_at', '>=', now()->subMonth());
            }

            $total = $query->count();
            $pending = $query->where('status', ChangeRequest::STATUS_AWAITING_APPROVAL)->count();
            $approved = $query->where('status', ChangeRequest::STATUS_APPROVED)->count();
            $rejected = $query->where('status', ChangeRequest::STATUS_REJECTED)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get alerts for change requests
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId($request);
            
            // Get change requests that need attention
            $alerts = [];
            
            // Overdue change requests
            $overdue = ChangeRequest::where('tenant_id', $tenantId)
                ->where('status', ChangeRequest::STATUS_AWAITING_APPROVAL)
                ->where('due_date', '<', now())
                ->get();
            
            foreach ($overdue as $cr) {
                $alerts[] = [
                    'id' => 'cr_overdue_' . $cr->id,
                    'message' => "Change request '{$cr->title}' is overdue",
                    'type' => 'warning',
                    'priority' => 8,
                    'created_at' => $cr->due_date,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get activity for change requests
     */
    public function getActivity(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId($request);
            $limit = (int) $request->get('limit', 10);

            // Get recent change requests as activity
            $changeRequests = ChangeRequest::where('tenant_id', $tenantId)
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();

            $activities = $changeRequests->map(function ($cr) {
                return [
                    'id' => 'cr_' . $cr->id,
                    'type' => 'change_request',
                    'action' => $cr->status === ChangeRequest::STATUS_APPROVED ? 'approved' :
                               ($cr->status === ChangeRequest::STATUS_REJECTED ? 'rejected' :
                               ($cr->status === ChangeRequest::STATUS_AWAITING_APPROVAL ? 'submitted' : 'created')),
                    'description' => "Change request '{$cr->title}' was " . 
                                   ($cr->status === ChangeRequest::STATUS_APPROVED ? 'approved' :
                                    ($cr->status === ChangeRequest::STATUS_REJECTED ? 'rejected' :
                                    ($cr->status === ChangeRequest::STATUS_AWAITING_APPROVAL ? 'submitted' : 'created'))),
                    'timestamp' => $cr->updated_at->toISOString(),
                    'user' => $cr->requester ? [
                        'id' => $cr->requester->id,
                        'name' => $cr->requester->name,
                    ] : null,
                    'metadata' => [
                        'change_request_id' => $cr->id,
                        'status' => $cr->status,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Standardized error response with error envelope
     */
    protected function errorResponse(string $message, int $status = 500, $errors = null): JsonResponse
    {
        $errorId = uniqid('err_', true);
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($errors) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $status);
    }
}

