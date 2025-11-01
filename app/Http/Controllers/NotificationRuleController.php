<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Foundation\Helpers\AuthHelper;
use App\Support\ApiResponse;
use Src\Notification\Models\NotificationRule;
use Src\Notification\Resources\NotificationRuleCollection;
use Src\Notification\Resources\NotificationRuleResource;
use Src\RBAC\Middleware\RBACMiddleware;

/**
 * Controller xử lý các hoạt động CRUD cho Notification Rules
 * 
 * @package Src\Notification\Controllers
 */
class NotificationRuleController
{
    /**
     * @var NotificationRuleService
     */
    private NotificationRuleService $notificationRuleService;

    /**
     * Constructor - áp dụng RBAC middleware và inject service
     */
    public function __construct(NotificationRuleService $notificationRuleService)
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
        $this->notificationRuleService = $notificationRuleService;
    }

    /**
     * Lấy ID người dùng hiện tại một cách an toàn
     *
     * @return int|null
     */
    private function getUserId(): ?int
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Lấy danh sách notification rules của user hiện tại
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $projectId = $request->get('project_id');
            $eventKey = $request->get('event_key');
            $isEnabled = $request->get('is_enabled');
            
            $rules = $this->notificationRuleService->getUserRules(
                $userId,
                $projectId,
                $eventKey,
                $isEnabled
            );

            return ApiResponse::success(
                new NotificationRuleCollection($rules)
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy danh sách quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Tạo notification rule mới
     *
     * @param StoreNotificationRuleRequest $request
     * @return JsonResponse
     */
    public function store(StoreNotificationRuleRequest $request): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $data = $request->validated();
            $data['user_id'] = $userId;
            
            $rule = $this->notificationRuleService->createRule($data);

            return ApiResponse::success(
                new NotificationRuleResource($rule),
                'Quy tắc thông báo đã được tạo thành công',
                201
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể tạo quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết notification rule
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function show(string $ulid): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $rule = $this->notificationRuleService->getRuleById($ulid);

            if (!$rule || $rule->user_id !== $userId) {
                return ApiResponse::error('Quy tắc thông báo không tồn tại', 404);
            }

            return ApiResponse::success(
                new NotificationRuleResource($rule)
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy thông tin quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật notification rule
     *
     * @param UpdateNotificationRuleRequest $request
     * @param string $ulid
     * @return JsonResponse
     */
    public function update(UpdateNotificationRuleRequest $request, string $ulid): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $rule = $this->notificationRuleService->updateRule(
                $ulid,
                $request->validated(),
                $userId
            );

            return ApiResponse::success(
                new NotificationRuleResource($rule),
                'Quy tắc thông báo đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể cập nhật quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Xóa notification rule
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function destroy(string $ulid): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $rule = NotificationRule::where('ulid', $ulid)
                ->where('user_id', $userId)
                ->first();

            if (!$rule) {
                return ApiResponse::error('Quy tắc thông báo không tồn tại', 404);
            }

            $rule->delete();

            return ApiResponse::success(
                null,
                'Quy tắc thông báo đã được xóa thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể xóa quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Bật/tắt notification rule
     *
     * @param string $ulid
     * @return JsonResponse
     */
    public function toggle(string $ulid): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $rule = $this->notificationRuleService->toggleRule($ulid, $userId);

            $status = $rule->is_enabled ? 'bật' : 'tắt';
            return ApiResponse::success(
                new NotificationRuleResource($rule),
                "Quy tắc thông báo đã được {$status}"
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể thay đổi trạng thái quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Tạo quy tắc mặc định cho user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createDefaults(Request $request): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $request->validate([
                'project_id' => 'nullable|integer|exists:projects,id'
            ]);
            
            $rules = $this->notificationRuleService->createDefaultRules(
                $userId,
                $request->get('project_id')
            );

            return ApiResponse::success(
                NotificationRuleResource::collection($rules),
                'Quy tắc thông báo mặc định đã được tạo thành công'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể tạo quy tắc mặc định: ' . $e->getMessage());
        }
    }

    /**
     * Test notification rule với event giả lập
     *
     * @param Request $request
     * @param string $ulid
     * @return JsonResponse
     */
    public function test(Request $request, string $ulid): JsonResponse
    {
        try {
            $request->validate([
                'event_data' => 'required|array',
                'priority' => 'required|in:critical,normal,low'
            ]);
            
            $currentUserId = $this->getUserId();
            if (!$currentUserId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $result = $this->notificationRuleService->testRule(
                $ulid,
                $currentUserId,
                $request->get('event_data'),
                $request->get('priority')
            );

            return ApiResponse::success(
                $result,
                'Test quy tắc thông báo hoàn thành'
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể test quy tắc thông báo: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách các event có thể sử dụng cho notification rules
     *
     * @return JsonResponse
     */
    public function getAvailableEvents(): JsonResponse
    {
        try {
            $events = $this->notificationRuleService->getAvailableEvents();

            return ApiResponse::success([
                'events' => $events
            ]);
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy danh sách events: ' . $e->getMessage());
        }
    }

    /**
     * Lấy notification rules theo project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function getByProject(int $projectId): JsonResponse
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ApiResponse::error('Người dùng chưa được xác thực', 401);
            }
            
            $rules = $this->notificationRuleService->getRulesByProject(
                $userId,
                $projectId
            );

            return ApiResponse::success(
                new NotificationRuleCollection($rules)
            );
        } catch (Exception $e) {
            return ApiResponse::error('Không thể lấy quy tắc thông báo theo project: ' . $e->getMessage());
        }
    }
}