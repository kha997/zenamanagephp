<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use App\Models\ZenaSubmittal;
use App\Models\ZenaProject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubmittalController extends BaseApiController
{
    /**
     * Display a listing of submittals.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $query = ZenaSubmittal::with(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            // Filter by project if specified
            if ($request->has('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            // Filter by status if specified
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by submittal type if specified
            if ($request->has('submittal_type')) {
                $query->where('submittal_type', $request->input('submittal_type'));
            }

            // Search functionality
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('submittal_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $submittals = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->successResponse($submittals, 'Submittals retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve submittals: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created submittal.
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
                'submittal_type' => 'required|in:shop_drawing,material_sample,product_data,test_report,other',
                'specification_section' => 'nullable|string|max:255',
                'due_date' => 'nullable|date|after:today',
                'contractor' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = ZenaSubmittal::create([
                'project_id' => $request->input('project_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'submittal_type' => $request->input('submittal_type'),
                'specification_section' => $request->input('specification_section'),
                'due_date' => $request->input('due_date'),
                'contractor' => $request->input('contractor'),
                'manufacturer' => $request->input('manufacturer'),
                'status' => 'draft',
                'submitted_by' => $user->id,
                'submittal_number' => $this->generateSubmittalNumber($request->input('project_id')),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal created successfully', 201);
        } catch (\Exception $e) {
            return $this->serverError('Failed to create submittal: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified submittal.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::with(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name', 'attachments'])
                ->find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            return $this->successResponse($submittal, 'Submittal retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve submittal: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified submittal.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'submittal_type' => 'sometimes|in:shop_drawing,material_sample,product_data,test_report,other',
                'specification_section' => 'nullable|string|max:255',
                'due_date' => 'nullable|date',
                'contractor' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'status' => 'sometimes|in:draft,submitted,pending_review,approved,rejected,revised',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal->update($request->only([
                'title', 'description', 'submittal_type', 'specification_section',
                'due_date', 'contractor', 'manufacturer', 'status'
            ]));

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal updated successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update submittal: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified submittal.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            $submittal->delete();

            return $this->successResponse(null, 'Submittal deleted successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete submittal: ' . $e->getMessage());
        }
    }

    /**
     * Submit submittal for review.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            if ($submittal->status !== 'draft') {
                return $this->errorResponse('Only draft submittals can be submitted', 400);
            }

            $submittal->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal submitted successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to submit submittal: ' . $e->getMessage());
        }
    }

    /**
     * Review submittal.
     */
    public function review(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            $validator = Validator::make($request->all(), [
                'review_status' => 'required|in:approved,rejected,revised',
                'review_comments' => 'required|string',
                'review_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal->update([
                'status' => $request->input('review_status'),
                'review_comments' => $request->input('review_comments'),
                'review_notes' => $request->input('review_notes'),
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal reviewed successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to review submittal: ' . $e->getMessage());
        }
    }

    /**
     * Approve submittal.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            $validator = Validator::make($request->all(), [
                'approval_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal->update([
                'status' => 'approved',
                'approval_comments' => $request->input('approval_comments'),
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal approved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to approve submittal: ' . $e->getMessage());
        }
    }

    /**
     * Reject submittal.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = ZenaSubmittal::find($id);

            if (!$submittal) {
                return $this->notFound('Submittal not found');
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string',
                'rejection_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_comments' => $request->input('rejection_comments'),
                'rejected_by' => $user->id,
                'rejected_at' => now(),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            return $this->successResponse($submittal, 'Submittal rejected successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to reject submittal: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique submittal number.
     */
    private function generateSubmittalNumber(string $projectId): string
    {
        $project = ZenaProject::find($projectId);
        $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';
        
        $lastSubmittal = ZenaSubmittal::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $sequence = $lastSubmittal ? (int)substr($lastSubmittal->submittal_number, -4) + 1 : 1;
        
        return $projectCode . '-SUB-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
