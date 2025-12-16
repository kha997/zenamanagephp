<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Observability API Controller
 * 
 * PR: Observability 3-in-1
 * 
 * Provides API endpoints for querying observability data (logs, metrics, traces).
 */
class ObservabilityController extends Controller
{
    protected ObservabilityService $observabilityService;

    public function __construct(ObservabilityService $observabilityService)
    {
        $this->observabilityService = $observabilityService;
    }

    /**
     * Get observability summary (3-in-1: logs, metrics, traces)
     * 
     * GET /api/v1/observability/summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
        $timeWindow = (int) $request->query('time_window', 5); // minutes

        $summary = $this->observabilityService->getMetricsSummary($tenantId, $timeWindow);

        // Add trace context
        $traceContext = $this->observabilityService->getCurrentTraceContext();

        // Add log context (recent logs with request_id)
        $logContext = $this->getRecentLogs($tenantId, $timeWindow);

        return response()->json([
            'ok' => true,
            'data' => [
                'metrics' => $summary,
                'traces' => $traceContext,
                'logs' => $logContext,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get metrics by request_id
     * 
     * GET /api/v1/observability/request/{requestId}
     */
    public function getByRequestId(string $requestId, Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;

        // Get metrics for this request_id
        $metrics = $this->getMetricsByRequestId($requestId, $tenantId);

        // Get logs for this request_id
        $logs = $this->getLogsByRequestId($requestId, $tenantId);

        // Get trace for this request_id
        $trace = $this->getTraceByRequestId($requestId, $tenantId);

        return response()->json([
            'ok' => true,
            'data' => [
                'request_id' => $requestId,
                'metrics' => $metrics,
                'logs' => $logs,
                'trace' => $trace,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get observability dashboard data
     * 
     * GET /api/v1/observability/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
        $timeWindow = (int) $request->query('time_window', 60); // minutes

        $summary = $this->observabilityService->getMetricsSummary($tenantId, $timeWindow);

        // Get recent violations
        $violations = $this->getRecentViolations($tenantId, $timeWindow);

        // Get top slow requests
        $slowRequests = $this->getSlowRequests($tenantId, $timeWindow);

        // Get error summary
        $errorSummary = $this->getErrorSummary($tenantId, $timeWindow);

        return response()->json([
            'ok' => true,
            'data' => [
                'metrics' => $summary,
                'violations' => $violations,
                'slow_requests' => $slowRequests,
                'errors' => $errorSummary,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get recent logs
     */
    private function getRecentLogs(?string $tenantId, int $timeWindowMinutes): array
    {
        // In production, this would query log storage (ELK, CloudWatch, etc.)
        // For now, return structure
        return [
            'count' => 0,
            'logs' => [],
            'note' => 'Log querying requires log aggregation system (ELK, CloudWatch, etc.)',
        ];
    }

    /**
     * Get metrics by request_id
     */
    private function getMetricsByRequestId(string $requestId, ?string $tenantId): array
    {
        // In production, this would query metrics storage (Prometheus, etc.)
        // For now, return structure
        return [
            'request_id' => $requestId,
            'http_requests' => [],
            'database_queries' => [],
            'errors' => [],
            'note' => 'Metrics querying requires metrics storage (Prometheus, etc.)',
        ];
    }

    /**
     * Get logs by request_id
     */
    private function getLogsByRequestId(string $requestId, ?string $tenantId): array
    {
        // In production, this would query log storage filtered by request_id
        // For now, return structure
        return [
            'request_id' => $requestId,
            'logs' => [],
            'note' => 'Log querying requires log aggregation system',
        ];
    }

    /**
     * Get trace by request_id
     */
    private function getTraceByRequestId(string $requestId, ?string $tenantId): array
    {
        // In production, this would query trace storage (Jaeger, Zipkin, etc.)
        // For now, return structure
        return [
            'request_id' => $requestId,
            'trace_id' => $requestId,
            'spans' => [],
            'note' => 'Trace querying requires APM system (Jaeger, Zipkin, etc.)',
        ];
    }

    /**
     * Get recent violations
     */
    private function getRecentViolations(?string $tenantId, int $timeWindowMinutes): array
    {
        // Get from cache (stored by SLOAlertingService)
        $violations = \Illuminate\Support\Facades\Cache::get('slo_alerts', []);
        
        // Filter by tenant if provided
        if ($tenantId) {
            $violations = array_filter($violations, function ($v) use ($tenantId) {
                return isset($v['tenant_id']) && $v['tenant_id'] === $tenantId;
            });
        }

        // Filter by time window
        $cutoff = now()->subMinutes($timeWindowMinutes);
        $violations = array_filter($violations, function ($v) use ($cutoff) {
            $timestamp = \Carbon\Carbon::parse($v['timestamp'] ?? now());
            return $timestamp->isAfter($cutoff);
        });

        return array_values(array_slice($violations, -20)); // Last 20
    }

    /**
     * Get slow requests
     */
    private function getSlowRequests(?string $tenantId, int $timeWindowMinutes): array
    {
        // In production, this would query metrics storage
        // For now, return structure
        return [
            'count' => 0,
            'requests' => [],
            'note' => 'Slow requests querying requires metrics storage',
        ];
    }

    /**
     * Get error summary
     */
    private function getErrorSummary(?string $tenantId, int $timeWindowMinutes): array
    {
        // In production, this would query error tracking (Sentry, etc.)
        // For now, return structure
        return [
            'total' => 0,
            'by_type' => [],
            'by_route' => [],
            'note' => 'Error summary requires error tracking system',
        ];
    }
}

