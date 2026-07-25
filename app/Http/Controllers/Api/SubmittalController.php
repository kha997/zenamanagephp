<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use App\Models\Project;
use App\Models\Submittal;
use App\Services\SubmittalLifecycleService;
use App\Services\ZenaAuditLogger;
use App\Support\SubmittalContentRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmittalController extends ApiBaseController
{
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private SubmittalLifecycleService $lifecycle
    ) {
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

    private function submittalQuery(array $relations = null): Builder
    {
        $relations ??= [
            'project:id,name',
            'submittedBy:id,name',
            'reviewedBy:id,name',
        ];

        $query = Submittal::query()
            ->where('tenant_id', $this->tenantId());

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    private function submittalForTenant(string $id, array $relations = null): Submittal
    {
        return $this->submittalQuery($relations)
            ->where('id', $id)
            ->firstOrFail();
    }

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

            /** @var \App\Models\User $user */

            $this->authorize('viewAny', Submittal::class);

            $query = $this->submittalQuery();

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
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('submittal_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $submittals = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return $this->listSuccessResponse($submittals, 'Submittals retrieved successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
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

            /** @var \App\Models\User $user */

            $this->authorize('create', Submittal::class);

            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
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

            $project = $this->projectForTenant($request->input('project_id'));

            if (!$project) {
                return $this->notFound('Project not found');
            }

            $submittal = Submittal::create([
                'tenant_id' => $this->tenantId(),
                'project_id' => $project->id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'submittal_type' => $request->input('submittal_type'),
                'specification_section' => $request->input('specification_section'),
                'due_date' => $request->input('due_date'),
                'contractor' => $request->input('contractor'),
                'manufacturer' => $request->input('manufacturer'),
                'status' => 'draft',
                'submitted_by' => $user->id,
                'submittal_number' => $this->generateSubmittalNumber($request->input('project_id'), $project),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.create',
                'submittal',
                (string) $submittal->id,
                201,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal created successfully', 201);
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id, [
                'project:id,name',
                'submittedBy:id,name',
                'reviewedBy:id,name',
                'documents',
            ]);

            $this->authorize('view', $submittal);

            return $this->successResponse($submittal, 'Submittal retrieved successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('update', $submittal);

            $validator = Validator::make(
                $request->all(),
                SubmittalContentRules::rules() + ['status' => 'prohibited']
            );

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $data = $request->only([
                'title', 'description', 'submittal_type', 'specification_section',
                'due_date', 'contractor', 'manufacturer',
            ]);

            $submittal = $this->lifecycle->updateContent($submittal, $data, ['actor_user_id' => $user->id]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.update',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal updated successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->validationError(['status' => [$e->getMessage()]]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update submittal: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified submittal.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('delete', $submittal);

            if ($submittal->status !== Submittal::STATUS_DRAFT) {
                return $this->errorResponse('Only draft submittals can be deleted', 400);
            }

            $projectId = $submittal->project_id;
            $submittal->delete();

            $this->auditLogger->log(
                $request,
                'zena.submittal.delete',
                'submittal',
                (string) $submittal->id,
                200,
                $projectId,
                $this->tenantId()
            );

            return $this->successResponse(null, 'Submittal deleted successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('submit', $submittal);

            $validator = Validator::make($request->all(), [
                'revision_summary' => [
                    Rule::requiredIf($submittal->status === Submittal::STATUS_REVISING),
                    'nullable',
                    'string',
                ],
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->submit($submittal, [
                'actor_user_id' => $user->id,
                'revision_summary' => $request->input('revision_summary'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.submit',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal submitted successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to submit submittal: ' . $e->getMessage());
        }
    }

    public function startRevision(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('startRevision', $submittal);

            $submittal = $this->lifecycle->startRevision($submittal, ['actor_user_id' => $user->id]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.start_revision',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal reopened for revision');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to start revision: ' . $e->getMessage());
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);

            $reviewStatus = $request->input('review_status') ?? $request->input('status');
            $reviewComments = $request->input('review_comments') ?? $request->input('review_notes');

            $validator = Validator::make([
                'review_status' => $reviewStatus,
                'review_comments' => $reviewComments,
                'review_notes' => $request->input('review_notes'),
            ], [
                'review_status' => 'required|in:approved,rejected',
                'review_comments' => 'required|string',
                'review_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $ability = $reviewStatus === 'approved' ? 'approve' : 'reject';
            $this->authorize($ability, $submittal);

            $context = ['actor_user_id' => $user->id];

            $submittal = $reviewStatus === 'approved'
                ? $this->lifecycle->approve($submittal, $context + ['approval_comments' => $reviewComments])
                : $this->lifecycle->reject($submittal, $context + [
                    'rejection_reason' => $reviewComments,
                    'rejection_comments' => $request->input('review_notes'),
                ]);

            $submittal->forceFill([
                'review_comments' => $reviewComments,
                'review_notes' => $request->input('review_notes'),
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ])->save();

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.review',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal reviewed successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('approve', $submittal);

            $validator = Validator::make($request->all(), [
                'approval_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->approve($submittal, [
                'actor_user_id' => $user->id,
                'approval_comments' => $request->input('approval_comments'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.approve',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal approved successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
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

            /** @var \App\Models\User $user */

            $submittal = $this->submittalForTenant($id);
            $this->authorize('reject', $submittal);

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string',
                'rejection_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->reject($submittal, [
                'actor_user_id' => $user->id,
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_comments' => $request->input('rejection_comments'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.reject',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal rejected successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to reject submittal: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique submittal number.
     */
    private function generateSubmittalNumber(string $projectId, ?Project $project = null): string
    {
        return DB::transaction(function () use ($projectId, $project): string {
            $project ??= $this->projectForTenant($projectId);
            $projectCode = $project ? strtoupper(substr($project->name, 0, 3)) : 'PRJ';

            $lastSubmittal = Submittal::where('tenant_id', $this->tenantId())
                ->where('project_id', $projectId)
                ->orderBy('created_at', 'desc')
                ->lockForUpdate()
                ->first();

            $sequence = $lastSubmittal ? (int) substr($lastSubmittal->submittal_number, -4) + 1 : 1;

            return $projectCode . '-SUB-' . sprintf('%04d', $sequence);
        });
    }

    private function projectForTenant(string $projectId): ?Project
    {
        return Project::where('id', $projectId)
            ->where('tenant_id', $this->tenantId())
            ->first();
    }
}
