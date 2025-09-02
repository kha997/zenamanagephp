<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Baseline;
use Src\CoreProject\Resources\BaselineResource;
use Src\CoreProject\Services\BaselineService;
use Src\Foundation\Utils\JSendResponse;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\RBAC\Traits\HasRBACContext;
use Illuminate\Support\Facades\Validator;

/**
 * Controller xử lý các hoạt động CRUD cho Baseline
 * 
 * @package Src\CoreProject\Controllers
 */

class BaselineController
{
    use HasRBACContext;
    
    protected BaselineService $baselineService;
    
    public function __construct(BaselineService $baselineService)
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
        $this->baselineService = $baselineService;
    }
    
    /**
     * Lấy danh sách baselines theo project
     * GET /api/v1/projects/{projectId}/baselines
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $projectId)) {
                return JSendResponse::error('Không có quyền xem baselines', 403);
            }

            $query = Baseline::where('project_id', $projectId)
                           ->with(['creator']);

            // Filter theo type
            if ($request->has('type')) {
                $query->where('type', $request->get('type'));
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'version');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $baselines = $query->get();

            return JSendResponse::success([
                'baselines' => BaselineResource::collection($baselines)
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách baselines: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo baseline mới
     * POST /api/v1/projects/{projectId}/baselines
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.create', $projectId)) {
                return JSendResponse::error('Không có quyền tạo baseline', 403);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:contract,execution',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'cost' => 'required|numeric|min:0',
                'note' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            // Tính version tiếp theo
            $latestVersion = Baseline::where('project_id', $projectId)
                                   ->where('type', $request->input('type'))
                                   ->max('version') ?? 0;

            $baseline = Baseline::create([
                'project_id' => $projectId,
                'type' => $request->input('type'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'cost' => $request->input('cost'),
                'version' => $latestVersion + 1,
                'note' => $request->input('note'),
                'created_by' => $this->getCurrentUserId($request)
            ]);

            return JSendResponse::success([
                'baseline' => new BaselineResource($baseline->load('creator')),
                'message' => 'Baseline đã được tạo thành công'
            ], 201);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo baseline: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết baseline
     * GET /api/v1/baselines/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $baseline = Baseline::with(['creator', 'project'])->findOrFail($id);

            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $baseline->project_id)) {
                return JSendResponse::error('Không có quyền xem baseline này', 403);
            }

            return JSendResponse::success([
                'baseline' => new BaselineResource($baseline)
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin baseline: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật baseline
     * PUT/PATCH /api/v1/baselines/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $baseline = Baseline::findOrFail($id);

            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.update', $baseline->project_id)) {
                return JSendResponse::error('Không có quyền cập nhật baseline', 403);
            }

            $validator = Validator::make($request->all(), [
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after:start_date',
                'cost' => 'sometimes|required|numeric|min:0',
                'note' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            $updateData = $request->only(['start_date', 'end_date', 'cost', 'note']);
            $baseline->update($updateData);

            return JSendResponse::success([
                'baseline' => new BaselineResource($baseline->fresh(['creator', 'project'])),
                'message' => 'Baseline đã được cập nhật thành công'
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật baseline: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa baseline
     * DELETE /api/v1/baselines/{id}
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $baseline = Baseline::findOrFail($id);

            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.delete', $baseline->project_id)) {
                return JSendResponse::error('Không có quyền xóa baseline', 403);
            }

            $baseline->delete();

            return JSendResponse::success([
                'message' => 'Baseline đã được xóa thành công'
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa baseline: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * So sánh hai baseline
     * GET /api/v1/baselines/{id1}/compare/{id2}
     */
    public function compare(Request $request, string $id1, string $id2): JsonResponse
    {
        try {
            $baseline1 = Baseline::findOrFail($id1);
            $baseline2 = Baseline::findOrFail($id2);
            
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $baseline1->project_id) ||
                !$this->hasPermission($request, 'baseline.view', $baseline2->project_id)) {
                return JSendResponse::error('Không có quyền so sánh baselines', 403);
            }
            
            $comparison = $this->baselineService->compareBaselines($baseline1, $baseline2);
            
            return JSendResponse::success([
                'comparison' => $comparison
            ]);
            
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể so sánh baselines: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Tạo baseline từ dữ liệu project hiện tại
     * POST /api/v1/projects/{projectId}/baselines/from-current
     */
    public function createFromCurrent(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.create', $projectId)) {
                return JSendResponse::error('Không có quyền tạo baseline', 403);
            }
            
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:contract,execution',
                'note' => 'nullable|string|max:1000'
            ]);
            
            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }
            
            $baseline = $this->baselineService->createBaselineFromProject(
                $projectId,
                $request->input('type'),
                $this->getCurrentUserId($request),
                $request->input('note')
            );
            
            return JSendResponse::success([
                'baseline' => new BaselineResource($baseline->load('creator')),
                'message' => 'Baseline đã được tạo từ dữ liệu project hiện tại'
            ], 201);
            
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo baseline: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy báo cáo baseline cho project
     * GET /api/v1/projects/{projectId}/baselines/report
     */
    public function report(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $projectId)) {
                return JSendResponse::error('Không có quyền xem báo cáo baseline', 403);
            }
            
            $report = $this->baselineService->getProjectBaselineReport($projectId);
            
            return JSendResponse::success([
                'report' => $report
            ]);
            
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo báo cáo baseline: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Re-baseline project từ baseline hiện tại
     * POST /api/v1/baselines/{id}/rebaseline
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function rebaseline(Request $request, string $id): JsonResponse
    {
        try {
            $baseline = Baseline::findOrFail($id);

            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.create', $baseline->project_id)) {
                return JSendResponse::error('Không có quyền tạo re-baseline', 403);
            }

            $validator = Validator::make($request->all(), [
                'note' => 'nullable|string|max:1000',
                'linked_contract_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return JSendResponse::fail($validator->errors(), 422);
            }

            $newBaseline = $this->baselineService->rebaseline(
                $baseline,
                $this->getCurrentUserId($request),
                $request->input('note'),
                $request->input('linked_contract_id')
            );

            return JSendResponse::success([
                'baseline' => new BaselineResource($newBaseline->load('creator')),
                'message' => 'Re-baseline đã được tạo thành công'
            ], 201);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo re-baseline: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy variance analysis cho project
     * GET /api/v1/projects/{projectId}/variance
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function getVariance(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $projectId)) {
                return JSendResponse::error('Không có quyền xem variance analysis', 403);
            }

            $baselineType = $request->get('baseline_type', 'execution');
            $variance = $this->baselineService->calculateProjectVariance($projectId, $baselineType);

            return JSendResponse::success([
                'variance' => $variance
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán variance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy lịch sử baseline changes
     * GET /api/v1/baselines/{id}/history
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function getHistory(Request $request, string $id): JsonResponse
    {
        try {
            $baseline = Baseline::findOrFail($id);

            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $baseline->project_id)) {
                return JSendResponse::error('Không có quyền xem lịch sử baseline', 403);
            }

            $history = $baseline->history()->with('creator')->orderBy('created_at', 'desc')->get();

            return JSendResponse::success([
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy lịch sử baseline: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy current baseline cho project
     * GET /api/v1/projects/{projectId}/baselines/current
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function getCurrent(Request $request, string $projectId): JsonResponse
    {
        try {
            // Kiểm tra quyền
            if (!$this->hasPermission($request, 'baseline.view', $projectId)) {
                return JSendResponse::error('Không có quyền xem baseline hiện tại', 403);
            }

            $type = $request->get('type', 'execution');
            $currentBaseline = Baseline::getCurrentBaseline($projectId, $type);

            if (!$currentBaseline) {
                return JSendResponse::error('Không tìm thấy baseline hiện tại cho project', 404);
            }

            return JSendResponse::success([
                'baseline' => new BaselineResource($currentBaseline->load('creator'))
            ]);

        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy baseline hiện tại: ' . $e->getMessage(), 500);
        }
    }

}