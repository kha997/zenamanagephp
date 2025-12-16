<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TaskReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SendTaskDueRemindersCommand - Round 254: Task Due-Date Reminder Notifications
 * 
 * Console command to send due soon and overdue reminders for project tasks.
 * 
 * This command:
 * - Iterates through all active tenants
 * - Sends reminders for tasks due tomorrow (due_soon)
 * - Sends reminders for overdue tasks (overdue)
 * - Prevents duplicate notifications
 * 
 * Usage:
 *   php artisan tasks:send-due-reminders
 *   php artisan tasks:send-due-reminders --tenant=tenant-id
 */
class SendTaskDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send-due-reminders
                            {--tenant= : Limit to a specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send due soon and overdue reminders for project tasks';

    /**
     * Execute the console command.
     */
    public function handle(TaskReminderService $taskReminderService): int
    {
        $tenantId = $this->option('tenant');

        $this->info('Starting task due reminder process...');

        // Determine which tenants to process
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found");
                return Command::FAILURE;
            }
            if (!$tenant->isActive()) {
                $this->warn("Tenant {$tenantId} is not active (status: {$tenant->status})");
            }
            $tenants = collect([$tenant]);
        } else {
            // Get all active tenants
            $tenants = Tenant::where('status', 'active')->get();
            $this->info("Found {$tenants->count()} active tenant(s)");
        }

        $totalProcessed = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name} ({$tenant->id})");

            try {
                $taskReminderService->sendDueRemindersForTenant((string) $tenant->id);
                $totalProcessed++;
                $this->info("  ✓ Successfully processed tenant {$tenant->id}");
            } catch (\Exception $e) {
                $totalErrors++;
                $this->error("  ✗ Failed to process tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('SendTaskDueRemindersCommand: Failed to process tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue with other tenants
            }
        }

        $this->newLine();
        if ($totalErrors > 0) {
            $this->warn("Processed {$totalProcessed} tenant(s) with {$totalErrors} error(s)");
        } else {
            $this->info("Successfully processed {$totalProcessed} tenant(s)");
        }

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
