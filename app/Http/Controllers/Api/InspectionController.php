<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Services\ZenaAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            'workInstanceStep.workInstance.templateVersion.template',
            'workInstanceStep.values',
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
            $inspections->getCollection()->transform(fn (QcInspection $inspection): array => $this->transformInspection($inspection));

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
                'work_instance_step_id' => 'nullable|string',
                'checklist_results' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $plan = $this->qcPlanForTenant($request->input('qc_plan_id'));

            if (!$plan) {
                return $this->notFound('QC plan not found');
            }

            $linkedStep = $this->resolveInspectionStepForPlan($plan, $request->input('work_instance_step_id'));
            if ($request->filled('work_instance_step_id') && !$linkedStep) {
                return $this->errorResponse('Inspection checklist instance not available for this QC plan', 422);
            }

            $checklistResults = $request->input('checklist_results');
            if ($linkedStep) {
                $checklistResults = is_array($checklistResults)
                    ? $this->normalizeChecklistResults($linkedStep, $checklistResults)
                    : $this->defaultChecklistResults($linkedStep);
            }

            $inspection = DB::transaction(function () use ($request, $plan, $linkedStep, $checklistResults): QcInspection {
                $inspection = QcInspection::create([
                    'tenant_id' => $this->tenantId(),
                    'qc_plan_id' => $plan->id,
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'inspection_date' => $request->input('inspection_date'),
                    'inspector_id' => $request->input('inspector_id'),
                    'work_instance_step_id' => $linkedStep?->id,
                    'checklist_results' => $checklistResults,
                    'status' => 'scheduled',
                ]);

                if ($linkedStep && is_array($checklistResults) && $checklistResults !== []) {
                    $this->syncChecklistValuesToStep($linkedStep, $checklistResults);
                }

                return $inspection;
            });

            $inspection->load([
                'qcPlan:id,title,project_id',
                'inspector:id,name',
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            $this->auditLogger->log(
                $request,
                'zena.inspection.create',
                'inspection',
                (string) $inspection->id,
                201,
                $plan->project_id,
                $this->tenantId()
            );

            return $this->successResponse($this->transformInspection($inspection), 'Inspection created successfully', 201);
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
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            return $this->successResponse($this->transformInspection($inspection), 'Inspection retrieved successfully');
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
                'work_instance_step_id' => 'nullable|string',
                'checklist_results' => 'nullable|array',
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

            if ($request->has('work_instance_step_id')) {
                $plan = $inspection->qcPlan ?? $this->qcPlanForTenant((string) $inspection->qc_plan_id);
                $linkedStep = $plan ? $this->resolveInspectionStepForPlan($plan, $request->input('work_instance_step_id')) : null;

                if ($request->filled('work_instance_step_id') && !$linkedStep) {
                    return $this->errorResponse('Inspection checklist instance not available for this QC plan', 422);
                }

                $data['work_instance_step_id'] = $linkedStep?->id;

                if ($linkedStep && $request->has('checklist_results')) {
                    $data['checklist_results'] = is_array($request->input('checklist_results'))
                        ? $this->normalizeChecklistResults($linkedStep, $request->input('checklist_results', []))
                        : [];
                }
            }

            $inspection->update(array_filter($data, fn($value) => $value !== null));

            $inspection->load([
                'qcPlan:id,title,project_id',
                'inspector:id,name',
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            $this->auditLogger->log(
                $request,
                'zena.inspection.update',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($this->transformInspection($inspection), 'Inspection updated successfully');
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

            $inspection->load([
                'qcPlan:id,title,project_id',
                'inspector:id,name',
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            $this->auditLogger->log(
                $request,
                'zena.inspection.schedule',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($this->transformInspection($inspection), 'Inspection scheduled successfully');
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

            $inspection = DB::transaction(function () use ($inspection, $request): QcInspection {
                $checklistResults = $request->input('checklist_results');

                if ($inspection->workInstanceStep) {
                    $checklistResults = is_array($checklistResults)
                        ? $this->normalizeChecklistResults($inspection->workInstanceStep, $checklistResults)
                        : $inspection->checklist_results;
                }

                $inspection->update([
                    'status' => 'in_progress',
                    'findings' => $request->input('findings'),
                    'recommendations' => $request->input('recommendations'),
                    'checklist_results' => $checklistResults,
                    'photos' => $request->input('photos'),
                ]);

                if ($inspection->workInstanceStep && is_array($checklistResults)) {
                    $this->syncChecklistValuesToStep($inspection->workInstanceStep, $checklistResults);
                    $inspection->workInstanceStep->update([
                        'status' => 'in_progress',
                        'started_at' => $inspection->workInstanceStep->started_at ?? now(),
                    ]);
                }

                return $inspection;
            });

            $inspection->load([
                'qcPlan:id,title,project_id',
                'inspector:id,name',
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            $this->auditLogger->log(
                $request,
                'zena.inspection.conduct',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($this->transformInspection($inspection), 'Inspection conducted successfully');
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

            $inspection = DB::transaction(function () use ($inspection, $request): QcInspection {
                $checklistResults = $request->input('checklist_results');

                if ($inspection->workInstanceStep) {
                    $checklistResults = is_array($checklistResults)
                        ? $this->normalizeChecklistResults($inspection->workInstanceStep, $checklistResults)
                        : $inspection->checklist_results;
                }

                $inspection->update([
                    'status' => 'completed',
                    'findings' => $request->input('findings'),
                    'recommendations' => $request->input('recommendations'),
                    'checklist_results' => $checklistResults,
                    'photos' => $request->input('photos'),
                ]);

                if ($inspection->workInstanceStep && is_array($checklistResults)) {
                    $this->syncChecklistValuesToStep($inspection->workInstanceStep, $checklistResults);
                    $inspection->workInstanceStep->update([
                        'status' => 'completed',
                        'started_at' => $inspection->workInstanceStep->started_at ?? now(),
                        'completed_at' => now(),
                    ]);
                }

                return $inspection;
            });

            $inspection->load([
                'qcPlan:id,title,project_id',
                'inspector:id,name',
                'workInstanceStep.workInstance.templateVersion.template',
                'workInstanceStep.values',
            ]);

            $this->auditLogger->log(
                $request,
                'zena.inspection.complete',
                'inspection',
                (string) $inspection->id,
                200,
                $inspection->qcPlan?->project_id,
                $this->tenantId()
            );

            return $this->successResponse($this->transformInspection($inspection), 'Inspection completed successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Inspection not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to complete inspection: ' . $e->getMessage());
        }
    }

    private function resolveInspectionStepForPlan(QcPlan $plan, mixed $stepId): ?WorkInstanceStep
    {
        if (!is_string($stepId) || $stepId === '') {
            return null;
        }

        return WorkInstanceStep::query()
            ->with(['workInstance.templateVersion.template', 'values'])
            ->where('tenant_id', $this->tenantId())
            ->whereKey($stepId)
            ->where('type', 'inspection')
            ->whereHas('workInstance', function (Builder $query) use ($plan): void {
                $query->where('tenant_id', $this->tenantId())
                    ->where('project_id', $plan->project_id);
            })
            ->first();
    }

    /**
     * @param array<int, array<string, mixed>> $checklistResults
     * @return array<int, array<string, mixed>>
     */
    private function normalizeChecklistResults(WorkInstanceStep $step, array $checklistResults): array
    {
        $incomingByItemKey = collect($checklistResults)
            ->filter(fn ($item): bool => is_array($item))
            ->keyBy(function (array $item): string {
                $itemKey = $item['item_key'] ?? null;
                if (is_string($itemKey) && $itemKey !== '') {
                    return $itemKey;
                }

                $fieldKey = $item['field_key'] ?? null;
                return is_string($fieldKey) ? Str::after($fieldKey, 'checklist:') : '';
            });

        return collect($this->defaultChecklistResults($step))
            ->map(function (array $item) use ($incomingByItemKey): array {
                $incoming = $incomingByItemKey->get((string) $item['item_key']);

                if (!is_array($incoming)) {
                    return $item;
                }

                $result = $incoming['result'] ?? $incoming['value'] ?? null;
                if (is_bool($result)) {
                    $result = $result ? 'pass' : 'fail';
                }

                return [
                    'item_key' => $item['item_key'],
                    'field_key' => $item['field_key'],
                    'label' => $item['label'],
                    'required' => $item['required'],
                    'result' => is_scalar($result) ? (string) $result : null,
                    'notes' => is_scalar($incoming['notes'] ?? null) ? (string) $incoming['notes'] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultChecklistResults(WorkInstanceStep $step): array
    {
        return collect($step->snapshot_fields_json ?? [])
            ->filter(fn ($field): bool => is_array($field) && str_starts_with((string) ($field['field_key'] ?? ''), 'checklist:'))
            ->map(static function (array $field): array {
                $fieldKey = (string) ($field['field_key'] ?? '');

                return [
                    'item_key' => Str::after($fieldKey, 'checklist:'),
                    'field_key' => $fieldKey,
                    'label' => (string) ($field['label'] ?? $fieldKey),
                    'required' => (bool) ($field['required'] ?? false),
                    'result' => null,
                    'notes' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $checklistResults
     */
    private function syncChecklistValuesToStep(WorkInstanceStep $step, array $checklistResults): void
    {
        foreach ($checklistResults as $item) {
            if (!is_array($item)) {
                continue;
            }

            $fieldKey = $item['field_key'] ?? null;
            if (!is_string($fieldKey) || !str_starts_with($fieldKey, 'checklist:')) {
                $itemKey = $item['item_key'] ?? null;
                if (!is_string($itemKey) || $itemKey === '') {
                    continue;
                }

                $fieldKey = 'checklist:' . $itemKey;
            }

            WorkInstanceFieldValue::query()->updateOrCreate(
                [
                    'tenant_id' => $this->tenantId(),
                    'work_instance_step_id' => (string) $step->id,
                    'field_key' => $fieldKey,
                ],
                [
                    'value_string' => null,
                    'value_number' => null,
                    'value_date' => null,
                    'value_datetime' => null,
                    'value_json' => [
                        'result' => $item['result'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ],
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function generatedChecklistPayload(?WorkInstanceStep $step): ?array
    {
        if (!$step) {
            return null;
        }

        $valuesByFieldKey = $step->values->keyBy('field_key');
        $items = collect($this->defaultChecklistResults($step))
            ->map(function (array $item) use ($valuesByFieldKey): array {
                $value = $valuesByFieldKey->get((string) $item['field_key']);
                $current = is_array($value?->value_json) ? $value->value_json : null;

                return [
                    'item_key' => $item['item_key'],
                    'field_key' => $item['field_key'],
                    'label' => $item['label'],
                    'required' => $item['required'],
                    'result' => $current['result'] ?? $value?->value_string,
                    'notes' => $current['notes'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'work_instance_id' => (string) ($step->work_instance_id ?? ''),
            'work_instance_step_id' => (string) $step->id,
            'step_key' => (string) ($step->step_key ?? ''),
            'step_type' => (string) ($step->type ?? ''),
            'template_id' => (string) ($step->workInstance?->templateVersion?->template?->id ?? ''),
            'template_code' => (string) ($step->workInstance?->templateVersion?->template?->code ?? ''),
            'template_name' => (string) ($step->workInstance?->templateVersion?->template?->name ?? ''),
            'template_version_id' => (string) ($step->workInstance?->templateVersion?->id ?? ''),
            'template_semver' => (string) ($step->workInstance?->templateVersion?->semver ?? ''),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformInspection(QcInspection $inspection): array
    {
        return array_merge($inspection->toArray(), [
            'generated_checklist' => $this->generatedChecklistPayload($inspection->workInstanceStep),
        ]);
    }
}
