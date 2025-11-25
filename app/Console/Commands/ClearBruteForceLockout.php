<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearBruteForceLockout extends Command
{
    protected $signature = 'auth:clear-lockout {email?} {--ip=}';
    protected $description = 'Clear brute force protection lockout for user or IP';

    public function handle()
    {
        $email = $this->argument('email');
        $ip = $this->option('ip') ?: request()->ip() ?: '127.0.0.1';

        $this->info('Clearing brute force protection lockout...');
        $this->info("IP: {$ip}");

        if ($email) {
            $this->clearUserLockout($email, $ip);
        } else {
            $this->clearAllLockouts($ip);
        }

        $this->info('✅ Lockout cleared!');
        return 0;
    }

    private function clearUserLockout(string $email, string $ip): void
    {
        // Clear account lockout
        Cache::forget("brute_force:account:{$email}");
        Cache::forget("auth_lockout:{$email}");
        
        // Clear attempt counts
        Cache::forget("brute_force:attempts:{$email}:{$ip}");
        Cache::forget("user_attempts:{$email}");
        Cache::forget("auth_attempts:{$ip}");
        
        $this->info("✅ Cleared lockout for: {$email}");
    }

    private function clearAllLockouts(string $ip): void
    {
        // Clear IP-based blocks
        Cache::forget("brute_force:ip:{$ip}");
        Cache::forget("auth_attempts:{$ip}");
        
        // Clear common test accounts
        $testEmails = [
            'superadmin@zena.com',
            'admin@zena.com',
            'pm@zena.com',
            'designer@zena.com',
        ];
        
        foreach ($testEmails as $email) {
            Cache::forget("brute_force:account:{$email}");
            Cache::forget("auth_lockout:{$email}");
            Cache::forget("brute_force:attempts:{$email}:{$ip}");
            Cache::forget("user_attempts:{$email}");
        }
        
        $this->info("✅ Cleared all lockouts for IP: {$ip}");
    }
}

