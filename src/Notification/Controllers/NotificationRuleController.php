<?php declare(strict_types=1);

namespace Src\Notification\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Notification\Models\NotificationRule;
use Src\Notification\Services\NotificationRuleService;
use Src\Notification\Requests\StoreNotificationRuleRequest;
use Src\Notification\Requests\UpdateNotificationRuleRequest;
use Src\Notification\Resources\NotificationRuleResource;
use Src\Notification\Resources\NotificationRuleCollection;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;
use Exception;

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
        $this->middleware(RBACMiddleware::class);
        $this->notificationRuleService = $notificationRuleService;
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
            $userId = auth()->id();
            $projectId = $request->get('project_id');
            $eventKey = $request->get('event_key');
            $isEnabled = $request->get('is_enabled');
            
            $rules = $this->notificationRuleService->getUserRules(
                $userId,
                $projectId,
                $eventKey,
                $isEnabled
            );

            return JSendResponse::success(
                new NotificationRuleCollection($rules)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách quy tắc thông báo: ' . $e->getMessage());
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
            $data = $request->validated();
            $data['user_id'] = auth()->id();
            
            $rule = $this->notificationRuleService->createRule($data);

            return JSendResponse::success(
                new NotificationRuleResource($rule),
                'Quy tắc thông báo đã được tạo thành công',
                201
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể tạo quy tắc thông báo: ' . $e->getMessage());
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
            $rule = $this->notificationRuleService->getRuleById($ulid);

            if (!$rule || $rule->user_id !== auth()->id()) {
                return JSendResponse::error('Quy tắc thông báo không tồn tại', 404);
            }

            return JSendResponse::success(
                new NotificationRuleResource($rule)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin quy tắc thông báo: ' . $e->getMessage());
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
            $rule = $this->notificationRuleService->updateRule(
                $ulid,
                $request->validated(),
                auth()->id()
            );

            return JSendResponse::success(
                new NotificationRuleResource($rule),
                'Quy tắc thông báo đã được cập nhật thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể cập nhật quy tắc thông báo: ' . $e->getMessage());
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
            $rule = NotificationRule::where('ulid', $ulid)
                ->where('user_id', auth()->id())
                ->first();

            if (!$rule) {
                return JSendResponse::error('Quy tắc thông báo không tồn tại', 404);
            }

            $rule->delete();

            return JSendResponse::success(
                null,
                'Quy tắc thông báo đã được xóa thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể xóa quy tắc thông báo: ' . $e->getMessage());
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
            $rule = $this->notificationRuleService->toggleRule($ulid, auth()->id());

            $status = $rule->is_enabled ? 'bật' : 'tắt';
            return JSendResponse::success(
                new NotificationRuleResource($rule),
                "Quy tắc thông báo đã được {$status}"
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể thay đổi trạng thái quy tắc thông báo: ' . $e->getMessage());
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
            $request->validate([
                'project_id' => 'nullable|integer|exists:projects,id'
            ]);
            
            $rules = $this->notificationRuleService->createDefaultRules(
                auth()->id(),
                $request->get('project_id')
            );

            return JSendResponse::success(
                NotificationRuleResource::collection($rules),
                'Quy tắc thông báo mặc định đã được tạo thành công'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể tạo quy tắc mặc định: ' . $e->getMessage());
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
            
            $result = $this->notificationRuleService->testRule(
                $ulid,
                auth()->id(),
                $request->get('event_data'),
                $request->get('priority')
            );

            return JSendResponse::success(
                $result,
                'Test quy tắc thông báo hoàn thành'
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể test quy tắc thông báo: ' . $e->getMessage());
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

            return JSendResponse::success([
                'events' => $events
            ]);
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách events: ' . $e->getMessage());
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
            $rules = $this->notificationRuleService->getRulesByProject(
                auth()->id(),
                $projectId
            );

            return JSendResponse::success(
                new NotificationRuleCollection($rules)
            );
        } catch (Exception $e) {
            return JSendResponse::error('Không thể lấy quy tắc thông báo theo project: ' . $e->getMessage());
        }
    }
}