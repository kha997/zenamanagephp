<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RBACSyncService;
use Illuminate\Console\Command;

/**
 * RBAC Sync Command
 * 
 * Generates a report comparing backend permissions with OpenAPI x-abilities.
 * Useful for CI/CD to detect drift between policies and API documentation.
 */
class RBACSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:sync-check {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check RBAC sync between backend permissions and OpenAPI x-abilities';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = app(RBACSyncService::class);
        $report = $service->generateReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->info('RBAC Sync Report');
        $this->line('================');
        $this->newLine();

        // OpenAPI Coverage
        $coverage = $report['openapi_coverage'];
        $this->info('OpenAPI Coverage:');
        $this->line("  Total endpoints: {$coverage['total']}");
        $this->line("  With x-abilities: {$coverage['with_abilities']}");
        $this->line("  Coverage: {$coverage['coverage_percent']}%");
        
        if (!empty($coverage['missing'])) {
            $this->warn('  Missing x-abilities:');
            foreach ($coverage['missing'] as $missing) {
                $this->line("    - {$missing['method']} {$missing['path']} ({$missing['summary']})");
            }
        }
        $this->newLine();

        // Permissions Comparison
        $comparison = $report['permissions_comparison'];
        $this->info('Permissions Comparison:');
        $this->line("  Backend permissions: {$comparison['permissions_count']}");
        $this->line("  OpenAPI abilities: {$comparison['abilities_count']}");
        
        if (!empty($comparison['in_permissions_not_abilities'])) {
            $this->warn('  Permissions not in OpenAPI:');
            foreach ($comparison['in_permissions_not_abilities'] as $perm) {
                $this->line("    - {$perm}");
            }
        }
        
        if (!empty($comparison['in_abilities_not_permissions'])) {
            $this->warn('  Abilities not in backend permissions:');
            foreach ($comparison['in_abilities_not_permissions'] as $ability) {
                $this->line("    - {$ability}");
            }
        }

        if ($comparison['match']) {
            $this->info('  ✓ Permissions and abilities match!');
        } else {
            $this->error('  ✗ Permissions and abilities do not match!');
        }
        $this->newLine();

        // Exit code based on drift
        $hasDrift = !$comparison['match'] || $coverage['coverage_percent'] < 80;
        
        if ($hasDrift) {
            $this->error('RBAC drift detected! Please update OpenAPI spec or backend permissions.');
            return Command::FAILURE;
        }

        $this->info('✓ No RBAC drift detected.');
        return Command::SUCCESS;
    }
}

