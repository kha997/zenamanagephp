<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Cache, Queue};

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';
    protected $description = 'Check system health and performance';

    public function handle(): int
    {
        $checks = [
            'Database' => $this->checkDatabase(),
            'Cache' => $this->checkCache(),
            'Queue' => $this->checkQueue(),
            'Storage' => $this->checkStorage(),
        ];

        foreach ($checks as $service => $status) {
            $this->line("$service: " . ($status ? '✅ OK' : '❌ FAILED'));
        }

        return array_reduce($checks, fn($carry, $status) => $carry && $status, true) ? 0 : 1;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}