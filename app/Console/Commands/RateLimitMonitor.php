<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class RateLimitMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rate-limit:monitor {--group=all : Specific group to monitor} {--clear : Clear rate limit data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and manage rate limiting data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('clear')) {
            return $this->clearRateLimitData();
        }

        $group = $this->option('group');
        
        if ($group === 'all') {
            $this->monitorAllGroups();
        } else {
            $this->monitorGroup($group);
        }
    }

    /**
     * Monitor all rate limiting groups
     */
    protected function monitorAllGroups()
    {
        $groups = ['public', 'app', 'admin', 'auth', 'invitations'];
        
        $this->info('Rate Limiting Status - All Groups');
        $this->line('=====================================');
        
        foreach ($groups as $group) {
            $this->monitorGroup($group);
            $this->line('');
        }
    }

    /**
     * Monitor specific group
     */
    protected function monitorGroup(string $group)
    {
        $config = config("rate-limiting.limits.{$group}");
        
        if (!$config) {
            $this->error("Group '{$group}' not found in configuration.");
            return;
        }

        $this->info("Rate Limiting Status - {$group} Group");
        $this->line('=====================================');
        
        // Get rate limit data
        $pattern = "rate_limit:{$group}:*";
        $keys = $this->getRateLimitKeys($pattern);
        
        if (empty($keys)) {
            $this->line("No active rate limiting data for {$group} group.");
            return;
        }

        $this->line("Active Keys: " . count($keys));
        $this->line("Limit: {$config['requests_per_minute']} requests/minute");
        $this->line("Burst Limit: {$config['burst_limit']} requests");
        $this->line("Ban Duration: {$config['ban_duration']} seconds");
        $this->line('');

        // Show top violators
        $violators = $this->getTopViolators($keys, $config);
        
        if (!empty($violators)) {
            $this->line('Top Violators:');
            $this->table(
                ['Key', 'Current Count', 'Burst Count', 'Status'],
                $violators
            );
        }

        // Show banned IPs/users
        $banned = $this->getBannedKeys($keys);
        
        if (!empty($banned)) {
            $this->line('');
            $this->warn('Currently Banned:');
            foreach ($banned as $banKey) {
                $this->line("  - {$banKey}");
            }
        }
    }

    /**
     * Get rate limit keys matching pattern
     */
    protected function getRateLimitKeys(string $pattern): array
    {
        try {
            if (config('cache.default') === 'redis') {
                return Redis::keys($pattern);
            } else {
                // For other cache drivers, we can't easily get all keys
                // This is a limitation of non-Redis cache drivers
                return [];
            }
        } catch (\Exception $e) {
            $this->error("Error retrieving keys: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top violators
     */
    protected function getTopViolators(array $keys, array $config): array
    {
        $violators = [];
        
        foreach ($keys as $key) {
            if (str_contains($key, ':banned')) {
                continue; // Skip ban keys
            }
            
            $currentCount = Cache::get($key, 0);
            $burstKey = "{$key}:burst";
            $burstCount = Cache::get($burstKey, 0);
            
            if ($currentCount > 0 || $burstCount > 0) {
                $status = 'Active';
                if (Cache::has("{$key}:banned")) {
                    $status = 'Banned';
                } elseif ($currentCount >= $config['requests_per_minute']) {
                    $status = 'Rate Limited';
                } elseif ($burstCount >= $config['burst_limit']) {
                    $status = 'Burst Limited';
                }
                
                $violators[] = [
                    $key,
                    $currentCount,
                    $burstCount,
                    $status
                ];
            }
        }
        
        // Sort by current count descending
        usort($violators, function ($a, $b) {
            return $b[1] <=> $a[1];
        });
        
        return array_slice($violators, 0, 10); // Top 10
    }

    /**
     * Get banned keys
     */
    protected function getBannedKeys(array $keys): array
    {
        $banned = [];
        
        foreach ($keys as $key) {
            if (str_contains($key, ':banned') && Cache::has($key)) {
                $banned[] = str_replace(':banned', '', $key);
            }
        }
        
        return $banned;
    }

    /**
     * Clear rate limit data
     */
    protected function clearRateLimitData()
    {
        $group = $this->option('group');
        
        if ($group === 'all') {
            $groups = ['public', 'app', 'admin', 'auth', 'invitations'];
        } else {
            $groups = [$group];
        }
        
        $cleared = 0;
        
        foreach ($groups as $groupName) {
            $pattern = "rate_limit:{$groupName}:*";
            $keys = $this->getRateLimitKeys($pattern);
            
            foreach ($keys as $key) {
                Cache::forget($key);
                $cleared++;
            }
        }
        
        $this->info("Cleared {$cleared} rate limiting entries for " . implode(', ', $groups) . " groups.");
    }
}
