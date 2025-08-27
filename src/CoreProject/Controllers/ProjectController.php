<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Resources\ProjectResource;
use Src\CoreProject\Requests\StoreProjectRequest;
use Src\CoreProject\Requests\UpdateProjectRequest;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;

/**
 * Controller xử lý các hoạt động CRUD cho Project
 * 
 * @package Src\CoreProject\Controllers
 */
class ProjectController
{
    /**
     * Constructor - áp dụng RBAC middleware
     */
    public function __construct()
    {
        $this->middleware(RBACMiddleware::class);
    }

    /**
     * Lấy danh sách projects
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Project::with(['rootComponents', 'tasks']);

            // Filter theo status
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            // Filter theo visibility
            if ($request->has('visibility')) {
                $query->where('visibility', $request->get('visibility'));
            }

            // Filter theo date range
            if ($request->has('start_date_from')) {
                $query->where('start_date', '>=', $request->get('start_date_from'));
            }
            if ($request->has('start_date_to')) {
                $query->where('start_date', '<=', $request->get('start_date_to'));
            }

            // Filter theo progress range
            if ($request->has('progress_min')) {
                $query->where('progress', '>=', $request->get('progress_min'));
            }
            if ($request->has('progress_max')) {
                $query->where('progress', '<=', $request->get('progress_max'));
            }

            // Search theo tên
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $projects = $query->paginate(
                $request->get('per_page', 15)
            );

            return JSendResponse::success([
                'projects' => ProjectResource::collection($projects->items()),
                'pagination' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total()
                ]
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo project mới
     *
     * @param StoreProjectRequest $request
     * @return JsonResponse
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            $projectData = $request->validated();
            $projectData['tenant_id'] = $request->user()->tenant_id ?? 1;
            
            // Tạo project từ template nếu có
            if (isset($projectData['work_template_id'])) {
                $templateId = $projectData['work_template_id'];
                unset($projectData['work_template_id']);
                
                $project = Project::createFromTemplate($templateId, $projectData);
            } else {
                $project = Project::create($projectData);
            }
            
            $project->load(['rootComponents', 'tasks']);

            // Dispatch event
            event(new \Src\CoreProject\Events\ProjectCreated($project));

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Dự án đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết một project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function show(int $projectId): JsonResponse
    {
        try {
            $project = Project::with([
                'rootComponents.childComponents',
                'tasks.assignments.user',
                'tasks.component'
            ])->findOrFail($projectId);

            return JSendResponse::success([
                'project' => new ProjectResource($project)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin project
     *
     * @param UpdateProjectRequest $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function update(UpdateProjectRequest $request, int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            $oldData = $project->toArray();
            
            $project->update($request->validated());
            $project->load(['rootComponents', 'tasks']);

            // Dispatch event nếu có thay đổi về progress hoặc cost
            $changedFields = array_keys($request->validated());
            if (array_intersect($changedFields, ['progress', 'actual_cost', 'status'])) {
                event(new \Src\CoreProject\Events\ProjectUpdated(
                    $project,
                    $oldData,
                    $changedFields
                ));
            }

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Dự án đã được cập nhật thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function destroy(int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);

            // Kiểm tra xem project có components không
            if ($project->components()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa dự án này vì nó có các component liên kết. Vui lòng xóa các component trước.',
                    400
                );
            }

            // Kiểm tra xem project có tasks không
            if ($project->tasks()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa dự án này vì nó có các task liên kết. Vui lòng xóa các task trước.',
                    400
                );
            }

            $project->delete();

            // Dispatch event
            event(new \Src\CoreProject\Events\ProjectDeleted($project));

            return JSendResponse::success([
                'message' => 'Dự án đã được xóa thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tính toán lại progress của project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function recalculateProgress(int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            $project->recalculateProgress();
            $project->load(['rootComponents', 'tasks']);

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Tiến độ dự án đã được tính toán lại.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán lại tiến độ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tính toán lại chi phí thực tế của project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function recalculateActualCost(int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            $project->recalculateActualCost();
            $project->load(['rootComponents', 'tasks']);

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Chi phí thực tế đã được tính toán lại.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán lại chi phí: ' . $e->getMessage(), 500);
        }
    }
}