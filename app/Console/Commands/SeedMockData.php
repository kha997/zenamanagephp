<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\MockDataSeeder;

class SeedMockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mock:seed {--fresh : Clear all existing data first}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed database with comprehensive mock data for testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Mock Data Seeding Process...');
        
        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh mode enabled - this will clear existing data!');
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('❌ Operation cancelled.');
                return;
            }
        }

        try {
            $seeder = new MockDataSeeder();
            $seeder->setCommand($this);
            $seeder->run();

            $this->info('');
            $this->info('✅ Mock data seeding completed successfully!');
            $this->info('📊 You can now test all functionality with real database data.');
            $this->info('');
            $this->info('🔗 Test the following:');
            $this->info('   - Tasks Management: http://localhost:8000/tasks');
            $this->info('   - Projects Management: http://localhost:8000/projects');
            $this->info('   - Admin Dashboard: http://localhost:8000/admin');
            $this->info('');
            $this->info('🧹 To clean up after testing, run: php artisan mock:clean');

        } catch (\Exception $e) {
            $this->error('❌ Error seeding mock data: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
