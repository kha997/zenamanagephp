<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Resources\ComponentResource;
use Src\CoreProject\Requests\StoreComponentRequest;
use Src\CoreProject\Requests\UpdateComponentRequest;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Illuminate\Support\Facades\Event;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;

/**
 * Controller xử lý các hoạt động CRUD cho Component
 * 
 * @package Src\CoreProject\Controllers
 */
class ComponentController
{
    /**
     * Constructor - áp dụng RBAC middleware
     */
    public function __construct()
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
    }

    /**
     * Lấy danh sách components của một project
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        try {
            $components = Component::where('project_id', $projectId)->get();
            return JSendResponse::success(ComponentResource::collection($components));
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách components: ' . $e->getMessage());
        }
    }
    
    /**
     * Tạo component mới
     *
     * @param StoreComponentRequest $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function store(StoreComponentRequest $request, string $projectId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['project_id'] = $projectId;
            
            $component = Component::create($data);
            return JSendResponse::success(new ComponentResource($component), 'Component đã được tạo thành công', 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo component: ' . $e->getMessage());
        }
    }
    
    /**
     * Hiển thị chi tiết component
     *
     * @param string $projectId
     * @param string $componentId
     * @return JsonResponse
     */
    public function show(string $projectId, string $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                                ->where('id', $componentId)
                                ->firstOrFail();
            return JSendResponse::success(new ComponentResource($component));
        } catch (\Exception $e) {
            return JSendResponse::error('Không tìm thấy component: ' . $e->getMessage());
        }
    }
    
    /**
     * Cập nhật component
     *
     * @param UpdateComponentRequest $request
     * @param string $projectId
     * @param string $componentId
     * @return JsonResponse
     */
    public function update(UpdateComponentRequest $request, string $componentId, ?string $projectId = null): JsonResponse
    {
        try {
            $query = Component::where('id', $componentId);

            if ($projectId) {
                $query->where('project_id', $projectId);
            }

            $component = $query->firstOrFail();
            
            $data = $request->validated();
            $progressPercent = null;

            if (array_key_exists('progress_percent', $data)) {
                $progressPercent = $data['progress_percent'];
                unset($data['progress_percent']);
            }

            $component->update($data);

            if ($progressPercent !== null) {
                $oldProgress = $component->progress_percent;
                $oldActualCost = $component->actual_cost;
                $component->updateProgress($progressPercent);
                $newActualCost = $component->actual_cost;

                Event::dispatch(new ComponentProgressUpdated(
                    $component->id,
                    $component->project_id,
                    optional(auth()->user())->id ?? 0,
                    optional(auth()->user())->tenant_id ?? 0,
                    $oldProgress,
                    $progressPercent,
                    $oldActualCost,
                    $newActualCost,
                    [
                        'progress_percent' => ['old' => $oldProgress, 'new' => $progressPercent],
                        'actual_cost' => ['old' => $oldActualCost, 'new' => $newActualCost]
                    ],
                    new \DateTime()
                ));
            }
            return JSendResponse::success(new ComponentResource($component), 'Component đã được cập nhật thành công');
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật component: ' . $e->getMessage());
        }
    }
    
    /**
     * Xóa component
     *
     * @param string $projectId
     * @param string $componentId
     * @return JsonResponse
     */
    public function destroy(string $projectId, string $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                                ->where('id', $componentId)
                                ->firstOrFail();
            
            $component->delete();
            return JSendResponse::success(null, 'Component đã được xóa thành công');
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa component: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy cây phân cấp components
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function tree(string $projectId): JsonResponse
    {
        try {
            $components = Component::where('project_id', $projectId)
                                 ->whereNull('parent_component_id')
                                 ->with('children')
                                 ->get();
            return JSendResponse::success(ComponentResource::collection($components));
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy cây components: ' . $e->getMessage());
        }
    }
}
