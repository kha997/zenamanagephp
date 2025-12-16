<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Unlock User Account Command
 * 
 * Allows admin to unlock a user account that has been locked due to failed login attempts.
 */
class UnlockUserAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:unlock 
                            {email : The email address of the user to unlock}
                            {--tenant= : Optional tenant ID to scope the search}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock a user account that has been locked due to failed login attempts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $tenantId = $this->option('tenant');
        
        // Find user
        $query = User::where('email', $email);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $user = $query->first();
        
        if (!$user) {
            $this->error("User not found with email: {$email}" . ($tenantId ? " in tenant: {$tenantId}" : ""));
            return Command::FAILURE;
        }
        
        // Check if account is actually locked
        if (!$user->isLocked() && $user->failed_login_attempts === 0) {
            $this->info("Account for {$email} is not locked.");
            return Command::SUCCESS;
        }
        
        // Unlock the account
        $user->unlockAccount();
        
        $this->info("Successfully unlocked account for: {$email}");
        $this->info("User ID: {$user->id}");
        $this->info("Tenant ID: {$user->tenant_id}");
        
        // Log the unlock action
        Log::info('User account unlocked via command', [
            'user_id' => $user->id,
            'email' => $email,
            'tenant_id' => $user->tenant_id,
            'unlocked_by' => 'console_command',
        ]);
        
        return Command::SUCCESS;
    }
}
