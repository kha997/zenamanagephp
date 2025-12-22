<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\MonitoringService;

class LogPerformanceMetrics
{
    public function __construct(
        private MonitoringService $monitoringService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Log metrics
        $this->logMetrics($request, $response, $responseTime);

        return $response;
    }

    /**
     * Log performance metrics
     */
    private function logMetrics(Request $request, $response, float $responseTime): void
    {
        try {
            $isApi = $request->is('api/*');
            $statusCode = $response->getStatusCode();

            if ($isApi) {
                // Log API metrics
                $this->monitoringService->logApiRequest(
                    $request->method(),
                    $request->path(),
                    $responseTime,
                    $statusCode
                );
            } else {
                // Log page load metrics
                $this->monitoringService->logPageLoad(
                    $request->route()?->getName() ?? $request->path(),
                    $responseTime,
                    $statusCode
                );
            }

            // Log structured metrics
            $this->logStructuredMetrics($request, $response, $responseTime);

        } catch (\Exception $e) {
            Log::error('Failed to log performance metrics', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }
    }

    /**
     * Log structured metrics for monitoring
     */
    private function logStructuredMetrics(Request $request, $response, float $responseTime): void
    {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'response_time_ms' => round($responseTime, 2),
                'status_code' => $response->getStatusCode(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'is_api' => $request->is('api/*'),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ];

            // Add additional context for API requests
            if ($request->is('api/*')) {
                $logData['api_version'] = $this->extractApiVersion($request->path());
                $logData['content_type'] = $request->header('Content-Type');
                $logData['accept'] = $request->header('Accept');
            }

            // Log to monitoring channel
            Log::info('Performance Metrics', $logData);

            // Log slow requests (> 500ms)
            if ($responseTime > 500) {
                Log::warning('Slow Request Detected', $logData);
            }

            // Log errors (4xx, 5xx)
            if ($response->getStatusCode() >= 400) {
                Log::error('Error Response', $logData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log structured metrics', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);
        }
    }

    /**
     * Extract API version from path
     */
    private function extractApiVersion(string $path): ?string
    {
        if (preg_match('/\/api\/v(\d+)\//', $path, $matches)) {
            return 'v' . $matches[1];
        }

        return null;
    }
}
