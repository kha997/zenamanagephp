<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\CoreProject\Models\Task;

/**
 * Middleware để validate task access và permissions
 * 
 * Kiểm tra:
 * - Task có tồn tại không
 * - Task có thuộc project hiện tại không
 * - User có quyền truy cập task không
 * - Task có bị ẩn (hidden) không
 */
class TaskAccessMiddleware
{
    private RBACManager $rbacManager;
    
    public function __construct(RBACManager $rbacManager)
    {
        $this->rbacManager = $rbacManager;
    }
    
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $taskParam Parameter name containing task_id
     * @param bool $allowHidden Allow access to hidden tasks
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $taskParam = 'task', bool $allowHidden = false)
    {
        $taskId = $request->route($taskParam) ?? $request->input('task_id');
        $projectId = $request->get('project_context');
        $user = $request->get('auth_user');
        
        if (!$taskId) {
            return $this->badRequestResponse('Task ID is required');
        }
        
        if (!$projectId) {
            return $this->badRequestResponse('Project context is required');
        }
        
        // Find task
        $task = Task::find($taskId);
        if (!$task) {
            return $this->notFoundResponse('Task not found');
        }
        
        // Check if task belongs to the current project
        if ($task->project_id !== $projectId) {
            return $this->forbiddenResponse('Task does not belong to the current project');
        }
        
        // Check if task is hidden and if hidden tasks are allowed
        if ($task->is_hidden && !$allowHidden) {
            return $this->notFoundResponse('Task not found');
        }
        
        // Check task access permission
        if (!$this->rbacManager->hasPermission($user['user_id'], 'task.view', $projectId)) {
            return $this->forbiddenResponse('Access to task denied');
        }
        
        // Check if user is assigned to this task for assignment-specific operations
        $isAssigned = $task->assignments()->where('user_id', $user['user_id'])->exists();
        
        // Add task context to request
        $request->merge([
            'task_context' => $taskId,
            'task_model' => $task,
            'is_task_assigned' => $isAssigned
        ]);
        
        return $next($request);
    }
    
    /**
     * Return bad request response
     * 
     * @param string $message
     * @return Response
     */
    private function badRequestResponse(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 400);
    }
    
    /**
     * Return forbidden response
     * 
     * @param string $message
     * @return Response
     */
    private function forbiddenResponse(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 403);
    }
    
    /**
     * Return not found response
     * 
     * @param string $message
     * @return Response
     */
    private function notFoundResponse(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 404);
    }
}