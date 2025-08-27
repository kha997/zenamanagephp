<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Resources\TaskResource;
use Src\CoreProject\Requests\StoreTaskRequest;
use Src\CoreProject\Requests\UpdateTaskRequest;
use Src\CoreProject\Services\TaskService;
use Src\CoreProject\Services\ConditionalTagService;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Controller xử lý các hoạt động CRUD cho Task với TaskService và ConditionalTagService
 * 
 * @package Src\CoreProject\Controllers
 */
class TaskController
{
    private TaskService $taskService;
    private ConditionalTagService $conditionalTagService;

    /**
     * Constructor - áp dụng RBAC middleware và inject services
     */
    public function __construct(
        TaskService $taskService,
        ConditionalTagService $conditionalTagService
    ) {
        $this->middleware(RBACMiddleware::class);
        $this->taskService = $taskService;
        $this->conditionalTagService = $conditionalTagService;
    }

    /**
     * Lấy danh sách tasks của một project với filtering và pagination
     *
     * @param Request $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $query = Task::where('project_id', $projectId)
                ->with(['component', 'assignments.user']);

            // Filtering
            if ($request->has('component_id')) {
                $query->where('component_id', $request->component_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('conditional_tag')) {
                $query->where('conditional_tag', 'LIKE', '%' . $request->conditional_tag . '%');
            }

            if ($request->has('is_hidden')) {
                $query->where('is_hidden', $request->boolean('is_hidden'));
            }

            if ($request->has('assigned_to')) {
                $query->whereHas('assignments', function ($q) use ($request) {
                    $q->where('user_id', $request->assigned_to);
                });
            }

            if ($request->has('start_date_from')) {
                $query->where('start_date', '>=', $request->start_date_from);
            }

            if ($request->has('start_date_to')) {
                $query->where('start_date', '<=', $request->start_date_to);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $tasks = $query->paginate($perPage);

            return JSendResponse::success([
                'tasks' => TaskResource::collection($tasks->items()),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total()
                ]
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách tasks: ' . $e->getMessage());
        }
    }

    /**
     * Tạo task mới sử dụng TaskService
     *
     * @param StoreTaskRequest $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function store(StoreTaskRequest $request, int $projectId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['project_id'] = $projectId;

            // Sử dụng TaskService để tạo task với validation đầy đủ
            $task = $this->taskService->createTask($data);

            // Load relationships
            $task->load(['component', 'assignments.user', 'project']);

            return JSendResponse::success([
                'task' => new TaskResource($task),
                'message' => 'Task đã được tạo thành công'
            ]);
        } catch (InvalidArgumentException $e) {
            return JSendResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo task: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết của một task
     *
     * @param int $projectId
     * @param int $taskId
     * @return JsonResponse
     */
    public function show(int $projectId, int $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->with(['component', 'assignments.user', 'project'])
                ->firstOrFail();

            return JSendResponse::success([
                'task' => new TaskResource($task)
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin task: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật task sử dụng TaskService
     *
     * @param UpdateTaskRequest $request
     * @param int $projectId
     * @param int $taskId
     * @return JsonResponse
     */
    public function update(UpdateTaskRequest $request, int $projectId, int $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $data = $request->validated();

            // Sử dụng TaskService để update với validation dependencies
            $updatedTask = $this->taskService->updateTask($task, $data);

            // Load relationships
            $updatedTask->load(['component', 'assignments.user', 'project']);

            return JSendResponse::success([
                'task' => new TaskResource($updatedTask),
                'message' => 'Task đã được cập nhật thành công'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (InvalidArgumentException $e) {
            return JSendResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật task: ' . $e->getMessage());
        }
    }

    /**
     * Xóa task với validation dependencies
     *
     * @param int $projectId
     * @param int $taskId
     * @return JsonResponse
     */
    public function destroy(int $projectId, int $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            // Kiểm tra assignments
            if ($task->assignments()->exists()) {
                return JSendResponse::error('Không thể xóa task đã có assignments. Vui lòng xóa assignments trước.', 400);
            }

            // Kiểm tra dependencies từ tasks khác
            $dependentTasks = Task::where('project_id', $projectId)
                ->whereJsonContains('dependencies', $taskId)
                ->exists();

            if ($dependentTasks) {
                return JSendResponse::error('Không thể xóa task vì có tasks khác phụ thuộc vào nó.', 400);
            }

            $task->delete();

            return JSendResponse::success([
                'message' => 'Task đã được xóa thành công'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa task: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dependency graph của project sử dụng TaskService
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function dependencyGraph(int $projectId): JsonResponse
    {
        try {
            $tasks = Task::where('project_id', $projectId)
                ->where('is_hidden', false)
                ->select(['id', 'name', 'status', 'dependencies'])
                ->get();

            $graph = $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'dependencies' => $task->dependencies ?? []
                ];
            });

            // Lấy critical path sử dụng TaskService
            $criticalPath = $this->taskService->getCriticalPath($projectId);

            return JSendResponse::success([
                'graph' => $graph,
                'critical_path' => $criticalPath
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy dependency graph: ' . $e->getMessage());
        }
    }

    /**
     * Toggle visibility của task sử dụng ConditionalTagService
     *
     * @param int $projectId
     * @param int $taskId
     * @return JsonResponse
     */
    public function toggleVisibility(int $projectId, int $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $task->is_hidden = !$task->is_hidden;
            $task->save();

            return JSendResponse::success([
                'task' => new TaskResource($task),
                'message' => $task->is_hidden ? 'Task đã được ẩn' : 'Task đã được hiển thị'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể thay đổi visibility: ' . $e->getMessage());
        }
    }

    /**
     * Process conditional tags cho tất cả tasks trong project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function processConditionalTags(int $projectId): JsonResponse
    {
        try {
            $results = $this->conditionalTagService->processProjectConditionalTags($projectId);

            return JSendResponse::success([
                'results' => $results,
                'message' => "Đã xử lý {$results['processed']} conditional tags"
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xử lý conditional tags: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách available conditional tags
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function getAvailableConditionalTags(int $projectId): JsonResponse
    {
        try {
            $project = \Src\CoreProject\Models\Project::findOrFail($projectId);
            $tags = $this->conditionalTagService->getAvailableConditionalTags($project);

            return JSendResponse::success([
                'conditional_tags' => $tags
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Project không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy conditional tags: ' . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách tasks sẵn sàng để bắt đầu
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function getReadyTasks(int $projectId): JsonResponse
    {
        try {
            $readyTasks = $this->taskService->getReadyTasks($projectId);

            return JSendResponse::success([
                'ready_tasks' => TaskResource::collection($readyTasks),
                'count' => $readyTasks->count()
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy ready tasks: ' . $e->getMessage());
        }
    }

    /**
     * Validate dependencies cho task
     *
     * @param Request $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function validateDependencies(Request $request, int $projectId): JsonResponse
    {
        try {
            $dependencies = $request->input('dependencies', []);
            $excludeTaskId = $request->input('exclude_task_id');

            $this->taskService->validateDependencies($dependencies, $projectId, $excludeTaskId);

            return JSendResponse::success([
                'valid' => true,
                'message' => 'Dependencies hợp lệ'
            ]);
        } catch (InvalidArgumentException $e) {
            return JSendResponse::success([
                'valid' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể validate dependencies: ' . $e->getMessage());
        }
    }

    /**
     * Tính toán lại schedule cho task
     *
     * @param int $projectId
     * @param int $taskId
     * @return JsonResponse
     */
    public function recalculateSchedule(int $projectId, int $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $this->taskService->calculateTaskSchedule($task);

            $task->load(['component', 'assignments.user', 'project']);

            return JSendResponse::success([
                'task' => new TaskResource($task),
                'message' => 'Schedule đã được tính toán lại'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán schedule: ' . $e->getMessage());
        }
    }
}