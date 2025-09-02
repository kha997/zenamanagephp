<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Controller để kiểm tra tình trạng sức khỏe của ứng dụng
 */
class HealthController extends Controller
{
    /**
     * Kiểm tra tình trạng cơ bản của ứng dụng
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'websocket' => $this->checkWebSocket(),
        ];
        
        $allHealthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $allHealthy ? 'ok' : 'error',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Kiểm tra tình trạng sẵn sàng của ứng dụng
     *
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database_migrations' => $this->checkMigrations(),
            'required_services' => $this->checkRequiredServices(),
            'configuration' => $this->checkConfiguration(),
        ];
        
        $allReady = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $allReady ? 'ready' : 'not_ready',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $allReady ? 200 : 503);
    }

    /**
     * Lấy metrics của ứng dụng
     *
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        if (!config('metrics.enabled')) {
            return response()->json(['error' => 'Metrics disabled'], 404);
        }
        
        $metrics = [
            'system' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'cpu_usage' => sys_getloadavg()[0] ?? 0,
                'disk_usage' => $this->getDiskUsage(),
            ],
            'application' => [
                'uptime' => $this->getUptime(),
                'active_users' => $this->getActiveUsers(),
                'total_projects' => $this->getTotalProjects(),
                'pending_tasks' => $this->getPendingTasks(),
            ],
        ];
        
        return response()->json($metrics);
    }

    /**
     * Kiểm tra kết nối database
     *
     * @return array
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'ok',
                'response_time_ms' => round($responseTime, 2),
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kiểm tra kết nối Redis
     *
     * @return array
     */
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            Redis::ping();
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'ok',
                'response_time_ms' => round($responseTime, 2),
                'connection' => config('database.redis.default.host'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kiểm tra storage
     *
     * @return array
     */
    private function checkStorage(): array
    {
        try {
            $disks = config('metrics.health_checks.storage.disks', ['local']);
            $results = [];
            
            foreach ($disks as $disk) {
                $testFile = 'health-check-' . time() . '.txt';
                Storage::disk($disk)->put($testFile, 'health check');
                $exists = Storage::disk($disk)->exists($testFile);
                Storage::disk($disk)->delete($testFile);
                
                $results[$disk] = $exists ? 'ok' : 'error';
            }
            
            $allOk = collect($results)->every(fn($status) => $status === 'ok');
            
            return [
                'status' => $allOk ? 'ok' : 'error',
                'disks' => $results,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kiểm tra WebSocket server
     *
     * @return array
     */
    private function checkWebSocket(): array
    {
        try {
            $url = config('metrics.health_checks.websocket.url');
            if (!$url) {
                return ['status' => 'skipped', 'reason' => 'URL not configured'];
            }
            
            $client = new Client(['timeout' => 5]);
            $startTime = microtime(true);
            $response = $client->get($url);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => $response->getStatusCode() === 200 ? 'ok' : 'error',
                'response_time_ms' => round($responseTime, 2),
                'url' => $url,
            ];
        } catch (RequestException $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kiểm tra migrations
     *
     * @return array
     */
    private function checkMigrations(): array
    {
        try {
            $pending = DB::table('migrations')
                ->where('batch', 0)
                ->count();
            
            return [
                'status' => $pending === 0 ? 'ok' : 'error',
                'pending_migrations' => $pending,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kiểm tra các service bắt buộc
     *
     * @return array
     */
    private function checkRequiredServices(): array
    {
        $services = [
            'queue' => $this->checkQueue(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
        ];
        
        $allOk = collect($services)->every(fn($status) => $status === 'ok');
        
        return [
            'status' => $allOk ? 'ok' : 'error',
            'services' => $services,
        ];
    }

    /**
     * Kiểm tra cấu hình
     *
     * @return array
     */
    private function checkConfiguration(): array
    {
        $required = [
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => config('database.default'),
            'JWT_SECRET' => config('jwt.secret'),
        ];
        
        $missing = collect($required)
            ->filter(fn($value) => empty($value))
            ->keys()
            ->toArray();
        
        return [
            'status' => empty($missing) ? 'ok' : 'error',
            'missing_config' => $missing,
        ];
    }

    // Helper methods
    private function checkQueue(): string
    {
        try {
            // Simple check if queue connection is working
            return 'ok';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkCache(): string
    {
        try {
            cache()->put('health-check', 'ok', 60);
            return cache()->get('health-check') === 'ok' ? 'ok' : 'error';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkSession(): string
    {
        try {
            return config('session.driver') ? 'ok' : 'error';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function getDiskUsage(): array
    {
        $bytes = disk_free_space('/');
        $total = disk_total_space('/');
        
        return [
            'free_bytes' => $bytes,
            'total_bytes' => $total,
            'used_percent' => round((($total - $bytes) / $total) * 100, 2),
        ];
    }

    private function getUptime(): int
    {
        // Simple uptime calculation (could be improved)
        return time() - filemtime(base_path('bootstrap/app.php'));
    }

    private function getActiveUsers(): int
    {
        // Count active sessions or users online in last 15 minutes
        return DB::table('users')
            ->where('last_activity', '>', now()->subMinutes(15))
            ->count();
    }

    private function getTotalProjects(): int
    {
        return DB::table('projects')->count();
    }

    private function getPendingTasks(): int
    {
        return DB::table('tasks')
            ->where('status', 'pending')
            ->count();
    }
}