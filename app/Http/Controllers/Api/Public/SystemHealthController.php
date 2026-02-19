<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthController extends Controller
{
    public function detailed(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase() ? 'ok' : 'unhealthy',
            'cache' => $this->checkCache() ? 'ok' : 'unhealthy',
            'queue' => 'ok',
        ];

        $overallStatus = in_array('unhealthy', $services, true) ? 'unhealthy' : 'healthy';

        return response()->json([
            'overall_status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'services' => $services,
            'metrics' => [
                'memory_usage_bytes' => memory_get_usage(true),
                'memory_peak_usage_bytes' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
            ],
            'alerts' => [],
            'recommendations' => [],
        ]);
    }

    public function performance(): JsonResponse
    {
        $loadAvg = null;

        if (function_exists('sys_getloadavg')) {
            try {
                $loadAvg = sys_getloadavg();
            } catch (Throwable) {
                $loadAvg = null;
            }
        }

        return response()->json([
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'cpu' => [
                'load_avg' => $loadAvg,
            ],
            'php' => [
                'version' => PHP_VERSION,
            ],
            'application' => [
                'environment' => app()->environment(),
                'laravel_version' => app()->version(),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $key = '_health_check';
            Cache::put($key, 'ok', now()->addSeconds(10));

            return Cache::get($key) === 'ok';
        } catch (Throwable) {
            return false;
        }
    }
}
