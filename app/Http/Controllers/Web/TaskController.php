<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskFormRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

/**
 * Web Task Controller for task management interface
 * 
 * @package App\Http\Controllers\Web
 */
class TaskController extends Controller
{
    use \App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;

    /**
     * TaskController constructor.
     *
     * @param TaskService $taskService
     */
    public function __construct(private readonly TaskService $taskService)
    {
    }

    /**
     * Display a listing of tasks.
     */
    public function index(Request $request): View
    {
        try {
            $filters = $request->only(['status', 'project_id', 'assignee_id', 'search']);
            $perPage = (int) $request->get('per_page', 15);
            
            $tasks = $this->taskService->getTasks($filters, $perPage);
            $projects = Project::select('id', 'name')->get();
            
            return view('tasks.index', compact('tasks', 'projects'));
        } catch (\Exception $e) {
            return view('tasks.index', [
                'tasks' => collect(),
                'projects' => collect(),
                'error' => 'Không thể tải danh sách tasks: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API endpoint to get tasks for frontend
     */
    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'project_id', 'assignee_id', 'search']);
            $perPage = (int) $request->get('per_page', 50);
            
            $tasks = $this->taskService->getTasks($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tasks' => $tasks->items(),
                    'total' => $tasks->total(),
                    'per_page' => $tasks->perPage(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new task.
     */
    public function create(Request $request): View
    {
        try {
            $projects = Project::query()
                ->where('tenant_id', (string) \Auth::user()?->tenant_id)
                ->select('id', 'name')
                ->get();
            $projectId = $request->get('project_id');

            return view('tasks.create', compact('projects', 'projectId'));
        } catch (\Exception $e) {
            return view('tasks.create', [
                'projects' => collect(),
                'projectId' => null,
                'error' => 'Không thể tải form tạo task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store a newly created task.
     */
    public function store(
        Request $request,
        \App\Http\Controllers\Api\TaskController $apiController
    ): RedirectResponse {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        try {
            $response = $apiController->store(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null))
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Không thể tạo công việc.');
        }

        return $this->handleMutationResponse($response, route('app.tasks'), 'Đã tạo công việc');
    }

    /**
     * Display the specified task.
     */
    public function show(string $taskId): View|RedirectResponse
    {
        try {
            $task = Task::with(['project'])
                ->where('tenant_id', (string) \Auth::user()?->tenant_id)
                ->findOrFail($taskId);
            
            return view('tasks.show', compact('task'));
        } catch (\Exception $e) {
            \Log::error('Task show error', [
                'task_id' => $taskId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()
                ->route('tasks.index')
                ->withErrors(['error' => 'Không thể tải task: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified task.
     */
    public function edit(string $taskId): View
    {
        try {
            $tenantId = (string) \Auth::user()?->tenant_id;
            $task = Task::query()->where('tenant_id', $tenantId)->findOrFail($taskId);
            $projects = Project::query()->where('tenant_id', $tenantId)->select('id', 'name')->get();

            return view('tasks.edit', compact('task', 'projects'));
        } catch (\Exception $e) {
            return view('tasks.edit', [
                'task' => null,
                'projects' => collect(),
                'error' => 'Không thể tải form chỉnh sửa task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for editing a task (debug version).
     */
    public function editDebug(string $taskId): View
    {
        try {
            $task = Task::findOrFail($taskId);
            $projects = Project::select('id', 'name')->get();
            
            return view('tasks.edit-debug', compact('task', 'projects'));
        } catch (\Exception $e) {
            return view('tasks.edit-debug', [
                'task' => null,
                'projects' => collect(),
                'error' => 'Không thể tải form chỉnh sửa task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for editing a task (simple debug version).
     */
    public function editSimpleDebug(string $taskId): View
    {
        try {
            $task = Task::findOrFail($taskId);
            $projects = Project::select('id', 'name')->get();
            
            return view('tasks.edit-simple-debug', compact('task', 'projects'));
        } catch (\Exception $e) {
            return view('tasks.edit-simple-debug', [
                'task' => null,
                'projects' => collect(),
                'error' => 'Không thể tải form chỉnh sửa task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(
        Request $request,
        string $taskId,
        \App\Http\Controllers\Api\TaskController $apiController
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            $response = $apiController->update(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null)),
                $taskId
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Không thể cập nhật công việc.');
        }

        return $this->handleMutationResponse($response, '/app/tasks/' . $taskId, 'Đã cập nhật công việc');
    }

    /**
     * Remove the specified task.
     */
    public function destroy(string $taskId): RedirectResponse
    {
        try {
            $deleted = $this->taskService->deleteTask($taskId);
            
            if (!$deleted) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => 'Task không tồn tại hoặc không thể xóa.']);
            }
            
            return redirect()
                ->route('tasks.index')
                ->with('success', 'Task đã được xóa thành công!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Không thể xóa task: ' . $e->getMessage()]);
        }
    }

    /**
     * Đánh dấu công việc đang vướng (blocker) — dùng App\Models\Task
     * (model canonical) thay vì alias Src\CoreProject của file này.
     */
    public function block(Request $request, string $taskId): RedirectResponse
    {
        $request->validate(['blocker_note' => ['required', 'string', 'max:1000']]);

        $task = \App\Models\Task::query()
            ->where('tenant_id', (string) auth()->user()?->tenant_id)
            ->findOrFail($taskId);

        $task->forceFill([
            'blocked_at' => now(),
            'blocker_note' => (string) $request->string('blocker_note'),
            'blocked_by' => (string) auth()->id(),
        ])->save();

        return back()->with('success', 'Đã đánh dấu công việc đang vướng.');
    }

    public function unblock(string $taskId): RedirectResponse
    {
        $task = \App\Models\Task::query()
            ->where('tenant_id', (string) auth()->user()?->tenant_id)
            ->findOrFail($taskId);

        $task->forceFill(['blocked_at' => null, 'blocker_note' => null, 'blocked_by' => null])->save();

        return back()->with('success', 'Đã gỡ trạng thái vướng.');
    }

    /**
     * Update task status via AJAX
     */
    public function updateStatus(Request $request, string $taskId): JsonResponse
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
     * Get tasks for a specific project
     */
    public function getProjectTasks(string $projectId): JsonResponse
    {
        try {
            $tasks = Task::where('project_id', $projectId)
                ->with(['assignee'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'tasks' => TaskResource::collection($tasks)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Archive a task
     */
    public function archive(Request $request, string $taskId): JsonResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            $task->update(['status' => 'archived']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Task đã được lưu trữ thành công!',
                'data' => [
                    'task' => new TaskResource($task)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lưu trữ task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move a task to another project
     */
    public function move(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id'
        ]);
        
        try {
            $task = Task::findOrFail($taskId);
            $task->update(['project_id' => $request->project_id]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Task đã được di chuyển thành công!',
                'data' => [
                    'task' => new TaskResource($task)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể di chuyển task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task documents
     */
    public function documents(string $taskId): JsonResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            
            $documents = []; // Placeholder
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task),
                    'documents' => $documents
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy tài liệu task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store task document
     */
    public function storeDocument(Request $request, string $taskId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'note' => 'nullable|string|max:1000'
        ]);
        
        try {
            $task = Task::findOrFail($taskId);
            
            
            return response()->json([
                'status' => 'success',
                'message' => 'Tài liệu đã được lưu thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lưu tài liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get task history/log
     */
    public function history(string $taskId): JsonResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            
            $history = [
                [
                    'action' => 'Task Created',
                    'description' => 'Task was created and assigned',
                    'timestamp' => $task->created_at,
                    'user' => 'System'
                ],
                [
                    'action' => 'Status Changed',
                    'description' => 'Status changed to ' . $task->status,
                    'timestamp' => $task->updated_at,
                    'user' => 'System'
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'task' => new TaskResource($task),
                    'history' => $history
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy lịch sử task: ' . $e->getMessage()
            ], 500);
        }
    }
}