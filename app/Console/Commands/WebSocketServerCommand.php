<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\WebSocket\DashboardWebSocketHandler;

/**
 * WebSocket Server Command
 * 
 * Command để chạy WebSocket server cho Dashboard real-time updates
 */
class WebSocketServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:serve 
                            {--host=0.0.0.0 : Host to bind the server to}
                            {--port=8080 : Port to bind the server to}
                            {--workers=1 : Number of worker processes}';

    /**
     * The console command description.
     */
    protected $description = 'Start WebSocket server for Dashboard real-time updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $workers = $this->option('workers');

        $this->info("Starting WebSocket server on {$host}:{$port}");
        $this->info("Workers: {$workers}");
        $this->info("Press Ctrl+C to stop the server");

        try {
            // Tạo WebSocket handler
            $webSocketHandler = new DashboardWebSocketHandler();
            
            // Tạo server với HTTP và WebSocket support
            $server = IoServer::factory(
                new HttpServer(
                    new WsServer($webSocketHandler)
                ),
                $port,
                $host
            );

            // Setup periodic tasks
            $this->setupPeriodicTasks($server, $webSocketHandler);

            // Start server
            $server->run();

        } catch (\Exception $e) {
            $this->error("Failed to start WebSocket server: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Setup periodic tasks cho server
     */
    private function setupPeriodicTasks(IoServer $server, DashboardWebSocketHandler $handler)
    {
        // Ping clients mỗi 30 giây
        $server->loop->addPeriodicTimer(30, function () use ($handler) {
            $handler->broadcast([
                'type' => 'ping',
                'timestamp' => now()->toISOString()
            ]);
        });

        // Cleanup inactive connections mỗi 5 phút
        $server->loop->addPeriodicTimer(300, function () use ($handler) {
            $stats = $handler->getStats();
            \Log::info('WebSocket server stats', $stats);
        });

        // Broadcast system metrics mỗi phút
        $server->loop->addPeriodicTimer(60, function () use ($handler) {
            $this->broadcastSystemMetrics($handler);
        });
    }

    /**
     * Broadcast system metrics
     */
    private function broadcastSystemMetrics(DashboardWebSocketHandler $handler)
    {
        $metrics = [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'active_connections' => $handler->getStats()['total_connections']
        ];

        $handler->broadcast([
            'type' => 'system_metrics',
            'metrics' => $metrics,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get CPU usage
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        }
        return 0;
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): float
    {
        $memory = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        return round(($memory / $memoryLimitBytes) * 100, 2);
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): float
    {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedSpace = $totalSpace - $freeSpace;
        
        return round(($usedSpace / $totalSpace) * 100, 2);
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }

        return $memoryLimit;
    }
}
