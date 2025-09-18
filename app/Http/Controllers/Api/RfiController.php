<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\ZenaRfi;
use App\Models\ZenaProject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RfiController extends BaseApiController
{
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

            $query = ZenaRfi::with(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

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
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('rfi_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $rfis = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($rfis, 'RFIs retrieved successfully');
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
                'project_id' => 'required|exists:zena_projects,id',
                'title' => 'required|string|max:255',
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

            $rfi = ZenaRfi::create([
                'project_id' => $request->input('project_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'priority' => $request->input('priority'),
                'due_date' => $request->input('due_date'),
                'assigned_to' => $request->input('assigned_to'),
                'location' => $request->input('location'),
                'drawing_reference' => $request->input('drawing_reference'),
                'status' => 'pending',
                'created_by' => $user->id,
                'rfi_number' => $this->generateRfiNumber($request->input('project_id')),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

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

            $rfi = ZenaRfi::with(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name', 'attachments'])
                ->find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

            return $this->successResponse($rfi, 'RFI retrieved successfully');
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

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'due_date' => 'nullable|date',
                'assigned_to' => 'nullable|exists:users,id',
                'location' => 'nullable|string|max:255',
                'drawing_reference' => 'nullable|string|max:255',
                'status' => 'sometimes|in:pending,in_progress,answered,closed',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $rfi->update($request->only([
                'title', 'description', 'priority', 'due_date', 
                'assigned_to', 'location', 'drawing_reference', 'status'
            ]));

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

            return $this->successResponse($rfi, 'RFI updated successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update RFI: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified RFI.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

            $rfi->delete();

            return $this->successResponse(null, 'RFI deleted successfully');
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

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

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

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

            return $this->successResponse($rfi, 'RFI assigned successfully');
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

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

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

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

            return $this->successResponse($rfi, 'RFI response submitted successfully');
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

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

            if ($rfi->status !== 'answered') {
                return $this->errorResponse('RFI must be answered before it can be closed', 400);
            }

            $rfi->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

            return $this->successResponse($rfi, 'RFI closed successfully');
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

            $rfi = ZenaRfi::find($id);

            if (!$rfi) {
                return $this->notFound('RFI not found');
            }

            $validator = Validator::make($request->all(), [
                'escalation_reason' => 'required|string',
                'escalated_to' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $rfi->update([
                'status' => 'escalated',
                'escalated_to' => $request->input('escalated_to'),
                'escalation_reason' => $request->input('escalation_reason'),
                'escalated_by' => $user->id,
                'escalated_at' => now(),
            ]);

            $rfi->load(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name']);

            return $this->successResponse($rfi, 'RFI escalated successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to escalate RFI: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique RFI number.
     */
    private function generateRfiNumber(string $projectId): string
    {
        $project = ZenaProject::find($projectId);
        $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';
        
        $lastRfi = ZenaRfi::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $sequence = $lastRfi ? (int)substr($lastRfi->rfi_number, -4) + 1 : 1;
        
        return $projectCode . '-RFI-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
