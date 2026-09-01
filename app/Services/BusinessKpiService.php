<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ContractPayment;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Cache;

class BusinessKpiService
{
    private const TERMINAL_LOST_STAGES = [
        Opportunity::STAGE_WON,
        Opportunity::STAGE_LOST,
        Opportunity::STAGE_NO_BID,
    ];

    /**
     * @return array<string, float>
     */
    public function monthlyRevenue(string $tenantId): array
    {
        return Cache::remember("business_kpi_monthly_revenue_{$tenantId}", 60, function () use ($tenantId): array {
            $result = [];

            Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->where('pipeline_stage', Opportunity::STAGE_WON)
                ->get(['updated_at', 'estimated_fee', 'external_quote_snapshot'])
                ->each(function (Opportunity $opportunity) use (&$result): void {
                    $month = $opportunity->updated_at->format('Y-m');
                    $revenue = $this->revenueFor($opportunity);
                    $result[$month] = ($result[$month] ?? 0.0) + $revenue;
                });

            ksort($result);

            return $result;
        });
    }

    /**
     * @return array<string, float>
     */
    public function pipelineByStage(string $tenantId): array
    {
        return Cache::remember("business_kpi_pipeline_by_stage_{$tenantId}", 60, function () use ($tenantId): array {
            return Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('pipeline_stage, SUM(estimated_fee) as total')
                ->groupBy('pipeline_stage')
                ->pluck('total', 'pipeline_stage')
                ->map(fn ($value) => (float) $value)
                ->toArray();
        });
    }

    /**
     * @return array{total: float, overdue_total: float, overdue_count: int, aging: array{not_due: float, due_1_30: float, due_31_60: float, due_61_90: float, due_over_90: float}}
     */
    public function outstandingDebt(string $tenantId): array
    {
        return Cache::remember("business_kpi_outstanding_debt_{$tenantId}", 60, function () use ($tenantId): array {
            $unpaid = ContractPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', ContractPayment::STATUS_PAID);

            $payments = (clone $unpaid)->get();

            $total = (float) $payments->sum('amount');
            $overdue = $payments->filter(fn ($p) => $p->due_date < now());

            $aging = [
                'not_due' => 0.0,
                'due_1_30' => 0.0,
                'due_31_60' => 0.0,
                'due_61_90' => 0.0,
                'due_over_90' => 0.0,
            ];

            foreach ($payments as $payment) {
                $daysOverdue = now()->diffInDays($payment->due_date, false) * -1;
                $amount = (float) $payment->amount;

                if ($daysOverdue <= 0) {
                    $aging['not_due'] += $amount;
                } elseif ($daysOverdue <= 30) {
                    $aging['due_1_30'] += $amount;
                } elseif ($daysOverdue <= 60) {
                    $aging['due_31_60'] += $amount;
                } elseif ($daysOverdue <= 90) {
                    $aging['due_61_90'] += $amount;
                } else {
                    $aging['due_over_90'] += $amount;
                }
            }

            return [
                'total' => $total,
                'overdue_total' => (float) $overdue->sum('amount'),
                'overdue_count' => $overdue->count(),
                'aging' => $aging,
            ];
        });
    }

    /**
     * @return array<string, array{won: int, total: int, rate: float}>
     */
    public function salesWinRate(string $tenantId): array
    {
        return Cache::remember("business_kpi_sales_win_rate_{$tenantId}", 60, function () use ($tenantId): array {
            $rows = Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('pipeline_stage', self::TERMINAL_LOST_STAGES)
                ->whereNotNull('sales_owner_id')
                ->get(['sales_owner_id', 'pipeline_stage']);

            $result = [];

            foreach ($rows->groupBy('sales_owner_id') as $ownerId => $group) {
                $total = $group->count();
                $won = $group->where('pipeline_stage', Opportunity::STAGE_WON)->count();

                $result[(string) $ownerId] = [
                    'won' => $won,
                    'total' => $total,
                    'rate' => $total > 0 ? (float) $won / $total : 0.0,
                ];
            }

            return $result;
        });
    }

    /**
     * @return array<string, array{won: int, total: int, rate: float, avg_fee: float}>
     */
    public function serviceCategoryPerformance(string $tenantId): array
    {
        return Cache::remember("business_kpi_service_category_performance_{$tenantId}", 60, function () use ($tenantId): array {
            /** @var \Illuminate\Support\Collection<int, Opportunity> $rows */
            $rows = Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('pipeline_stage', self::TERMINAL_LOST_STAGES)
                ->get(['service_category', 'pipeline_stage', 'estimated_fee', 'external_quote_snapshot']);

            $result = [];

            // GAP-048 §14 — a NULL service_category (possible once the
            // nullable migration ships) becomes an explicit "unclassified"
            // bucket rather than being silently dropped. This remains a
            // deliberate single-value bridge, not multi-Service-Line aware.
            foreach ($rows->groupBy(fn (Opportunity $opportunity) => $opportunity->service_category ?? 'unclassified') as $category => $group) {
                $total = $group->count();
                $wonOpportunities = $group->where('pipeline_stage', Opportunity::STAGE_WON);
                $won = $wonOpportunities->count();
                $avgFee = $won > 0
                    ? $wonOpportunities->sum(fn (Opportunity $opportunity) => $this->revenueFor($opportunity)) / $won
                    : 0.0;

                $result[(string) $category] = [
                    'won' => $won,
                    'total' => $total,
                    'rate' => $total > 0 ? (float) $won / $total : 0.0,
                    'avg_fee' => $avgFee,
                ];
            }

            return $result;
        });
    }

    private function revenueFor(Opportunity $opportunity): float
    {
        $snapshot = $opportunity->external_quote_snapshot ?? [];

        return (float) ($snapshot['total'] ?? $opportunity->estimated_fee ?? 0);
    }
}
