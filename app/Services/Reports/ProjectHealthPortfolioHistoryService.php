<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ProjectHealthSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Project Health Portfolio History Service
 * 
 * Round 91: Project Health Portfolio History API (backend-only)
 * 
 * Provides aggregated daily portfolio health history for a tenant.
 * Aggregates project health snapshots per day to show portfolio trends.
 */
class ProjectHealthPortfolioHistoryService
{
    /**
     * Get tenant portfolio health history
     * 
     * Aggregates daily snapshots to show per-day counts of projects by health status.
     * 
     * @param string|int $tenantId Tenant ID
     * @param int $days Number of days to look back (default: 30, max: 90, min: 1)
     * @return Collection Collection of daily summaries with snapshot_date, good, warning, critical, total
     */
    public function getTenantPortfolioHistory(string|int $tenantId, int $days = 30): Collection
    {
        // Clamp days to valid range
        $days = max(1, min($days, 90));

        // Compute date window
        $endDate = Carbon::today(config('app.timezone'));
        $startDate = $endDate->copy()->subDays($days - 1);

        // Query snapshots grouped by date and status
        // Use DATE() function to ensure consistent date format comparison
        $grouped = DB::table('project_health_snapshots')
            ->selectRaw('DATE(snapshot_date) as snapshot_date, overall_status, COUNT(*) as projects_count')
            ->where('tenant_id', $tenantId)
            ->whereBetween('snapshot_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('DATE(snapshot_date)'), 'overall_status')
            ->orderBy('snapshot_date')
            ->get();

        // Transform to per-day summary
        $dailySummaries = [];
        $currentDate = null;
        $currentSummary = null;

        foreach ($grouped as $row) {
            // Ensure date is in Y-m-d format (handle both string and Carbon instances)
            $dateString = is_string($row->snapshot_date) 
                ? $row->snapshot_date 
                : Carbon::parse($row->snapshot_date)->toDateString();
            
            // If new date, save previous summary and start new one
            if ($currentDate !== $dateString) {
                if ($currentSummary !== null) {
                    $dailySummaries[] = $currentSummary;
                }
                
                $currentDate = $dateString;
                $currentSummary = [
                    'snapshot_date' => $dateString,
                    'good' => 0,
                    'warning' => 0,
                    'critical' => 0,
                    'total' => 0,
                ];
            }

            // Aggregate counts by status
            $status = $row->overall_status;
            $count = (int) $row->projects_count;

            if ($status === 'good') {
                $currentSummary['good'] += $count;
            } elseif ($status === 'warning') {
                $currentSummary['warning'] += $count;
            } elseif ($status === 'critical') {
                $currentSummary['critical'] += $count;
            }
            // Note: 'no_data' status is ignored per requirements
        }

        // Add the last summary if exists
        if ($currentSummary !== null) {
            $dailySummaries[] = $currentSummary;
        }

        // Calculate total for each day
        foreach ($dailySummaries as &$summary) {
            $summary['total'] = $summary['good'] + $summary['warning'] + $summary['critical'];
        }

        return collect($dailySummaries);
    }
}

