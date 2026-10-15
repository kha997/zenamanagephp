<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QueryOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Command để tối ưu hóa database.
 */
class OptimizeDatabaseCommand extends Command
{
    protected $signature = 'db:optimize {--analyze : Analyze slow queries}';

    protected $description = 'Optimize database performance';

    public function __construct(
        private QueryOptimizationService $queryOptimizationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🚀 Starting database optimization...');

        // 1. Run migrations for new indexes
        $this->info('📊 Adding performance indexes...');
        Artisan::call('migrate', ['--force' => true]);
        $this->info('✅ Indexes added successfully');

        // 2. Analyze tables
        $this->info('🔍 Analyzing tables...');
        $this->analyzeTables();

        // 3. Update table statistics
        $this->info('📈 Updating table statistics...');
        $this->updateTableStatistics();

        // 4. Analyze slow queries if requested
        if ($this->option('analyze')) {
            $this->info('🐌 Analyzing slow queries...');
            $analysis = $this->queryOptimizationService->analyzeSlowQueries();
            $this->table(
                ['Metric', 'Value'],
                [['Slow Queries Count', $analysis['slow_queries_count']]]
            );

            $this->info('💡 Recommendations:');
            foreach ($analysis['recommendations'] as $recommendation) {
                $this->line("  - {$recommendation}");
            }
        }

        // 5. Clear query cache
        $this->info('🗑️ Clearing query cache...');
        $this->queryOptimizationService->clearProjectCaches();

        $this->info('✅ Database optimization completed!');

        return 0;
    }

    private function analyzeTables(): void
    {
        $tables = [
            'projects', 'tasks', 'components', 'interaction_logs',
            'documents', 'document_versions', 'change_requests',
            'notifications', 'users',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("ANALYZE TABLE {$table}");
                $this->line("  ✅ Analyzed table: {$table}");
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to analyze table {$table}: {$e->getMessage()}");
            }
        }
    }

    private function updateTableStatistics(): void
    {
        try {
            DB::statement('FLUSH TABLES');
            $this->line('  ✅ Table statistics updated');
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to update statistics: {$e->getMessage()}");
        }
    }
}
