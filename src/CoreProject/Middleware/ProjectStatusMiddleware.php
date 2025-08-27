<?php declare(strict_types=1);

namespace Src\CoreProject\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\CoreProject\Models\Project;

/**
 * Middleware để validate project status cho các operations
 * 
 * Kiểm tra project status và cho phép/từ chối operations dựa trên trạng thái
 */
class ProjectStatusMiddleware
{
    /**
     * Mapping các operations với allowed statuses
     */
    private const OPERATION_STATUS_MAP = [
        'create_task' => ['planning', 'active'],
        'update_task' => ['planning', 'active'],
        'delete_task' => ['planning'],
        'create_component' => ['planning', 'active'],
        'update_component' => ['planning', 'active'],
        'delete_component' => ['planning'],
        'update_progress' => ['active'],
        'complete_project' => ['active'],
        'archive_project' => ['completed', 'cancelled']
    ];
    
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $operation Operation type to validate
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $operation)
    {
        $project = $request->get('project_model');
        
        if (!$project) {
            return $this->badRequestResponse('Project context required');
        }
        
        // Get allowed statuses for this operation
        $allowedStatuses = self::OPERATION_STATUS_MAP[$operation] ?? [];
        
        if (empty($allowedStatuses)) {
            return $this->badRequestResponse("Unknown operation: {$operation}");
        }
        
        // Check if current project status allows this operation
        if (!in_array($project->status, $allowedStatuses)) {
            $allowedStatusesStr = implode(', ', $allowedStatuses);
            return $this->badRequestResponse(
                "Operation '{$operation}' is not allowed for project status '{$project->status}'. " .
                "Allowed statuses: {$allowedStatusesStr}"
            );
        }
        
        // Add operation context to request
        $request->merge(['validated_operation' => $operation]);
        
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
}