<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskManagementService;
use App\Services\ProjectManagementService;
use App\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Web Task Controller
 * 
 * Handles task management in the web interface,
 * providing views and handling form submissions.
 */
class TaskController extends Controller
{
    public function __construct(
        private TaskManagementService $taskService,
        private ProjectManagementService $projectService,
        private UserManagementService $userService
    ) {}


    /**
     * Display tasks Kanban board
     */
    public function kanban(Request $request): View
    {
        $filters = $request->only([
            'project_id',
            'status',
            'priority',
            'assignee_id',
            'search',
            'sort_by',
            'sort_direction',
            'page'
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Fetch tasks data
        $tasks = $this->taskService->getTasks($filters, 50, 'created_at', 'desc', $tenantId);
        $projects = $this->projectService->getProjects([], 100, 'name', 'asc', $tenantId);
        $users = $this->userService->getUsers([], 100, 'name', 'asc', $tenantId);

        return view('app.tasks.kanban', [
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users,
            'filters' => $filters
        ]);
    }

    /**
     * Display tasks list
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'project_id',
            'status',
            'priority',
            'assignee_id',
            'search',
            'sort_by',
            'sort_direction',
            'page'
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Fetch tasks data
        $tasks = $this->taskService->getTasks($filters, 50, 'created_at', 'desc', $tenantId);
        $projects = $this->projectService->getProjects([], 100, 'name', 'asc', $tenantId);
        $users = $this->userService->getUsers([], 100, 'name', 'asc', $tenantId);

        // Log for debugging
        \Log::info('TaskController::index', [
            'tenant_id' => $tenantId,
            'tasks_count' => $tasks->count(),
            'projects_count' => $projects->count(),
            'users_count' => $users->count(),
        ]);

        return view('app.tasks.index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users,
            'filters' => $filters
        ]);
    }

    /**
     * Show React Kanban board
     */
    public function kanbanReact(Request $request): View
    {
        $filters = $request->only([
            'project_id',
            'status',
            'priority',
            'assignee_id',
            'search',
            'sort_by',
            'sort_direction'
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Fetch tasks data for React Kanban
        $tasks = $this->taskService->getTasksList([], auth()->id(), $tenantId);
        $projects = \App\Models\Project::where('tenant_id', $tenantId)->get();
        $users = \App\Models\User::where('tenant_id', $tenantId)->get();

        return view('app.tasks.kanban-react', [
            'tasks' => $tasks,
            'projects' => $projects,
            'users' => $users,
            'filters' => $filters
        ]);
    }

    /**
     * Show task details
     */
    public function show(Task $task): View
    {
        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Check if the task belongs to the user's tenant
        if ($task->tenant_id !== $tenantId) {
            abort(404, 'Task not found');
        }
        
        $projects = $this->projectService->getProjects([], 100, 'name', 'asc', $tenantId);
        $users = $this->userService->getUsers([], 100, 'name', 'asc', $tenantId);

        return view('app.tasks.show', [
            'task' => $task,
            'projects' => $projects,
            'users' => $users
        ]);
    }

    /**
     * Show create task form
     */
    public function create(): View
    {
        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        $projects = $this->projectService->getProjects([], 100, 'name', 'asc', $tenantId);
        $users = $this->userService->getUsers([], 100, 'name', 'asc', $tenantId);

        return view('app.tasks.create', [
            'projects' => $projects,
            'users' => $users
        ]);
    }

    /**
     * Store new task
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'project_id' => 'required|string|ulid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:backlog,in_progress,blocked,done,canceled',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'assignee_id' => 'nullable|string|ulid',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_hours' => 'nullable|numeric|min:0'
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        $taskData = $request->all();
        $taskData['created_by'] = auth()->user()->id;
        
        $task = $this->taskService->createTask($taskData, $tenantId);

        if ($task) {
            return redirect()->route('app.tasks.show', $task->id)
                            ->with('success', 'Task created successfully!');
        }

        return back()->with('error', 'Failed to create task.');
    }

    /**
     * Show edit task form
     */
    public function edit(string $id): View
    {
        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        $task = $this->taskService->getTaskById($id, $tenantId);
        if (!$task) {
            abort(404, 'Task not found');
        }
        
        $projects = $this->projectService->getProjects([], 100, 'name', 'asc', $tenantId);
        $users = $this->userService->getUsers([], 100, 'name', 'asc', $tenantId);

        return view('app.tasks.edit', [
            'task' => $task,
            'projects' => $projects,
            'users' => $users
        ]);
    }

    /**
     * Update task
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'project_id' => 'required|string|ulid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:backlog,in_progress,blocked,done,canceled',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'assignee_id' => 'nullable|string|ulid',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_hours' => 'nullable|numeric|min:0'
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        $taskData = $request->all();
        
        $task = $this->taskService->updateTask($id, $taskData, $tenantId);

        if ($task) {
            return redirect()->route('app.tasks.show', $id)
                            ->with('success', 'Task updated successfully!');
        }

        return back()->with('error', 'Failed to update task.');
    }

    /**
     * Delete task
     */
    public function destroy(string $id): RedirectResponse
    {
        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        if ($this->taskService->deleteTask($id, $tenantId)) {
            return redirect()->route('app.tasks.index')
                            ->with('success', 'Task deleted successfully!');
        }

        return back()->with('error', 'Failed to delete task.');
    }

    /**
     * Handle bulk actions for tasks.
     */
    public function bulkActions(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required|string|in:delete,update_status,assign',
            'task_ids' => 'required|array',
            'task_ids.*' => 'string|ulid',
            'status' => 'nullable|string|in:backlog,in_progress,blocked,done,canceled',
            'assignee_id' => 'nullable|string|ulid',
        ]);

        $tenantId = auth()->check() ? (string) auth()->user()->tenant_id : '01K83FPK5XGPXF3V7ANJQRGX5X';
        $taskIds = $request->input('task_ids');
        $action = $request->input('action');
        $success = false;

        switch ($action) {
            case 'delete':
                $success = $this->taskService->bulkDeleteTasks($taskIds, $tenantId);
                $message = $success ? 'Selected tasks deleted successfully!' : 'Failed to delete selected tasks.';
                break;
            case 'update_status':
                $status = $request->input('status');
                $success = $this->taskService->bulkUpdateTaskStatus($taskIds, $status, $tenantId);
                $message = $success ? 'Selected tasks status updated successfully!' : 'Failed to update selected tasks status.';
                break;
            case 'assign':
                $assigneeId = $request->input('assignee_id');
                $success = $this->taskService->bulkAssignTasks($taskIds, $assigneeId, $tenantId);
                $message = $success ? 'Selected tasks assigned successfully!' : 'Failed to assign selected tasks.';
                break;
            default:
                $message = 'Invalid action.';
                break;
        }

        if ($success) {
            return back()->with('success', $message);
        }

        return back()->with('error', $message);
    }

}