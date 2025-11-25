<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OutboxService;
use Illuminate\Console\Command;

/**
 * Process Outbox Command
 * 
 * Processes pending events from the outbox table.
 * Should be run periodically (e.g., via cron) to ensure events are published.
 */
class ProcessOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:process {--limit=100 : Number of events to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending events from outbox table';

    /**
     * Execute the console command.
     */
    public function handle(OutboxService $outboxService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Processing up to {$limit} pending outbox events...");
        
        $processed = $outboxService->processPendingEvents($limit);
        
        if ($processed > 0) {
            $this->info("Processed {$processed} events successfully.");
        } else {
            $this->info("No pending events to process.");
        }
        
        return Command::SUCCESS;
    }
}
