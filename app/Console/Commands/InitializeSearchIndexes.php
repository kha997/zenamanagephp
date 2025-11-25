<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InitializeSearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:init 
                            {--model= : Specific model to index (Project, Task, Document)}
                            {--force : Force re-indexing even if index exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize search indexes for all searchable models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('scout.driver');
        
        if ($driver !== 'meilisearch') {
            $this->error('❌ Meilisearch not configured. Set SCOUT_DRIVER=meilisearch in .env');
            return 1;
        }

        $this->info('🔍 Initializing search indexes...');
        $this->newLine();

        $models = $this->option('model') 
            ? [ucfirst($this->option('model'))] 
            : ['Project', 'Task', 'Document'];

        foreach ($models as $modelName) {
            $modelClass = "App\\Models\\{$modelName}";
            
            if (!class_exists($modelClass)) {
                $this->warn("⚠️  Model {$modelClass} not found. Skipping...");
                continue;
            }

            $this->info("📦 Indexing {$modelName}...");
            
            try {
                if ($this->option('force')) {
                    // Flush existing index
                    Artisan::call('scout:flush', ['model' => $modelClass]);
                    $this->line("   Flushed existing index");
                }

                // Import models
                Artisan::call('scout:import', ['model' => $modelClass]);
                
                $count = $modelClass::count();
                $this->info("   ✅ {$modelName}: {$count} records indexed");
            } catch (\Exception $e) {
                $this->error("   ❌ {$modelName} indexing failed: " . $e->getMessage());
                return 1;
            }
        }

        $this->newLine();
        $this->info('✅ Search indexes initialized successfully!');

        return 0;
    }
}

