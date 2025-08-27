<?php declare(strict_types=1);

namespace Src\ChangeRequest\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\ChangeRequest\Services\ChangeRequestService;
use Src\ChangeRequest\Requests\StoreChangeRequestRequest;
use Src\ChangeRequest\Requests\UpdateChangeRequestRequest;
use Src\ChangeRequest\Requests\SubmitChangeRequestRequest;
use Src\ChangeRequest\Requests\DecideChangeRequestRequest;
use Src\ChangeRequest\Resources\ChangeRequestResource;
use Src\ChangeRequest\Resources\ChangeRequestCollection;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;
use Exception;

/**
 * Controller xử lý các hoạt động CRUD và workflow cho Change Request
 * 
 * @package Src\ChangeRequest\Controllers
 */
class ChangeRequestController extends Controller
{
    /**
     * @var ChangeRequestService
     */
    private ChangeRequestService $changeRequestService;

    /**
     * Constructor - áp dụng RBAC middleware và inject service
     */
    public function __construct(ChangeRequestService $changeRequestService)
    {
        $this->middleware(RBACMiddleware::class);
        $this->changeRequestService = $changeRequestService;
    }

    /**
     * Lấy danh sách change requests theo project
     * GET /api/v1/projects/{project_id}/cr?status=
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        try {
            $changeRequests = $this->changeRequestService->getChangeRequestsByProject(
                $projectId,
                $request->get('status'),
                $request->get('priority'),
                (int) $request->get('per_page', 15)
            );

            return JSendResponse::success(
                new ChangeRequestCollection($changeRequests)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách change requests: ' . $e->getMessage());
        }
    }

    /**
     * Tạo change request mới
     * POST /api/v1/projects/{project_id}/cr
     *
     * @param StoreChangeRequestRequest $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function store(StoreChangeRequestRequest $request, string $projectId): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->createChangeRequest(
                $projectId,
                $request->validated(),
                auth()->id()
            );

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest),
                'Change request đã được tạo thành công',
                201
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể tạo change request: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết change request
     * GET /api/v1/projects/{project_id}/cr/{id}
     *
     * @param string $projectId
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $projectId, string $id): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->getChangeRequestById($id);

            if (!$changeRequest || $changeRequest->project_id !== $projectId) {
                return JSendResponse::error('Change request không tồn tại', 404);
            }

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin change request: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật change request (chỉ khi ở trạng thái draft)
     * PUT /api/v1/projects/{project_id}/cr/{id}
     *
     * @param UpdateChangeRequestRequest $request
     * @param string $projectId
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateChangeRequestRequest $request, string $projectId, string $id): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->updateChangeRequest(
                $id,
                $request->validated(),
                auth()->id()
            );

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest),
                'Change request đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể cập nhật change request: ' . $e->getMessage());
        }
    }

    /**
     * Xóa change request (chỉ khi ở trạng thái draft)
     * DELETE /api/v1/projects/{project_id}/cr/{id}
     *
     * @param string $projectId
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $projectId, string $id): JsonResponse
    {
        try {
            $changeRequest = ChangeRequest::where('id', $id)
                ->where('project_id', $projectId)
                ->first();

            if (!$changeRequest) {
                return JSendResponse::error('Change request không tồn tại', 404);
            }

            // Chỉ cho phép xóa khi ở trạng thái draft
            if (!$changeRequest->isDraft()) {
                return JSendResponse::error('Không thể xóa change request đã được submit', 403);
            }

            $changeRequest->delete();

            return JSendResponse::success(
                null,
                'Change request đã được xóa thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể xóa change request: ' . $e->getMessage());
        }
    }

    /**
     * Submit change request để chờ phê duyệt
     * POST /api/v1/cr/{id}/submit
     *
     * @param SubmitChangeRequestRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function submit(SubmitChangeRequestRequest $request, string $id): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->submitForApproval(
                $id,
                auth()->id()
            );

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest),
                'Change request đã được submit để chờ phê duyệt'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể submit change request: ' . $e->getMessage());
        }
    }

    /**
     * Phê duyệt change request
     * POST /api/v1/cr/{id}/approve
     *
     * @param DecideChangeRequestRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function approve(DecideChangeRequestRequest $request, string $id): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->approveChangeRequest(
                $id,
                auth()->id(),
                $request->get('decision_note')
            );

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest),
                'Change request đã được phê duyệt'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể phê duyệt change request: ' . $e->getMessage());
        }
    }

    /**
     * Từ chối change request
     * POST /api/v1/cr/{id}/reject
     *
     * @param DecideChangeRequestRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function reject(DecideChangeRequestRequest $request, string $id): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->rejectChangeRequest(
                $id,
                auth()->id(),
                $request->get('decision_note')
            );

            return JSendResponse::success(
                new ChangeRequestResource($changeRequest),
                'Change request đã bị từ chối'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể từ chối change request: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thống kê change requests theo project
     * GET /api/v1/projects/{project_id}/cr/stats
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function stats(string $projectId): JsonResponse
    {
        try {
            $stats = $this->changeRequestService->getChangeRequestStats($projectId);

            return JSendResponse::success($stats);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thống kê change requests: ' . $e->getMessage());
        }
    }

    /**
     * Quản lý liên kết CR với entities khác
     * POST /api/v1/cr/{id}/links
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function manageLinks(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'linked_type' => 'required|in:task,document,component',
                'linked_id' => 'required|string',
                'action' => 'required|in:add,remove',
                'description' => 'nullable|string|max:500'
            ]);

            $result = $this->changeRequestService->manageEntityLink(
                $id,
                $request->get('linked_type'),
                $request->get('linked_id'),
                $request->get('action'),
                $request->get('description')
            );

            $message = $request->get('action') === 'add' 
                ? 'Liên kết đã được thêm thành công'
                : 'Liên kết đã được xóa thành công';

            return JSendResponse::success($result, $message);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể quản lý liên kết: ' . $e->getMessage());
        }
    }
}