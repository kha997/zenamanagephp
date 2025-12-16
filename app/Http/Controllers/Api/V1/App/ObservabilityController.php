<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Observability API Controller
 * 
 * Provides endpoints for metrics, traces, and observability data.
 */
class ObservabilityController extends BaseApiV1Controller
{
    public function __construct(
        private ObservabilityService $observabilityService
    ) {}

    /**
     * Get metrics summary
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $timeWindow = (int) $request->get('time_window', 5); // minutes
            
            $metrics = $this->observabilityService->getMetricsSummary($tenantId, $timeWindow);
            
            return $this->successResponse($metrics, 'Metrics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve metrics: ' . $e->getMessage(),
                500,
                null,
                'METRICS_RETRIEVE_FAILED'
            );
        }
    }

    /**
     * Get percentiles for a specific path
     */
    public function percentiles(Request $request): JsonResponse
    {
        try {
            $path = $request->get('path');
            $statusCode = (int) $request->get('status_code', 200);
            
            if (!$path) {
                return $this->errorResponse(
                    'Path parameter is required',
                    400,
                    null,
                    'MISSING_PATH_PARAMETER'
                );
            }
            
            $percentiles = $this->observabilityService->getPercentiles($path, $statusCode);
            
            return $this->successResponse($percentiles, 'Percentiles retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve percentiles: ' . $e->getMessage(),
                500,
                null,
                'PERCENTILES_RETRIEVE_FAILED'
            );
        }
    }

    /**
     * Get current trace context
     */
    public function traceContext(): JsonResponse
    {
        try {
            $context = $this->observabilityService->getCurrentTraceContext();
            
            return $this->successResponse($context, 'Trace context retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve trace context: ' . $e->getMessage(),
                500,
                null,
                'TRACE_CONTEXT_RETRIEVE_FAILED'
            );
        }
    }
}

