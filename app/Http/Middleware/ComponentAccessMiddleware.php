<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\CoreProject\Models\Component;

/**
 * Middleware để validate component access và permissions
 * 
 * Kiểm tra:
 * - Component có tồn tại không
 * - Component có thuộc project hiện tại không
 * - User có quyền truy cập component không
 */
class ComponentAccessMiddleware
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
     * @param string $componentParam Parameter name containing component_id
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $componentParam = 'component')
    {
        $componentId = $request->route($componentParam) ?? $request->input('component_id');
        $projectId = $request->get('project_context');
        $user = $request->get('auth_user');
        
        if (!$componentId) {
            return $this->badRequestResponse('Component ID is required');
        }
        
        if (!$projectId) {
            return $this->badRequestResponse('Project context is required');
        }
        
        // Find component
        $component = Component::find($componentId);
        if (!$component) {
            return $this->notFoundResponse('Component not found');
        }
        
        // Check if component belongs to the current project
        if ($component->project_id !== $projectId) {
            return $this->forbiddenResponse('Component does not belong to the current project');
        }
        
        // Check component access permission
        if (!$this->rbacManager->hasPermission($user['user_id'], 'component.view', $projectId)) {
            return $this->forbiddenResponse('Access to component denied');
        }
        
        // Add component context to request
        $request->merge([
            'component_context' => $componentId,
            'component_model' => $component
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