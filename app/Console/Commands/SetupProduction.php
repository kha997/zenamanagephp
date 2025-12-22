<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:production 
                            {--skip-migrations : Skip database migrations}
                            {--skip-indexing : Skip search index initialization}
                            {--skip-frontend : Skip frontend type generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup production environment: run migrations, initialize search indexes, generate frontend types';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting production setup...');
        $this->newLine();

        // Step 1: Database migrations
        if (!$this->option('skip-migrations')) {
            $this->info('📦 Step 1: Running database migrations...');
            try {
                Artisan::call('migrate', ['--force' => true]);
                $this->info('✅ Migrations completed');
            } catch (\Exception $e) {
                $this->error('❌ Migration failed: ' . $e->getMessage());
                return 1;
            }
            $this->newLine();
        }

        // Step 2: Initialize search indexes
        if (!$this->option('skip-indexing')) {
            $this->info('🔍 Step 2: Initializing search indexes...');
            
            if (config('scout.driver') === 'meilisearch') {
                $this->info('   Importing Projects...');
                try {
                    Artisan::call('scout:import', ['model' => 'App\Models\Project']);
                    $this->info('   ✅ Projects indexed');
                } catch (\Exception $e) {
                    $this->warn('   ⚠️  Projects indexing failed: ' . $e->getMessage());
                }

                $this->info('   Importing Tasks...');
                try {
                    Artisan::call('scout:import', ['model' => 'App\Models\Task']);
                    $this->info('   ✅ Tasks indexed');
                } catch (\Exception $e) {
                    $this->warn('   ⚠️  Tasks indexing failed: ' . $e->getMessage());
                }

                $this->info('   Importing Documents...');
                try {
                    Artisan::call('scout:import', ['model' => 'App\Models\Document']);
                    $this->info('   ✅ Documents indexed');
                } catch (\Exception $e) {
                    $this->warn('   ⚠️  Documents indexing failed: ' . $e->getMessage());
                }
            } else {
                $this->warn('   ⚠️  Meilisearch not configured. Set SCOUT_DRIVER=meilisearch in .env');
            }
            $this->newLine();
        }

        // Step 3: Generate frontend types
        if (!$this->option('skip-frontend')) {
            $this->info('📝 Step 3: Generating frontend types...');
            
            $frontendPath = base_path('frontend');
            if (!is_dir($frontendPath)) {
                $this->warn('   ⚠️  Frontend directory not found. Skipping...');
            } else {
                $this->info('   Generating API types...');
                exec("cd {$frontendPath} && npm run generate:api-types 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    $this->info('   ✅ API types generated');
                } else {
                    $this->warn('   ⚠️  API type generation failed');
                }

                $this->info('   Generating ability types...');
                exec("cd {$frontendPath} && npm run generate:abilities 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    $this->info('   ✅ Ability types generated');
                } else {
                    $this->warn('   ⚠️  Ability type generation failed (may be no x-abilities in spec yet)');
                }
            }
            $this->newLine();
        }

        // Step 4: Cache optimization
        $this->info('⚡ Step 4: Optimizing caches...');
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $this->info('✅ Caches optimized');
        } catch (\Exception $e) {
            $this->warn('⚠️  Cache optimization failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Step 5: Verify setup
        $this->info('✅ Step 5: Verifying setup...');
        $this->verifySetup();
        $this->newLine();

        $this->info('🎉 Production setup completed!');
        $this->newLine();
        $this->info('📋 Next steps:');
        $this->line('   1. Start queue workers: php artisan queue:work');
        $this->line('   2. Start outbox processor: php artisan queue:work --queue=outbox');
        $this->line('   3. Start search indexer: php artisan queue:work --queue=search');
        $this->line('   4. Start media processor: php artisan queue:work --queue=media');
        $this->line('   5. Configure OpenTelemetry (optional)');
        $this->line('   6. Configure CDN for media (optional)');

        return 0;
    }

    /**
     * Verify production setup
     */
    protected function verifySetup(): void
    {
        $checks = [
            'Database connection' => function() {
                try {
                    DB::connection()->getPdo();
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            },
            'Outbox table exists' => function() {
                return Schema::hasTable('outbox');
            },
            'Idempotency keys table exists' => function() {
                return Schema::hasTable('idempotency_keys');
            },
            'Tenants table has media quota' => function() {
                return Schema::hasTable('tenants') && 
                       Schema::hasColumn('tenants', 'media_quota_mb');
            },
        ];

        foreach ($checks as $check => $callback) {
            if ($callback()) {
                $this->info("   ✅ {$check}");
            } else {
                $this->error("   ❌ {$check}");
            }
        }
    }
}

