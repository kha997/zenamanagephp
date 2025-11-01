<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\CoreProject\Models\Project;

/**
 * Middleware để kiểm tra quyền sở hữu hoặc quản lý project
 * 
 * Sử dụng cho các operations quan trọng như:
 * - Xóa project
 * - Thay đổi cấu hình project
 * - Quản lý thành viên project
 */
class ProjectOwnershipMiddleware
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
     * @param string $level Required ownership level: 'owner', 'manager', 'admin'
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $level = 'manager')
    {
        $user = $request->get('auth_user');
        $projectId = $request->get('project_context');
        
        if (!$user || !$projectId) {
            return $this->unauthorizedResponse('Authentication and project context required');
        }
        
        // Check ownership level
        $hasOwnership = match($level) {
            'owner' => $this->isProjectOwner($user['user_id'], $projectId),
            'manager' => $this->isProjectManager($user['user_id'], $projectId),
            'admin' => $this->isProjectAdmin($user['user_id'], $projectId),
            default => false
        };
        
        if (!$hasOwnership) {
            return $this->forbiddenResponse("Project {$level} access required");
        }
        
        return $next($request);
    }
    
    /**
     * Check if user is project owner
     * 
     * @param int $userId
     * @param string $projectId
     * @return bool
     */
    private function isProjectOwner(int $userId, string $projectId): bool
    {
        $project = Project::find($projectId);
        return $project && $project->created_by === $userId;
    }
    
    /**
     * Check if user is project manager
     * 
     * @param int $userId
     * @param string $projectId
     * @return bool
     */
    private function isProjectManager(int $userId, string $projectId): bool
    {
        return $this->isProjectOwner($userId, $projectId) || 
               $this->rbacManager->hasPermission($userId, 'project.manage', $projectId);
    }
    
    /**
     * Check if user is project admin (system admin or project owner/manager)
     * 
     * @param int $userId
     * @param string $projectId
     * @return bool
     */
    private function isProjectAdmin(int $userId, string $projectId): bool
    {
        return $this->rbacManager->hasSystemRole($userId, 'system_admin') ||
               $this->isProjectManager($userId, $projectId);
    }
    
    /**
     * Return unauthorized response
     * 
     * @param string $message
     * @return Response
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 401);
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
}