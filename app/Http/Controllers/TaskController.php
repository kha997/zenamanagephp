<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Resources\TaskResource;
use Src\Foundation\Utils\JSendResponse;
use Src\RBAC\Middleware\RBACMiddleware;

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
     * Constructor - inject services
     */
    public function __construct(
        TaskService $taskService,
        ConditionalTagService $conditionalTagService
    ) {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
        $this->taskService = $taskService;
        $this->conditionalTagService = $conditionalTagService;
    }

    /**
     * Lấy danh sách tasks của một project với filtering và pagination
     *
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        // Thêm validation để chặn SQL injection
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['todo', 'in_progress', 'done', 'pending'])],
            'component_id' => 'nullable|integer|exists:components,id',
            'conditional_tag' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'sort_by' => ['nullable', Rule::in(['name', 'created_at', 'start_date', 'end_date'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $query = Task::where('project_id', $projectId)
                ->with(['component', 'assignments.user']);

            // Sử dụng validated data thay vì raw request
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function($q) 
                });
            }

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
                $query->whereHas('assignments', function ($q) 
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
        } catch (QueryException $e) {
            return response()->json(['message' => 'Bad query'], 400);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách tasks: ' . $e->getMessage());
        }
    }

    /**
     * Tạo task mới sử dụng TaskService
     *
     * @param StoreTaskRequest $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function store(StoreTaskRequest $request, string $projectId): JsonResponse
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
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function show(string $projectId, string $taskId): JsonResponse
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
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function update(UpdateTaskRequest $request, string $projectId, string $taskId): JsonResponse
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
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function destroy(string $projectId, string $taskId): JsonResponse
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
     * @param string $projectId
     * @return JsonResponse
     */
    public function dependencyGraph(string $projectId): JsonResponse
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
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function toggleVisibility(string $projectId, string $taskId): JsonResponse
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
     * @param string $projectId
     * @return JsonResponse
     */
    public function processConditionalTags(string $projectId): JsonResponse
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
     * @param string $projectId
     * @return JsonResponse
     */
    public function getAvailableConditionalTags(string $projectId): JsonResponse
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
     * @param string $projectId
     * @return JsonResponse
     */
    public function getReadyTasks(string $projectId): JsonResponse
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
     * @param string $projectId
     * @return JsonResponse
     */
    public function validateDependencies(Request $request, string $projectId): JsonResponse
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
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function recalculateSchedule(string $projectId, string $taskId): JsonResponse
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

    /**
     * Cập nhật trạng thái task
     *
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $projectId, string $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $request->validate([
                'status' => 'required|string|in:' . implode(',', array_keys(Task::STATUSES))
            ]);

            $updatedTask = $this->taskService->updateTaskStatus($task->ulid, $request->status);
            $updatedTask->load(['component', 'assignments.user', 'project']);

            return JSendResponse::success([
                'task' => new TaskResource($updatedTask),
                'message' => 'Trạng thái task đã được cập nhật thành công'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\InvalidArgumentException $e) {
            return JSendResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật trạng thái task: ' . $e->getMessage());
        }
    }

    /**
     * Gán user cho task
     *
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function assignUser(Request $request, string $projectId, string $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'split_percent' => 'nullable|numeric|min:0|max:100',
                'role' => 'nullable|string|in:assignee,reviewer,observer'
            ]);

            // Kiểm tra tổng split_percent không vượt quá 100%
            $currentTotal = $task->assignments()->sum('split_percent');
            $newPercentage = $request->split_percent ?? 100;
            
            if ($currentTotal + $newPercentage > 100) {
                return JSendResponse::error(
                    'Tổng phần trăm phân chia không được vượt quá 100%. Hiện tại: ' . $currentTotal . '%',
                    400
                );
            }

            // Tạo assignment mới
            $assignment = $task->assignments()->create([
                'user_id' => $request->user_id,
                'split_percent' => $newPercentage,
                'role' => $request->role ?? 'assignee'
            ]);

            $assignment->load(['user', 'task']);

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment),
                'message' => 'User đã được gán cho task thành công'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể gán user cho task: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật dependencies cho task
     *
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function updateDependencies(Request $request, string $projectId, string $taskId): JsonResponse
    {
        try {
            $task = Task::where('project_id', $projectId)
                ->where('id', $taskId)
                ->firstOrFail();

            $request->validate([
                'dependencies' => 'required|array',
                'dependencies.*' => [
                    'integer',
                    'exists:tasks,id',
                    'not_in:' . $taskId, // Không thể phụ thuộc vào chính nó
                    function ($attribute, $value, $fail) 
                        if ($dependentTask && $dependentTask->project_id !== $projectId) {
                            $fail('Task phụ thuộc phải thuộc cùng dự án.');
                        }
                    }
                ]
            ]);

            $task->update([
                'dependencies' => $request->dependencies
            ]);

            $task->load(['component', 'assignments.user', 'project']);

            return JSendResponse::success([
                'task' => new TaskResource($task),
                'message' => 'Phụ thuộc công việc đã được cập nhật'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật dependencies: ' . $e->getMessage());
        }
    }
}