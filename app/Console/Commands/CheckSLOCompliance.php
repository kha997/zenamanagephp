<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SLOAlertingService;
use App\Services\DashboardFreshnessTracker;

/**
 * Check SLO Compliance Command
 * 
 * PR: SLO/SLA nội bộ
 * 
 * Scheduled command to check SLO compliance and send alerts.
 * Should run every 5 minutes.
 */
class CheckSLOCompliance extends Command
{
    protected $signature = 'slo:check 
                            {--freshness : Check dashboard freshness only}
                            {--no-alerts : Check without sending alerts}';

    protected $description = 'Check SLO compliance and send alerts for violations';

    protected SLOAlertingService $sloService;
    protected DashboardFreshnessTracker $freshnessTracker;

    public function __construct(
        SLOAlertingService $sloService,
        DashboardFreshnessTracker $freshnessTracker
    ) {
        parent::__construct();
        $this->sloService = $sloService;
        $this->freshnessTracker = $freshnessTracker;
    }

    public function handle(): int
    {
        $this->info('🔍 Checking SLO compliance...');

        if ($this->option('freshness')) {
            return $this->checkFreshness();
        }

        // Check all SLOs
        $violations = $this->sloService->checkSLOCompliance();

        if (empty($violations)) {
            $this->info('✅ All SLOs are within targets');
            return Command::SUCCESS;
        }

        // Group violations by severity
        $critical = array_filter($violations, fn($v) => $v['severity'] === 'critical');
        $warning = array_filter($violations, fn($v) => $v['severity'] === 'warning');
        $info = array_filter($violations, fn($v) => $v['severity'] === 'info');

        $this->warn("⚠️  Found " . count($violations) . " SLO violations:");
        $this->line("   🔴 Critical: " . count($critical));
        $this->line("   🟡 Warning: " . count($warning));
        $this->line("   🔵 Info: " . count($info));

        // Display violations
        if (!empty($critical)) {
            $this->error("\n🔴 Critical Violations:");
            foreach ($critical as $violation) {
                $this->displayViolation($violation);
            }
        }

        if (!empty($warning)) {
            $this->warn("\n🟡 Warning Violations:");
            foreach ($warning as $violation) {
                $this->displayViolation($violation);
            }
        }

        if (!empty($info)) {
            $this->info("\n🔵 Info Violations:");
            foreach ($info as $violation) {
                $this->displayViolation($violation);
            }
        }

        // Check freshness
        $freshnessViolations = $this->freshnessTracker->getViolations();
        if (!empty($freshnessViolations)) {
            $this->warn("\n⚠️  Dashboard Freshness Violations:");
            foreach ($freshnessViolations as $violation) {
                $this->line("   {$violation['mutation_type']}: p95 = {$violation['p95']}ms (target: {$violation['target']}ms)");
            }
        }

        return Command::SUCCESS;
    }

    protected function checkFreshness(): int
    {
        $this->info('📊 Checking dashboard freshness...');

        $violations = $this->freshnessTracker->getViolations();
        $metrics = $this->freshnessTracker->getFreshnessMetrics();

        if (empty($violations)) {
            $this->info('✅ All dashboard freshness metrics are within target (≤ 5s)');
        } else {
            $this->warn("⚠️  Found " . count($violations) . " freshness violations:");
            foreach ($violations as $violation) {
                $this->line("   {$violation['mutation_type']}: p95 = {$violation['p95']}ms (target: {$violation['target']}ms)");
            }
        }

        // Display metrics summary
        if (!empty($metrics)) {
            $this->info("\n📊 Freshness Metrics Summary:");
            foreach ($metrics as $mutationType => $stats) {
                $this->line("   {$mutationType}:");
                $this->line("      p50: {$stats['p50']}ms");
                $this->line("      p95: {$stats['p95']}ms");
                $this->line("      p99: {$stats['p99']}ms");
                $this->line("      avg: {$stats['avg']}ms");
            }
        }

        return Command::SUCCESS;
    }

    protected function displayViolation(array $violation): void
    {
        $percentage = number_format($violation['percentage'], 1);
        $this->line("   {$violation['category']}/{$violation['metric']}:");
        $this->line("      Value: {$violation['value']} (Target: {$violation['target']})");
        $this->line("      Percentage: {$percentage}%");
        $this->line("      Severity: {$violation['severity']}");
    }
}

