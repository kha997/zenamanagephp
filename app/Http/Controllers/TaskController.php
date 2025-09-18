<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TaskFormRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * RESTful Controller cho Task management
 * 
 * @package App\Http\Controllers
 */
class TaskController extends Controller
{
    /**
     * TaskController constructor.
     *
     * @param TaskService $taskService
     */
    public function __construct(private readonly TaskService $taskService)
    {
    }

    /**
     * Display tasks of a project.
     * GET /api/v1/projects/{project}/tasks
     */
    public function index(int $projectId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'component_id', 'assigned_to', 'search', 'start_date', 'end_date']);
            $filters['project_id'] = $projectId;
            $perPage = (int) $request->get('per_page', 15);
            
            $tasks = $this->taskService->getTasks($filters, $perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'tasks' => TaskResource::collection($tasks->items()),
                    'pagination' => [
                        'current_page' => $tasks->currentPage(),
                        'last_page' => $tasks->lastPage(),
                        'per_page' => $tasks->perPage(),
                        'total' => $tasks->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created task.
     * POST /api/v1/projects/{project}/tasks
     */
    public function store(TaskFormRequest $request, int $projectId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['project_id'] = $projectId;
            
            $task = $this->taskService->createTask($data);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task)
                ],
                'message' => 'Task đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified task.
     * GET /api/v1/tasks/{task}
     */
    public function show(int $taskId, Request $request): JsonResponse
    {
        try {
            $includes = $request->get('include', []);
            if (is_string($includes)) {
                $includes = explode(',', $includes);
            }
            
            $task = $this->taskService->getTaskById($taskId, $includes);
            
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy thông tin task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified task.
     * PUT/PATCH /api/v1/tasks/{task}
     */
    public function update(TaskFormRequest $request, int $taskId): JsonResponse
    {
        try {
            $task = $this->taskService->updateTask($taskId, $request->validated());
            
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task)
                ],
                'message' => 'Task đã được cập nhật thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified task.
     * DELETE /api/v1/tasks/{task}
     */
    public function destroy(int $taskId): JsonResponse
    {
        try {
            $deleted = $this->taskService->deleteTask($taskId);
            
            if (!$deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task không tồn tại hoặc không thể xóa.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Task đã được xóa thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status.
     * PATCH /api/v1/tasks/{task}/status
     */
    public function updateStatus(Request $request, int $taskId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);
        
        try {
            $task = $this->taskService->updateTaskStatus($taskId, $request->status);
            
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task)
                ],
                'message' => 'Trạng thái task đã được cập nhật.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật trạng thái task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display tasks list page (Web)
     */
    public function indexWeb(): View
    {
        return view('tasks.index');
    }

    /**
     * Show the form for creating a new task (Web)
     */
    public function create(): View
    {
        return view('tasks.create');
    }

    /**
     * Display the specified task (Web)
     */
    public function showWeb($task): View
    {
        return view('tasks.show', compact('task'));
    }

    /**
     * Show the form for editing the specified task (Web)
     */
    public function edit($task): View
    {
        return view('tasks.edit', compact('task'));
    }
}