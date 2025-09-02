<?php declare(strict_types=1);

namespace Src\Compensation\Controllers;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Src\Compensation\Models\Contract;
use Src\Compensation\Models\TaskCompensation;
use Src\Compensation\Services\CompensationService;
use Src\Compensation\Requests\SyncTaskAssignmentsRequest;
use Src\Compensation\Requests\ApplyContractRequest;
use Src\Compensation\Resources\CompensationPreviewResource;
use Src\Compensation\Resources\TaskAssignmentResource;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\TaskAssignment;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;
use Exception;

/**
 * Controller xử lý các hoạt động compensation và KPI
 * 
 * @package Src\Compensation\Controllers
 */
class CompensationController extends Controller
{
    /**
     * @var CompensationService
     */
    private CompensationService $compensationService;

    /**
     * Constructor - áp dụng RBAC middleware và inject service
     */
    public function __construct(CompensationService $compensationService)
    {
        $this->middleware(RBACMiddleware::class);
        $this->compensationService = $compensationService;
    }

    /**
     * Đồng bộ task assignments cho một task
     * POST /api/v1/projects/{project_id}/tasks/{task_id}/assignments:sync
     *
     * @param SyncTaskAssignmentsRequest $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function syncTaskAssignments(
        SyncTaskAssignmentsRequest $request, 
        string $projectId, 
        string $taskId
    ): JsonResponse {
        try {
            // Kiểm tra task tồn tại và thuộc project
            $task = Task::where('id', $taskId)
                ->where('project_id', $projectId)
                ->first();

            if (!$task) {
                return JSendResponse::error('Task không tồn tại trong project này', 404);
            }

            // Đồng bộ assignments
            $assignments = $this->compensationService->syncTaskAssignments(
                $taskId,
                $request->validated()['assignments'],
                $this->resolveActorId()
            );

            return JSendResponse::success(
                TaskAssignmentResource::collection($assignments),
                'Task assignments đã được đồng bộ thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể đồng bộ task assignments: ' . $e->getMessage());
        }
    }

    /**
     * Preview compensation cho project với contract hiện tại
     * GET /api/v1/projects/{project_id}/compensation/preview?contract_id=
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function previewCompensation(Request $request, string $projectId): JsonResponse
    {
        try {
            $contractId = $request->get('contract_id');
            
            if (!$contractId) {
                return JSendResponse::error('Contract ID là bắt buộc', 400);
            }

            // Kiểm tra contract tồn tại và thuộc project
            $contract = Contract::where('id', $contractId)
                ->where('project_id', $projectId)
                ->first();

            if (!$contract) {
                return JSendResponse::error('Contract không tồn tại trong project này', 404);
            }

            // Tạo preview compensation
            $preview = $this->compensationService->previewCompensation(
                $projectId,
                $contractId,
                $this->resolveActorId()
            );

            return JSendResponse::success(
                new CompensationPreviewResource($preview),
                'Preview compensation đã được tạo thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể tạo preview compensation: ' . $e->getMessage());
        }
    }

    /**
     * Áp dụng contract cho compensation (lock values)
     * POST /api/v1/projects/{project_id}/contracts/{contract_id}/apply
     *
     * @param ApplyContractRequest $request
     * @param string $projectId
     * @param string $contractId
     * @return JsonResponse
     */
    public function applyContract(
        ApplyContractRequest $request, 
        string $projectId, 
        string $contractId
    ): JsonResponse {
        try {
            // Kiểm tra contract tồn tại và thuộc project
            $contract = Contract::where('id', $contractId)
                ->where('project_id', $projectId)
                ->first();

            if (!$contract) {
                return JSendResponse::error('Contract không tồn tại trong project này', 404);
            }

            // Kiểm tra contract có thể apply compensation không
            if (!$contract->canApplyCompensation()) {
                return JSendResponse::error('Contract không thể áp dụng compensation ở trạng thái hiện tại', 403);
            }

            // Áp dụng contract
            $result = $this->compensationService->applyContract(
                $contractId,
                $request->validated(),
                $this->resolveActorId()
            );

            return JSendResponse::success(
                $result,
                'Contract đã được áp dụng thành công cho compensation'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể áp dụng contract: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách compensation theo project
     * GET /api/v1/projects/{project_id}/compensation
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        try {
            $compensations = $this->compensationService->getCompensationsByProject(
                $projectId,
                $request->get('status'),
                $request->get('contract_id'),
                (int) $request->get('per_page', 15)
            );

            return JSendResponse::success($compensations);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách compensation: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết compensation của một task
     * GET /api/v1/projects/{project_id}/tasks/{task_id}/compensation
     *
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function showTaskCompensation(string $projectId, string $taskId): JsonResponse
    {
        try {
            // Kiểm tra task tồn tại và thuộc project
            $task = Task::where('id', $taskId)
                ->where('project_id', $projectId)
                ->with(['compensation', 'assignments.user'])
                ->first();

            if (!$task) {
                return JSendResponse::error('Task không tồn tại trong project này', 404);
            }

            $compensationData = $this->compensationService->getTaskCompensationDetails(
                $taskId
            );

            return JSendResponse::success($compensationData);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin compensation: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật compensation cho task
     * PUT /api/v1/projects/{project_id}/tasks/{task_id}/compensation
     *
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function updateTaskCompensation(
        Request $request, 
        string $projectId, 
        string $taskId
    ): JsonResponse {
        try {
            $request->validate([
                'base_contract_value_percent' => 'required|numeric|min:0|max:100',
                'effective_contract_value_percent' => 'nullable|numeric|min:0|max:100',
                'notes' => 'nullable|string|max:1000'
            ]);

            // Kiểm tra task tồn tại và thuộc project
            $task = Task::where('id', $taskId)
                ->where('project_id', $projectId)
                ->first();

            if (!$task) {
                return JSendResponse::error('Task không tồn tại trong project này', 404);
            }

            $compensation = $this->compensationService->updateTaskCompensation(
                $taskId,
                $request->validated(),
                $this->resolveActorId()
            );

            return JSendResponse::success(
                $compensation,
                'Compensation đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể cập nhật compensation: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thống kê compensation theo project
     * GET /api/v1/projects/{project_id}/compensation/stats
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function stats(string $projectId): JsonResponse
    {
        try {
            $stats = $this->compensationService->getCompensationStats($projectId);

            return JSendResponse::success($stats);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thống kê compensation: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the current actor ID for audit trails
     * Uses Auth facade instead of auth() helper for better testability
     *
     * @return string|int The actor ID or 'system' as fallback
     */
    private function resolveActorId()
    {
        try {
            // Check if user is authenticated using Auth facade
            if (AuthHelper::check()) {
                return AuthHelper::idOrSystem();
            }
        } catch (\Throwable $e) {
            // Log the error for debugging in non-production environments
            if (config('app.debug')) {
                \Log::warning('Controller: Unable to resolve actor ID', [
                    'error' => $e->getMessage(),
                    'controller' => static::class
                ]);
            }
        }
        
        // Fallback to 'system' for test environments or when auth is unavailable
        return 'system';
    }
}