<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\CoreProject\Models\Project;
use Src\Foundation\Events\BaseEvent;

/**
 * Middleware để validate project access và permissions cho CoreProject module
 * 
 * Middleware này sẽ:
 * - Kiểm tra project có tồn tại không
 * - Kiểm tra user có quyền truy cập project không
 * - Validate project status và visibility
 * - Inject project context vào request
 */
class ProjectAccessMiddleware
{
    private RBACManager $rbacManager;
    private EventBus $eventBus;
    
    public function __construct(RBACManager $rbacManager, EventBus $eventBus)
    {
        $this->rbacManager = $rbacManager;
        $this->eventBus = $eventBus;
    }
    
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $projectParam Parameter name containing project_id (default: 'project')
     * @param string|null $requiredStatus Required project status (optional)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $projectParam = 'project', ?string $requiredStatus = null)
    {
        // Get project ID from route or request
        $projectId = $request->route($projectParam) ?? $request->input('project_id');
        
        if (!$projectId) {
            return $this->badRequestResponse('Project ID is required');
        }
        
        // Get authenticated user
        $user = $request->get('auth_user');
        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }
        
        // Find project
        $project = Project::find($projectId);
        if (!$project) {
            $this->logProjectAccessAttempt($user, $projectId, 'project_not_found', $request);
            return $this->notFoundResponse('Project not found');
        }
        
        // Check if user has access to this project
        if (!$this->rbacManager->hasProjectAccess($user['user_id'], $projectId)) {
            $this->logProjectAccessAttempt($user, $projectId, 'access_denied', $request);
            return $this->forbiddenResponse('Access to this project is denied');
        }
        
        // Check project status if required
        if ($requiredStatus && $project->status !== $requiredStatus) {
            return $this->badRequestResponse("Project must be in '{$requiredStatus}' status");
        }
        
        // Check project visibility
        if ($project->visibility === 'private' && !$this->hasProjectRole($user['user_id'], $projectId)) {
            return $this->forbiddenResponse('Access to private project denied');
        }
        
        // Add project context to request
        $request->merge([
            'project_context' => $projectId,
            'project_model' => $project,
            'project_tenant_id' => $project->tenant_id
        ]);
        
        // Log successful access
        $this->logProjectAccessAttempt($user, $projectId, 'access_granted', $request);
        
        return $next($request);
    }
    
    /**
     * Check if user has any role in the project
     * 
     * @param int $userId
     * @param string $projectId
     * @return bool
     */
    private function hasProjectRole(int $userId, string $projectId): bool
    {
        return $this->rbacManager->getUserProjectRoles($userId, $projectId)->isNotEmpty();
    }
    
    /**
     * Log project access attempt for audit
     * 
     * @param array $user
     * @param string $projectId
     * @param string $result
     * @param Request $request
     */
    private function logProjectAccessAttempt(array $user, string $projectId, string $result, Request $request): void
    {
        $event = new BaseEvent('project.access.attempt', [
            'entityId' => $projectId,
            'projectId' => $projectId,
            'actorId' => $user['user_id'],
            'changedFields' => [
                'access_result' => $result,
                'endpoint' => $request->getPathInfo(),
                'method' => $request->getMethod(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ],
            'timestamp' => now()->toISOString()
        ]);
        
        $this->eventBus->publish('project.access.attempt', $event);
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