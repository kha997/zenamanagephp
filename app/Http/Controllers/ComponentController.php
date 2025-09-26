<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Resources\ComponentResource;
use Src\Foundation\Utils\JSendResponse;
use Src\RBAC\Middleware\RBACMiddleware;

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
            $components = Component::where('project_id', $projectId)->with(['user', 'project'])->get();
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
            return JSendResponse::success(new ComponentResource($component), 'Component đã được tạo thành công');
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
                                ->select(['id', 'name', 'status'])->where('id', $componentId)
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
    public function update(UpdateComponentRequest $request, string $projectId, string $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                                ->select(['id', 'name', 'status'])->where('id', $componentId)
                                ->firstOrFail();
            
            $component->update($request->validated());
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
                                ->select(['id', 'name', 'status'])->where('id', $componentId)
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
                                 ->with(['user', 'project'])->get();
            return JSendResponse::success(ComponentResource::collection($components));
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy cây components: ' . $e->getMessage());
        }
    }
}