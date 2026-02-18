<?php declare(strict_types=1);

namespace Src\InteractionLogs\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Src\InteractionLogs\Models\InteractionLog;
use Src\InteractionLogs\Requests\StoreInteractionLogRequest;
use Src\InteractionLogs\Requests\UpdateInteractionLogRequest;
use Src\InteractionLogs\Resources\InteractionLogResource;
use Src\InteractionLogs\Resources\InteractionLogCollection;
use Src\InteractionLogs\Services\InteractionLogService; // Sửa từ App\ thành Src\
use Src\Foundation\Utils\JSendResponse;
use Src\Foundation\Helpers\AuthHelper;

/**
 * Controller quản lý Interaction Logs
 * Xử lý các API endpoints cho việc quản lý tương tác với khách hàng
 */
class InteractionLogController extends Controller
{
    public function __construct(
        private InteractionLogService $interactionLogService
    ) {}

    /**
     * Lấy danh sách interaction logs theo tag path
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getByTagPath(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');
            $tagPath = $request->get('tag_path');
            $filters = $request->only(['type', 'visibility']);

            $logs = $this->interactionLogService->getLogsByTagPath(
                $projectId,
                $tagPath,
                $filters
            );

            return JSendResponse::success(
                new InteractionLogCollection($logs),
                'Danh sách logs theo tag path được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải logs theo tag path: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy danh sách interaction logs của một project
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');
            $filters = $request->only(['type', 'visibility', 'tag_path', 'linked_task_id', 'client_approved']);
            $perPage = (int) $request->get('per_page', 15);

            $logs = $this->interactionLogService->getProjectLogs(
                $projectId,
                $filters['type'] ?? null,
                $filters['visibility'] ?? InteractionLog::VISIBILITY_INTERNAL,
                $perPage
            );

            return JSendResponse::success(
                new InteractionLogCollection($logs),
                'Danh sách interaction logs được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải danh sách interaction logs: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Tạo interaction log mới
     * 
     * @param StoreInteractionLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreInteractionLogRequest $request): JsonResponse
    {
        try {
            $log = $this->interactionLogService->createLog($request->validated());

            return JSendResponse::success(
                new InteractionLogResource($log),
                'Interaction log được tạo thành công',
                201
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tạo interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Hiển thị chi tiết interaction log
     * 
     * @param InteractionLog $interactionLog
     * @return JsonResponse
     */
    public function show(InteractionLog $interactionLog): JsonResponse
    {
        try {
            $tenantId = \Src\Foundation\Helpers\AuthHelper::user()?->tenant_id;

            if ($tenantId && $interactionLog->tenant_id !== $tenantId) {
                return JSendResponse::error(
                    'Interaction log not found',
                    404
                );
            }

            return JSendResponse::success(
                new InteractionLogResource($interactionLog->load(['project', 'linkedTask', 'creator'])),
                'Chi tiết interaction log được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải chi tiết interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Cập nhật interaction log
     * 
     * @param UpdateInteractionLogRequest $request
     * @param InteractionLog $interactionLog
     * @return JsonResponse
     */
    public function update(UpdateInteractionLogRequest $request, InteractionLog $interactionLog): JsonResponse
    {
        try {
            $updatedLog = $this->interactionLogService->updateLogInstance(
                $interactionLog,
                $request->validated()
            );

            return JSendResponse::success(
                new InteractionLogResource($updatedLog),
                'Interaction log được cập nhật thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể cập nhật interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Xóa interaction log
     * 
     * @param InteractionLog $interactionLog
     * @return JsonResponse
     */
    public function destroy(InteractionLog $interactionLog): JsonResponse
    {
        try {
            $this->interactionLogService->deleteLogInstance($interactionLog);

            return JSendResponse::success(
                null,
                'Interaction log được xóa thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể xóa interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Phê duyệt interaction log để hiển thị cho client
     * 
     * @param InteractionLog $interactionLog
     * @return JsonResponse
     */
    public function approveForClient(InteractionLog $interactionLog): JsonResponse
    {
        try {
            $approvedLog = $this->interactionLogService->approveForClientInstance($interactionLog);

            return JSendResponse::success(
                new InteractionLogResource($approvedLog),
                'Interaction log đã được phê duyệt cho client'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể phê duyệt interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy danh sách interaction logs của một project cụ thể
     * 
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    /**
     * Lấy danh sách interaction logs của project
     */
    public function indexByProject(Request $request, string $projectId): JsonResponse
    {
        try {
            $type = $request->get('type');
            $visibility = $request->get('visibility', 'internal');
            $perPage = (int) $request->get('per_page', 15);

            // Sửa signature để phù hợp với Service
            $logs = $this->interactionLogService->getProjectLogs(
                (int) $projectId,
                $type,
                $visibility,
                $perPage
            );

            return JSendResponse::success(
                new InteractionLogCollection($logs),
                'Danh sách interaction logs của project được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải danh sách interaction logs: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Cập nhật interaction log của project cụ thể
     */
    public function updateForProject(UpdateInteractionLogRequest $request, string $projectId, InteractionLog $interactionLog): JsonResponse
    {
        try {
            // Kiểm tra log có thuộc project không
            if ($interactionLog->project_id !== $projectId) {
                return JSendResponse::error(
                    'Interaction log không thuộc project này',
                    404
                );
            }

            // Sửa từ updateLogInstance thành updateLog
            $updatedLog = $this->interactionLogService->updateLog(
                $interactionLog,
                $request->validated()
            );

            return JSendResponse::success(
                new InteractionLogResource($updatedLog),
                'Interaction log được cập nhật thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể cập nhật interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Xóa interaction log của project cụ thể
     */
    public function destroyForProject(string $projectId, InteractionLog $interactionLog): JsonResponse
    {
        try {
            // Kiểm tra log có thuộc project không
            if ($interactionLog->project_id !== $projectId) {
                return JSendResponse::error(
                    'Interaction log không thuộc project này',
                    404
                );
            }

            // Sửa từ deleteLogInstance thành deleteLog
            $this->interactionLogService->deleteLog($interactionLog);

            return JSendResponse::success(
                null,
                'Interaction log được xóa thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể xóa interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Phê duyệt interaction log cho client trong project cụ thể
     */
    public function approveForClientInProject(string $projectId, InteractionLog $interactionLog): JsonResponse
    {
        try {
            // Kiểm tra log có thuộc project không
            if ($interactionLog->project_id !== $projectId) {
                return JSendResponse::error(
                    'Interaction log không thuộc project này',
                    404
                );
            }

            // Sửa từ approveForClientInstance thành approveForClient
            $approvedLog = $this->interactionLogService->approveForClient($interactionLog);

            return JSendResponse::success(
                new InteractionLogResource($approvedLog),
                'Interaction log đã được phê duyệt cho client'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể phê duyệt interaction log: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy logs theo tag path trong project cụ thể
     */
    public function getByTagPathInProject(Request $request, string $projectId): JsonResponse
    {
        try {
            $tagPath = $request->get('tag_path');
            $perPage = (int) $request->get('per_page', 15);

            // Sửa signature để phù hợp với Service
            $logs = $this->interactionLogService->getLogsByTagPath(
                (int) $projectId,
                $tagPath,
                $perPage
            );

            return JSendResponse::success(
                new InteractionLogCollection($logs),
                'Danh sách logs theo tag path trong project được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải logs theo tag path: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Autocomplete cho tag_path trong project cụ thể
     */
    public function autocompleteTagPath(Request $request, string $projectId): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $limit = (int) $request->get('limit', 10);

            // Sửa signature để phù hợp với Service
            $suggestions = $this->interactionLogService->autocompleteTagPath(
                (int) $projectId,
                $query,
                $limit
            );

            return JSendResponse::success(
                $suggestions,
                'Gợi ý tag path được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải gợi ý tag path: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Thống kê interaction logs của project
     */
    public function getProjectStats(Request $request, string $projectId): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Sửa signature để phù hợp với Service
            $stats = $this->interactionLogService->getProjectStats(
                (int) $projectId,
                $dateFrom,
                $dateTo
            );

            return JSendResponse::success(
                $stats,
                'Thống kê interaction logs của project được tải thành công'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Không thể tải thống kê: ' . $e->getMessage(),
                500
            );
        }
    }
}
