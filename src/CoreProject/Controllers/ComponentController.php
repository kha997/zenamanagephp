<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Resources\ComponentResource;
use Src\CoreProject\Requests\StoreComponentRequest;
use Src\CoreProject\Requests\UpdateComponentRequest;
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
        $this->middleware(RBACMiddleware::class);
    }

    /**
     * Lấy danh sách components của một project
     *
     * @param Request $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $query = Component::where('project_id', $projectId)
                ->with(['parentComponent', 'childComponents', 'tasks']);

            // Filter theo parent component nếu có
            if ($request->has('parent_id')) {
                $query->where('parent_component_id', $request->get('parent_id'));
            }

            // Filter chỉ lấy root components (không có parent)
            if ($request->boolean('root_only')) {
                $query->whereNull('parent_component_id');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            $components = $query->paginate(
                $request->get('per_page', 15)
            );

            return JSendResponse::success([
                'components' => ComponentResource::collection($components->items()),
                'pagination' => [
                    'current_page' => $components->currentPage(),
                    'last_page' => $components->lastPage(),
                    'per_page' => $components->perPage(),
                    'total' => $components->total()
                ]
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách components: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo component mới
     *
     * @param StoreComponentRequest $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function store(StoreComponentRequest $request, int $projectId): JsonResponse
    {
        try {
            $componentData = $request->validated();
            $componentData['project_id'] = $projectId;

            $component = Component::create($componentData);
            $component->load(['parentComponent', 'childComponents', 'tasks']);

            // Dispatch event để cập nhật progress của project
            event(new \Src\CoreProject\Events\ComponentCreated($component));

            return JSendResponse::success([
                'component' => new ComponentResource($component),
                'message' => 'Component đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết một component
     *
     * @param int $projectId
     * @param int $componentId
     * @return JsonResponse
     */
    public function show(int $projectId, int $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                ->where('id', $componentId)
                ->with(['parentComponent', 'childComponents', 'tasks', 'project'])
                ->firstOrFail();

            return JSendResponse::success([
                'component' => new ComponentResource($component)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Component không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin component
     *
     * @param UpdateComponentRequest $request
     * @param int $projectId
     * @param int $componentId
     * @return JsonResponse
     */
    public function update(UpdateComponentRequest $request, int $projectId, int $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                ->where('id', $componentId)
                ->firstOrFail();

            $oldData = $component->toArray();
            $component->update($request->validated());
            $component->load(['parentComponent', 'childComponents', 'tasks']);

            // Dispatch event nếu có thay đổi về progress hoặc cost
            $changedFields = array_keys($request->validated());
            if (array_intersect($changedFields, ['progress_percent', 'actual_cost'])) {
                event(new \Src\CoreProject\Events\ComponentProgressUpdated(
                    $component,
                    $oldData,
                    $changedFields
                ));
            }

            return JSendResponse::success([
                'component' => new ComponentResource($component),
                'message' => 'Component đã được cập nhật thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Component không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa component
     *
     * @param int $projectId
     * @param int $componentId
     * @return JsonResponse
     */
    public function destroy(int $projectId, int $componentId): JsonResponse
    {
        try {
            $component = Component::where('project_id', $projectId)
                ->where('id', $componentId)
                ->firstOrFail();

            // Kiểm tra xem component có child components không
            if ($component->childComponents()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa component này vì nó có các component con. Vui lòng xóa các component con trước.',
                    400
                );
            }

            // Kiểm tra xem component có tasks không
            if ($component->tasks()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa component này vì nó có các task liên kết. Vui lòng xóa các task trước.',
                    400
                );
            }

            $component->delete();

            // Dispatch event để cập nhật lại progress của project
            event(new \Src\CoreProject\Events\ComponentDeleted($component));

            return JSendResponse::success([
                'message' => 'Component đã được xóa thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Component không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa component: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy cây phân cấp components của project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function tree(int $projectId): JsonResponse
    {
        try {
            $components = Component::where('project_id', $projectId)
                ->whereNull('parent_component_id') // Chỉ lấy root components
                ->with(['childComponents' => function ($query) {
                    $query->with('childComponents'); // Load nested children
                }])
                ->orderBy('name')
                ->get();

            return JSendResponse::success([
                'component_tree' => ComponentResource::collection($components)
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy cây phân cấp components: ' . $e->getMessage(), 500);
        }
    }
}