<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InspectionController extends ApiBaseController
{
    public function __construct(private ZenaAuditLogger $auditLogger)
    {
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

    private function inspectionQuery(array $relations = null): Builder
    {
        $relations ??= [
            'qcPlan:id,title,project_id',
            'inspector:id,name',
        ];

        $query = QcInspection::query()
            ->where('tenant_id', $this->tenantId());

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    private function inspectionForTenant(string $id, array $relations = null): QcInspection
    {
        return $this->inspectionQuery($relations)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function qcPlanForTenant(string $planId): ?QcPlan
    {
        return QcPlan::where('id', $planId)
            ->where('tenant_id', $this->tenantId())
            ->first();
    }

    /**
     * Display a listing of inspections.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $query = $this->inspectionQuery();

            if ($request->has('qc_plan_id')) {
                $query->where('qc_plan_id', $request->input('qc_plan_id'));
            }

            if ($request->has('project_id')) {
                $query->whereHas('qcPlan', function ($q) use ($request) {
                    $q->where('project_id', $request->input('project_id'));
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('inspector_id')) {
                $query->where('inspector_id', $request->input('inspector_id'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', $this->defaultLimit);
            $perPage = min($perPage, $this->maxLimit);

            $inspections = $query->orderBy('inspection_date', 'desc')->paginate($perPage);

            return $this->listSuccessResponse($inspections, 'Inspections retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve inspections: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created inspection.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $validator = Validator::make($request->all(), [
                'qc_plan_id' => 'required|exists:qc_plans,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'inspection_date' => 'required|date',
                'inspector_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $plan = $this->qcPlanForTenant($request->input('qc_plan_id'));

            if (!$plan) {
                return $this->notFound('QC plan not found');
            }

            $inspection = QcInspection::create([
                'tenant_id' => $this->tenantId(),
                'qc_plan_id' => $plan->id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'inspection_date' => $request->input('inspection_date'),
                'inspector_id' => $request->input('inspector_id'),
                'status' => 'scheduled',
            ]);

            $inspection->load(['qcPlan:id,title,project_id', 'inspector:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.inspection.create',
                'inspection',
                (string) $inspection->id,
                201,
                $plan->project_id,
                $this->tenantId()
            );

            return $this->successResponse($inspection, 'Inspection created successfully', 201);
        } catch (\Exception $e) {
            return $this->serverError('Failed to create inspection: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified inspection.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id, [
                'qcPlan:id,title,project_id',
                'inspector:id,name',
            ]);

            return $this->successResponse($inspection, 'Inspection retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve inspection: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified inspection.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id);

            $validator = Validator::make($request->all(), [
                'qc_plan_id' => 'sometimes|exists:qc_plans,id',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'inspection_date' => 'nullable|date',
                'inspector_id' => 'sometimes|exists:users,id',
                'status' => 'sometimes|in:scheduled,in_progress,completed,failed',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $data = $request->only([
                'title',
                'description',
                'inspection_date',
                'inspector_id',
                'status',
            ]);

            if ($request->has('qc_plan_id')) {
                $plan = $this->qcPlanForTenant($request->input('qc_plan_id'));

                if (!$plan) {
                    return $this->notFound('QC plan not found');
                }

                $data['qc_plan_id'] = $plan->id;
            }

            $inspection->update(array_filter($data, fn($value) => $value !== null));

            $inspection->load(['qcPlan:id,title,project_id','inspector:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.inspection.update',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($inspection, 'Inspection updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update inspection: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified inspection.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id);

            $projectId = $inspection->qcPlan?->project_id;
            $inspection->delete();

            $this->auditLogger->log(
                $request,
                'zena.inspection.delete',
                'inspection',
                (string) $inspection->id,
                200,
                $projectId,
                $this->tenantId()
            );

            return $this->successResponse(null, 'Inspection deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete inspection: ' . $e->getMessage());
        }
    }

    /**
     * Schedule an inspection.
     */
    public function schedule(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id);

            $validator = Validator::make($request->all(), [
                'inspection_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $inspection->update([
                'status' => 'scheduled',
                'inspection_date' => $request->input('inspection_date'),
            ]);

            $inspection->load(['qcPlan:id,title,project_id', 'inspector:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.inspection.schedule',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($inspection, 'Inspection scheduled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to schedule inspection: ' . $e->getMessage());
        }
    }

    /**
     * Conduct an inspection.
     */
    public function conduct(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id);

            $validator = Validator::make($request->all(), [
                'findings' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'checklist_results' => 'nullable|array',
                'photos' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $inspection->update([
                'status' => 'in_progress',
                'findings' => $request->input('findings'),
                'recommendations' => $request->input('recommendations'),
                'checklist_results' => $request->input('checklist_results'),
                'photos' => $request->input('photos'),
            ]);

            $inspection->load(['qcPlan:id,title,project_id', 'inspector:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.inspection.conduct',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($inspection, 'Inspection conducted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to conduct inspection: ' . $e->getMessage());
        }
    }

    /**
     * Complete an inspection.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $inspection = $this->inspectionForTenant($id);

            $validator = Validator::make($request->all(), [
                'findings' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'checklist_results' => 'nullable|array',
                'photos' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $inspection->update([
                'status' => 'completed',
                'findings' => $request->input('findings'),
                'recommendations' => $request->input('recommendations'),
                'checklist_results' => $request->input('checklist_results'),
                'photos' => $request->input('photos'),
            ]);

            $inspection->load(['qcPlan:id,title,project_id', 'inspector:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.inspection.complete',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($inspection, 'Inspection completed successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to complete inspection: ' . $e->getMessage());
        }
    }
}
