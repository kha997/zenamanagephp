<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChangeRequestFormRequest;
use App\Http\Resources\ChangeRequestResource;
use App\Services\ChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTful Controller cho ChangeRequest management
 * 
 * @package App\Http\Controllers
 */
class ChangeRequestController extends Controller
{
    /**
     * @param ChangeRequestService $changeRequestService
     */
    public function __construct(
        private readonly ChangeRequestService $changeRequestService
    ) {}

    /**
     * Display change requests of a project.
     * GET /api/v1/projects/{project}/change-requests
     */
    public function index(int $projectId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search']);
            $filters['project_id'] = $projectId;
            $perPage = (int) $request->get('per_page', 15);
            
            $changeRequests = $this->changeRequestService->getChangeRequests($filters, $perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'change_requests' => ChangeRequestResource::collection($changeRequests->items()),
                    'pagination' => [
                        'current_page' => $changeRequests->currentPage(),
                        'last_page' => $changeRequests->lastPage(),
                        'per_page' => $changeRequests->perPage(),
                        'total' => $changeRequests->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách CR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created change request.
     * POST /api/v1/projects/{project}/change-requests
     */
    public function store(ChangeRequestFormRequest $request, int $projectId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['project_id'] = $projectId;
            
            $changeRequest = $this->changeRequestService->createChangeRequest($data);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'change_request' => new ChangeRequestResource($changeRequest)
                ],
                'message' => 'Change Request đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo CR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified change request.
     * GET /api/v1/change-requests/{changeRequest}
     */
    public function show(int $changeRequestId): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->getChangeRequestById($changeRequestId);
            
            if (!$changeRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Change Request không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'change_request' => new ChangeRequestResource($changeRequest)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy thông tin CR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified change request.
     * PUT/PATCH /api/v1/change-requests/{changeRequest}
     */
    public function update(ChangeRequestFormRequest $request, int $changeRequestId): JsonResponse
    {
        try {
            $changeRequest = $this->changeRequestService->updateChangeRequest($changeRequestId, $request->validated());
            
            if (!$changeRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Change Request không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'change_request' => new ChangeRequestResource($changeRequest)
                ],
                'message' => 'Change Request đã được cập nhật thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật CR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve or reject change request.
     * PATCH /api/v1/change-requests/{changeRequest}/decision
     */
    public function makeDecision(Request $request, int $changeRequestId): JsonResponse
    {
        $request->validate([
            'decision' => 'required|in:approved,rejected',
            'decision_note' => 'nullable|string|max:2000'
        ]);
        
        try {
            $changeRequest = $this->changeRequestService->makeDecision(
                $changeRequestId,
                $request->decision,
                $request->decision_note
            );
            
            if (!$changeRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Change Request không tồn tại.'
                ], 404);
            }
            
            $message = $request->decision === 'approved' 
                ? 'Change Request đã được phê duyệt.' 
                : 'Change Request đã bị từ chối.';
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'change_request' => new ChangeRequestResource($changeRequest)
                ],
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xử lý quyết định: ' . $e->getMessage()
            ], 500);
        }
    }
}