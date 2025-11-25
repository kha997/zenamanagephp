<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProjectHealthPortfolioGenerated;
use Illuminate\Support\Facades\Log;

/**
 * Log Project Health Portfolio Generation Listener
 * 
 * Round 83: Project Health Observability & Perf Baseline
 * 
 * Logs project health portfolio generation events with performance metrics.
 * Respects monitoring configuration (enabled/disabled, log channel).
 */
class LogProjectHealthPortfolioGeneration
{
    /**
     * Handle the event.
     */
    public function handle(ProjectHealthPortfolioGenerated $event): void
    {
        // Read configuration
        $enabled = config('reports.project_health.monitoring_enabled', true);
        $channel = config('reports.project_health.log_channel');
        $sampleRate = (float) config('reports.project_health.sample_rate', 1.0);
        $logWhenEmpty = (bool) config('reports.project_health.log_when_empty', false);

        // Early returns
        if ($enabled === false) {
            return;
        }

        // Skip empty portfolios if log_when_empty is false
        if ($event->projectCount === 0 && $logWhenEmpty === false) {
            return;
        }

        // Skip if sample rate is 0 or negative
        if ($sampleRate <= 0.0) {
            return;
        }

        // Sampling: for rates < 1.0, use random check
        if ($sampleRate < 1.0) {
            $random = mt_rand() / mt_getrandmax();
            if ($random > $sampleRate) {
                return;
            }
        }

        // Build context
        $context = [
            'tenant_id' => $event->tenantId,
            'projects' => $event->projectCount,
            'duration_ms' => $event->durationMs,
        ];

        // Log using configured channel or default
        if ($channel !== null) {
            Log::channel($channel)->info('project_health.portfolio_generated', $context);
        } else {
            Log::info('project_health.portfolio_generated', $context);
        }
    }
}

