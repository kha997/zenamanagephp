<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Project Health Portfolio Generated Event
 * 
 * Round 83: Project Health Observability & Perf Baseline
 * 
 * Emitted when a project health portfolio is generated for a tenant.
 * Contains performance metrics: tenant ID, project count, and duration.
 */
class ProjectHealthPortfolioGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     * 
     * @param string|int $tenantId Tenant ID (ULID string or int)
     * @param int $projectCount Number of projects in the portfolio
     * @param float $durationMs Duration in milliseconds
     */
    public function __construct(
        public readonly string|int $tenantId,
        public readonly int $projectCount,
        public readonly float $durationMs,
    ) {
    }
}

