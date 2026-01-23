<?php declare(strict_types=1);

namespace Src\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class InspectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $perPage = max(1, (int) $request->input('per_page', 15));

        $query = QcInspection::with(['qcPlan:id,project_id,title', 'inspector:id,name,email'])
            ->where('tenant_id', $user->tenant_id);

        if ($projectId = $request->input('project_id')) {
            $query->whereHas('qcPlan', fn ($builder) => $builder->where('project_id', $projectId));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('inspection_date_from')) {
            $query->whereDate('inspection_date', '>=', $from);
        }

        if ($to = $request->input('inspection_date_to')) {
            $query->whereDate('inspection_date', '<=', $to);
        }

        $paginator = $query->orderByDesc('inspection_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $inspections = $paginator->getCollection()
            ->map(fn (QcInspection $inspection) => $this->inspectionPayload($inspection))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspections' => $inspections
            ],
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), $this->storeRules());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();
        $data['status'] = $data['status'] ?? 'scheduled';

        $plan = QcPlan::where('id', $data['qc_plan_id'])
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$plan) {
            return response()->json([
                'status' => 'error',
                'message' => 'QC plan not found for this tenant'
            ], 422);
        }

        $inspector = User::find($data['inspector_id']);

        if (!$inspector || $inspector->tenant_id !== $user->tenant_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspector must belong to the same tenant'
            ], 422);
        }

        $data['tenant_id'] = $user->tenant_id;
        $data['scheduled_at'] = $data['scheduled_at'] ?? now();

        $inspection = QcInspection::create($data);

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->updateRules());

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        if (isset($data['qc_plan_id'])) {
            $plan = QcPlan::where('id', $data['qc_plan_id'])
                ->where('tenant_id', $user->tenant_id)
                ->first();

            if (!$plan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'QC plan not found for this tenant'
                ], 422);
            }
        }

        if (isset($data['inspector_id'])) {
            $inspector = User::find($data['inspector_id']);

            if (!$inspector || $inspector->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inspector must belong to the same tenant'
                ], 422);
            }
        }

        $inspection->update($data);

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $inspection->delete();

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Inspection deleted successfully'
            ]
        ]);
    }

    public function schedule(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'inspection_date' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'results' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        if (!empty($data['inspection_date'])) {
            $inspection->inspection_date = $data['inspection_date'];
        }

        if (isset($data['notes'])) {
            $inspection->description = $data['notes'];
        }

        if (isset($data['results'])) {
            $inspection->findings = $data['results'];
        }

        $inspection->status = 'scheduled';
        $inspection->scheduled_at = $data['scheduled_at'] ?? now();
        $inspection->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ]);
    }

    public function conduct(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'conducted_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'results' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        if (isset($data['notes'])) {
            $inspection->description = $data['notes'];
        }

        if (isset($data['results'])) {
            $inspection->findings = $data['results'];
        }

        $inspection->status = 'in_progress';
        $inspection->conducted_at = $data['conducted_at'] ?? now();
        $inspection->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $inspection = $this->resolveInspection($id, $user);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'completed_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'results' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data = $validator->validated();

        if (isset($data['notes'])) {
            $inspection->description = $data['notes'];
        }

        if (isset($data['results'])) {
            $inspection->findings = $data['results'];
        }

        $inspection->status = 'completed';
        $inspection->completed_at = $data['completed_at'] ?? now();
        $inspection->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'inspection' => $this->inspectionPayload($inspection)
            ]
        ]);
    }

    private function inspectionPayload(QcInspection $inspection): array
    {
        $inspection->loadMissing(['qcPlan:id,project_id,title', 'inspector:id,name,email']);

        return [
            'id' => $inspection->id,
            'tenant_id' => $inspection->tenant_id,
            'qc_plan_id' => $inspection->qc_plan_id,
            'project_id' => $inspection->qcPlan?->project_id,
            'qc_plan' => $inspection->qcPlan?->only(['id', 'title', 'project_id']),
            'title' => $inspection->title,
            'description' => $inspection->description,
            'status' => $inspection->status,
            'inspection_date' => optional($inspection->inspection_date)->toDateString(),
            'scheduled_at' => optional($inspection->scheduled_at)->toDateTimeString(),
            'conducted_at' => optional($inspection->conducted_at)->toDateTimeString(),
            'completed_at' => optional($inspection->completed_at)->toDateTimeString(),
            'inspector' => $inspection->inspector ? [
                'id' => $inspection->inspector->id,
                'name' => $inspection->inspector->name,
                'email' => $inspection->inspector->email,
            ] : null,
            'findings' => $inspection->findings,
            'recommendations' => $inspection->recommendations,
            'checklist_results' => $inspection->checklist_results,
            'photos' => $inspection->photos,
            'created_at' => $inspection->created_at?->toDateTimeString(),
            'updated_at' => $inspection->updated_at?->toDateTimeString(),
        ];
    }

    private function resolveInspection(string $id, User $user): ?QcInspection
    {
        return QcInspection::where('tenant_id', $user->tenant_id)
            ->find($id);
    }

    private function storeRules(): array
    {
        return [
            'qc_plan_id' => 'required|exists:qc_plans,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', Rule::in(['scheduled', 'in_progress', 'completed', 'failed'])],
            'inspection_date' => 'required|date',
            'scheduled_at' => 'nullable|date',
            'conducted_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'inspector_id' => 'required|exists:users,id',
            'findings' => 'nullable|string',
            'recommendations' => 'nullable|string',
            'checklist_results' => 'nullable|array',
            'photos' => 'nullable|array',
        ];
    }

    private function updateRules(): array
    {
        return [
            'qc_plan_id' => 'sometimes|exists:qc_plans,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', Rule::in(['scheduled', 'in_progress', 'completed', 'failed'])],
            'inspection_date' => 'sometimes|date',
            'scheduled_at' => 'sometimes|nullable|date',
            'conducted_at' => 'sometimes|nullable|date',
            'completed_at' => 'sometimes|nullable|date',
            'inspector_id' => 'sometimes|exists:users,id',
            'findings' => 'sometimes|nullable|string',
            'recommendations' => 'sometimes|nullable|string',
            'checklist_results' => 'sometimes|array',
            'photos' => 'sometimes|array',
        ];
    }

    private function validationErrorResponse($validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }
}
