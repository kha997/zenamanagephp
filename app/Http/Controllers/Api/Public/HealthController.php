<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Public liveness check endpoint
     * No authentication required, throttled for public access
     */
    public function liveness(): JsonResponse
    {
        try {
            // Basic system checks
            $checks = [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'timestamp' => now()->toISOString()
            ];

            $allHealthy = collect($checks)->except('timestamp')->every(function ($check) {
                return $check === true;
            });

            $status = $allHealthy ? 'healthy' : 'unhealthy';
            $httpStatus = $allHealthy ? 200 : 503;

            return response()->json([
                'status' => $status,
                'service' => 'ZenaManage',
                'version' => '1.0.0',
                'checks' => $checks,
                'uptime' => $this->getUptime()
            ], $httpStatus);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'service' => 'ZenaManage',
                'version' => '1.0.0',
                'error' => 'System check failed',
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check cache system
     */
    private function checkCache(): bool
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            return $value === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check storage accessibility
     */
    private function checkStorage(): bool
    {
        try {
            $path = storage_path('app');
            return is_dir($path) && is_writable($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get system uptime
     */
    private function getUptime(): string
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return "Load: " . implode(', ', array_map(function($l) {
                    return number_format($l, 2);
                }, $load));
            }
            return 'Uptime: ' . date('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return 'Uptime: Unknown';
        }
    }
}
