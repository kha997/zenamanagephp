<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OutboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Outbox Worker Command
 * 
 * Continuously processes pending events from the outbox table.
 * Runs in a loop, processing events every N seconds.
 * 
 * Usage: php artisan outbox:worker --interval=5 --max-iterations=1000
 */
class OutboxWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:worker 
                            {--interval=5 : Seconds to wait between processing cycles}
                            {--limit=100 : Number of events to process per cycle}
                            {--max-iterations=0 : Maximum number of iterations (0 = infinite)}
                            {--stop-on-empty : Stop when no events to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously process pending events from outbox table (worker mode)';

    /**
     * Execute the console command.
     */
    public function handle(OutboxService $outboxService): int
    {
        $interval = (int) $this->option('interval');
        $limit = (int) $this->option('limit');
        $maxIterations = (int) $this->option('max-iterations');
        $stopOnEmpty = $this->option('stop-on-empty');
        
        $this->info("Starting outbox worker...");
        $this->info("Interval: {$interval}s, Limit: {$limit}, Max iterations: " . ($maxIterations > 0 ? $maxIterations : 'infinite'));
        
        $iteration = 0;
        $totalProcessed = 0;
        
        while (true) {
            $iteration++;
            
            // Check max iterations
            if ($maxIterations > 0 && $iteration > $maxIterations) {
                $this->info("Reached max iterations ({$maxIterations}), stopping.");
                break;
            }
            
            try {
                $processed = $outboxService->processPendingEvents($limit);
                $totalProcessed += $processed;
                
                if ($processed > 0) {
                    $this->line("Iteration {$iteration}: Processed {$processed} events (Total: {$totalProcessed})");
                } else {
                    if ($stopOnEmpty) {
                        $this->info("No pending events, stopping.");
                        break;
                    }
                    // Silent when no events (don't spam logs)
                }
                
                // Also process failed retryable events
                $retryable = $outboxService->retryFailedEvents($limit);
                if ($retryable > 0) {
                    $this->line("Retried {$retryable} failed events");
                }
                
            } catch (\Exception $e) {
                Log::error('Outbox worker error', [
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $this->error("Error in iteration {$iteration}: " . $e->getMessage());
            }
            
            // Sleep before next iteration
            if ($interval > 0) {
                sleep($interval);
            }
        }
        
        $this->info("Outbox worker stopped. Total processed: {$totalProcessed}");
        
        return Command::SUCCESS;
    }
}

