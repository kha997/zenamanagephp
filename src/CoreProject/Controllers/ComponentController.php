<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Resources\ComponentResource;
use Src\CoreProject\Requests\StoreComponentRequest;
use Src\CoreProject\Requests\UpdateComponentRequest;
use Src\CoreProject\Services\ComponentService;
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
        } catch (\Throwable $e) {
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
            $request->merge(['project_id' => $projectId]);
            $data = $request->validated();
            
            $componentService = new ComponentService();
            $component = $componentService->createComponent($projectId, $data);
            return JSendResponse::success(new ComponentResource($component), 201);
        } catch (\Throwable $e) {
            return JSendResponse::error('Không thể tạo component: ' . $e->getMessage());
        }
    }
    
    /**
     * Hiển thị chi tiết component
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $component = Component::where('id', $id)->firstOrFail();
            return JSendResponse::success(new ComponentResource($component));
        } catch (\Throwable $e) {
            return JSendResponse::error('Không tìm thấy component: ' . $e->getMessage());
        }
    }
    
    /**
     * Cập nhật component
     *
     * @param UpdateComponentRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateComponentRequest $request, string $id): JsonResponse
    {
        try {
            $componentService = new ComponentService();
            $component = $componentService->updateComponent($id, $request->validated());
            return JSendResponse::success(new ComponentResource($component));
        } catch (\Throwable $e) {
            return JSendResponse::error('Không thể cập nhật component: ' . $e->getMessage());
        }
    }
    
    /**
     * Xóa component
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $componentService = new ComponentService();
            $componentService->deleteComponent($id);
            return JSendResponse::success();
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            return JSendResponse::error('Không thể lấy cây components: ' . $e->getMessage());
        }
    }
}
