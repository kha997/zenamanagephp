<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModuleReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ModuleReadinessController
 * 
 * Provides endpoints for checking module readiness before enabling feature flags.
 */
class ModuleReadinessController extends Controller
{
    private ModuleReadinessService $readinessService;

    public function __construct(ModuleReadinessService $readinessService)
    {
        $this->readinessService = $readinessService;
    }

    /**
     * Get readiness checklist for a module
     * 
     * @param string $module
     * @return JsonResponse
     */
    public function getReadiness(Request $request, string $module): JsonResponse
    {
        try {
            $checklist = $this->readinessService->getReadinessChecklist($module);
            $isReady = $this->readinessService->isModuleReady($module);
            
            $completionRate = $checklist['total_items'] > 0
                ? ($checklist['completed_items'] / $checklist['total_items']) * 100
                : 0;
            
            return response()->json([
                'ok' => true,
                'module' => $module,
                'is_ready' => $isReady,
                'completion_rate' => round($completionRate, 2),
                'total_items' => $checklist['total_items'],
                'completed_items' => $checklist['completed_items'],
                'pending_items' => $checklist['pending_items'],
                'blocking_items' => $checklist['blocking_items'],
                'items' => $checklist['items'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to get module readiness',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }

    /**
     * Get readiness for all modules
     * 
     * @return JsonResponse
     */
    public function getAllReadiness(): JsonResponse
    {
        try {
            $modules = ['projects', 'tasks', 'documents', 'clients', 'quotes', 'team', 'dashboard'];
            $results = [];
            
            foreach ($modules as $module) {
                $checklist = $this->readinessService->getReadinessChecklist($module);
                $isReady = $this->readinessService->isModuleReady($module);
                
                $completionRate = $checklist['total_items'] > 0
                    ? ($checklist['completed_items'] / $checklist['total_items']) * 100
                    : 0;
                
                $results[$module] = [
                    'is_ready' => $isReady,
                    'completion_rate' => round($completionRate, 2),
                    'total_items' => $checklist['total_items'],
                    'completed_items' => $checklist['completed_items'],
                    'pending_items' => $checklist['pending_items'],
                    'blocking_items' => $checklist['blocking_items'],
                ];
            }
            
            return response()->json([
                'ok' => true,
                'modules' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to get module readiness',
                'traceId' => request()->header('X-Request-Id', uniqid('req_', true)),
            ], 500);
        }
    }
}

