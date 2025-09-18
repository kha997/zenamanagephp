<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ComponentFormRequest;
use App\Http\Resources\ComponentResource;
use App\Services\ComponentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTful Controller cho Component management
 * 
 * @package App\Http\Controllers
 */
class ComponentController extends Controller
{
    /**
     * @param ComponentService $componentService
     */
    public function __construct(
        private readonly ComponentService $componentService
    ) {}

    /**
     * Display components of a project.
     * GET /api/v1/projects/{project}/components
     */
    public function index(int $projectId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['parent_id', 'search']);
            $filters['project_id'] = $projectId;
            
            $components = $this->componentService->getComponents($filters);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'components' => ComponentResource::collection($components)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách component: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created component.
     * POST /api/v1/projects/{project}/components
     */
    public function store(ComponentFormRequest $request, int $projectId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['project_id'] = $projectId;
            
            $component = $this->componentService->createComponent($data);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'component' => new ComponentResource($component)
                ],
                'message' => 'Component đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo component: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified component.
     * GET /api/v1/components/{component}
     */
    public function show(int $componentId, Request $request): JsonResponse
    {
        try {
            $includes = $request->get('include', []);
            if (is_string($includes)) {
                $includes = explode(',', $includes);
            }
            
            $component = $this->componentService->getComponentById($componentId, $includes);
            
            if (!$component) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Component không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'component' => new ComponentResource($component)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy thông tin component: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified component.
     * PUT/PATCH /api/v1/components/{component}
     */
    public function update(ComponentFormRequest $request, int $componentId): JsonResponse
    {
        try {
            $component = $this->componentService->updateComponent($componentId, $request->validated());
            
            if (!$component) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Component không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'component' => new ComponentResource($component)
                ],
                'message' => 'Component đã được cập nhật thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật component: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified component.
     * DELETE /api/v1/components/{component}
     */
    public function destroy(int $componentId): JsonResponse
    {
        try {
            $deleted = $this->componentService->deleteComponent($componentId);
            
            if (!$deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Component không tồn tại hoặc không thể xóa.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Component đã được xóa thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa component: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get component hierarchy tree.
     * GET /api/v1/projects/{project}/components/tree
     */
    public function tree(int $projectId): JsonResponse
    {
        try {
            $tree = $this->componentService->getComponentTree($projectId);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'tree' => $tree
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy cây component: ' . $e->getMessage()
            ], 500);
        }
    }
}