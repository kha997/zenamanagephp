<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Observability Controller
 * 
 * Exposes observability metrics and performance data for admin dashboard.
 * Part of Gói 10: Observability End-to-End (OpenTelemetry).
 */
class ObservabilityController extends Controller
{
    protected ObservabilityService $observabilityService;

    public function __construct(ObservabilityService $observabilityService)
    {
        $this->observabilityService = $observabilityService;
    }

    /**
     * Get performance metrics summary
     * 
     * Returns p50, p95, p99 latencies for API endpoints.
     */
    public function metrics(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;
        $timeWindow = (int) $request->query('time_window', 5); // minutes

        $summary = $this->observabilityService->getMetricsSummary($tenantId, $timeWindow);

        return response()->json([
            'ok' => true,
            'data' => $summary,
            'time_window_minutes' => $timeWindow,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get performance budgets
     * 
     * Returns configured performance budgets from config/performance-budgets.json.
     */
    public function budgets(): JsonResponse
    {
        $budgetsPath = config_path('performance-budgets.json');
        
        if (!File::exists($budgetsPath)) {
            return response()->json([
                'ok' => false,
                'code' => 'BUDGETS_NOT_FOUND',
                'message' => 'Performance budgets configuration not found',
            ], 404);
        }

        $budgets = json_decode(File::get($budgetsPath), true);

        return response()->json([
            'ok' => true,
            'data' => $budgets,
        ]);
    }

    /**
     * Get percentiles for a specific route
     * 
     * Returns p50, p95, p99 latencies for a given route path.
     */
    public function percentiles(Request $request): JsonResponse
    {
        $path = $request->query('path');
        $statusCode = (int) $request->query('status_code', 200);

        if (!$path) {
            return response()->json([
                'ok' => false,
                'code' => 'PATH_REQUIRED',
                'message' => 'Path parameter is required',
            ], 400);
        }

        $percentiles = $this->observabilityService->getPercentiles($path, $statusCode);

        // Get budget for this path
        $budgetsPath = config_path('performance-budgets.json');
        $budget = null;
        if (File::exists($budgetsPath)) {
            $budgets = json_decode(File::get($budgetsPath), true);
            $budget = $budgets['budgets'][$path] ?? $budgets['default'] ?? null;
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'path' => $path,
                'status_code' => $statusCode,
                'percentiles' => $percentiles,
                'budget' => $budget,
                'within_budget' => $budget ? ($percentiles['p95'] <= $budget['p95']) : null,
            ],
        ]);
    }

    /**
     * Get trace context for current request
     */
    public function traceContext(Request $request): JsonResponse
    {
        $traceContext = $this->observabilityService->getCurrentTraceContext();

        return response()->json([
            'ok' => true,
            'data' => $traceContext,
        ]);
    }
}

