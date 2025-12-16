<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Services\CacheKeyService;

/**
 * Cleanup Orphaned Cache Keys
 * 
 * Removes cache keys that reference non-existent entities.
 * Runs daily to prevent cache bloat.
 */
class CleanupOrphanedCacheKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cleanup-orphaned 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--tenant= : Cleanup for specific tenant only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup orphaned cache keys that reference non-existent entities';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');
        
        $this->info('Starting orphaned cache key cleanup...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No keys will be deleted');
        }

        $env = app()->environment();
        $pattern = $tenantId 
            ? "{$env}:{$tenantId}:*" 
            : "{$env}:*";

        try {
            $keys = Redis::keys($pattern);
            $orphanedCount = 0;
            $checkedCount = 0;

            $this->info("Found " . count($keys) . " cache keys to check");

            foreach ($keys as $key) {
                $checkedCount++;
                
                if ($this->isOrphaned($key)) {
                    $orphanedCount++;
                    
                    if ($dryRun) {
                        $this->line("Would delete: {$key}");
                    } else {
                        Redis::del($key);
                    }
                }

                // Progress indicator
                if ($checkedCount % 100 === 0) {
                    $this->info("Checked {$checkedCount} keys, found {$orphanedCount} orphaned");
                }
            }

            $this->info("Cleanup complete!");
            $this->info("Checked: {$checkedCount} keys");
            $this->info("Orphaned: {$orphanedCount} keys");
            
            if (!$dryRun && $orphanedCount > 0) {
                $this->info("Deleted {$orphanedCount} orphaned cache keys");
            }

            Log::info('Orphaned cache keys cleanup completed', [
                'checked' => $checkedCount,
                'orphaned' => $orphanedCount,
                'tenant_id' => $tenantId,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            Log::error('Orphaned cache keys cleanup failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Check if a cache key is orphaned (references non-existent entity)
     * 
     * @param string $key Cache key
     * @return bool True if orphaned
     */
    private function isOrphaned(string $key): bool
    {
        // Parse key format: {env}:{tenant}:{domain}:{entity}:{id}:{view}
        $parts = explode(':', $key);
        
        if (count($parts) < 5) {
            return false; // Not a standard entity key
        }

        $entity = $parts[3] ?? null;
        $entityId = $parts[4] ?? null;

        if (!$entity || !$entityId) {
            return false;
        }

        // Check if entity exists in database
        return $this->entityExists($entity, $entityId);
    }

    /**
     * Check if entity exists in database
     * 
     * @param string $entity Entity type (e.g., 'task', 'project')
     * @param string $entityId Entity ID
     * @return bool True if entity does NOT exist (orphaned)
     */
    private function entityExists(string $entity, string $entityId): bool
    {
        try {
            $modelClass = $this->getModelClass($entity);
            if (!$modelClass) {
                return false; // Unknown entity, don't delete
            }

            $exists = $modelClass::where('id', $entityId)->exists();
            return !$exists; // Return true if NOT exists (orphaned)
        } catch (\Exception $e) {
            Log::warning('Error checking entity existence', [
                'entity' => $entity,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            return false; // On error, don't delete
        }
    }

    /**
     * Get model class for entity type
     * 
     * @param string $entity Entity type
     * @return string|null Model class name
     */
    private function getModelClass(string $entity): ?string
    {
        $modelMap = [
            'task' => \App\Models\Task::class,
            'tasks' => \App\Models\Task::class,
            'project' => \App\Models\Project::class,
            'projects' => \App\Models\Project::class,
            'document' => \App\Models\Document::class,
            'documents' => \App\Models\Document::class,
            'user' => \App\Models\User::class,
            'users' => \App\Models\User::class,
        ];

        return $modelMap[$entity] ?? null;
    }
}

