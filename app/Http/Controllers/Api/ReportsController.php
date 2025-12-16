<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesTenantContext;
use App\Http\Controllers\Api\Concerns\FormatsActivityEntries;
use App\Services\Reports\ContractsReportsService;
use App\Services\Reports\ContractCostOverrunsService;
use App\Services\Reports\PortfolioReportsService;
use App\Services\Reports\ProjectHealthPortfolioService;
use App\Services\Reports\ProjectHealthPortfolioHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Project;
use App\Models\Task;
use App\Models\ReportSchedule;
use Carbon\Carbon;

class ReportsController extends Controller
{
    use ResolvesTenantContext, FormatsActivityEntries;

    protected ContractsReportsService $contractsReportsService;
    protected ContractCostOverrunsService $contractCostOverrunsService;
    protected PortfolioReportsService $portfolioReportsService;
    protected ProjectHealthPortfolioService $projectHealthPortfolioService;
    protected ProjectHealthPortfolioHistoryService $projectHealthPortfolioHistoryService;

    public function __construct(
        ContractsReportsService $contractsReportsService,
        ContractCostOverrunsService $contractCostOverrunsService,
        PortfolioReportsService $portfolioReportsService,
        ProjectHealthPortfolioService $projectHealthPortfolioService,
        ProjectHealthPortfolioHistoryService $projectHealthPortfolioHistoryService
    ) {
        $this->contractsReportsService = $contractsReportsService;
        $this->contractCostOverrunsService = $contractCostOverrunsService;
        $this->portfolioReportsService = $portfolioReportsService;
        $this->projectHealthPortfolioService = $projectHealthPortfolioService;
        $this->projectHealthPortfolioHistoryService = $projectHealthPortfolioHistoryService;
    }

    /**
     * Get tenant ID from request context (throws if not found)
     */
    protected function getTenantId(Request $request): string
    {
        $tenantId = $this->resolveActiveTenantIdFromRequest($request);
        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID not found for user');
        }
        return $tenantId;
    }
    /**
     * Get Reports KPIs with trends
     */
    public function getKpis(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);
            $period = $request->get('period', 'week'); // week, month
            
            // Calculate date ranges
            $now = Carbon::now();
            $currentPeriodStart = match($period) {
                'week' => $now->copy()->startOfWeek(),
                'month' => $now->copy()->startOfMonth(),
                default => $now->copy()->startOfWeek(),
            };
            
            $previousPeriodStart = match($period) {
                'week' => $currentPeriodStart->copy()->subWeek(),
                'month' => $currentPeriodStart->copy()->subMonth(),
                default => $currentPeriodStart->copy()->subWeek(),
            };
            $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

            // Get current period metrics
            // Total reports scheduled
            $totalReports = ReportSchedule::where('tenant_id', $tenantId)->count();
            
            // Recent reports (sent in current period)
            $recentReports = ReportSchedule::where('tenant_id', $tenantId)
                ->whereNotNull('last_sent_at')
                ->where('last_sent_at', '>=', $currentPeriodStart)
                ->count();

            // Reports by type
            $byType = ReportSchedule::where('tenant_id', $tenantId)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();

            // Total downloads (count of reports sent in last 30 days)
            $downloads = ReportSchedule::where('tenant_id', $tenantId)
                ->whereNotNull('last_sent_at')
                ->where('last_sent_at', '>=', Carbon::now()->subDays(30))
                ->count();

            // Get previous period metrics for comparison
            $previousTotalReports = ReportSchedule::where('tenant_id', $tenantId)
                ->where('created_at', '<=', $previousPeriodEnd)->count();
            $previousRecentReports = ReportSchedule::where('tenant_id', $tenantId)
                ->whereNotNull('last_sent_at')
                ->where('last_sent_at', '>=', $previousPeriodStart)
                ->where('last_sent_at', '<=', $previousPeriodEnd)->count();
            $previousDownloads = ReportSchedule::where('tenant_id', $tenantId)
                ->whereNotNull('last_sent_at')
                ->where('last_sent_at', '>=', $previousPeriodStart->copy()->subDays(30))
                ->where('last_sent_at', '<=', $previousPeriodEnd)
                ->count();

            // Calculate trends (percentage change)
            $calculateTrend = function($current, $previous) {
                if ($previous == 0) return $current > 0 ? ['value' => 100, 'direction' => 'up'] : ['value' => 0, 'direction' => 'neutral'];
                $change = (($current - $previous) / $previous) * 100;
                return [
                    'value' => round(abs($change), 1),
                    'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
                ];
            };

            // Round 38: Get Contracts & Payments KPIs
            $contractsKpis = $this->contractsReportsService->getContractsKpisForTenant($tenantId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_reports' => $totalReports,
                    'recent_reports' => $recentReports,
                    'by_type' => $byType,
                    'downloads' => $downloads,
                    'trends' => [
                        'total_reports' => $calculateTrend($totalReports, $previousTotalReports),
                        'recent_reports' => $calculateTrend($recentReports, $previousRecentReports),
                        'downloads' => $calculateTrend($downloads, $previousDownloads),
                    ],
                    'period' => $period,
                    'contracts' => $contractsKpis, // Round 38: Contracts & Payments KPIs
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load reports KPIs', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load reports KPIs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Reports Alerts
     */
    public function getAlerts(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $alerts = [];
            
            // Get scheduled reports that are overdue (should have been sent but haven't)
            $overdueReports = ReportSchedule::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotNull('next_send_at')
                ->where('next_send_at', '<', Carbon::now())
                ->get();
                
            foreach ($overdueReports as $overdueReport) {
                $alerts[] = [
                    'id' => 'overdue-report-' . $overdueReport->id,
                    'title' => 'Report Overdue',
                    'message' => "Scheduled report '{$overdueReport->name}' is overdue",
                    'severity' => 'high',
                    'status' => 'unread',
                    'type' => 'report_overdue',
                    'source' => 'report',
                    'createdAt' => now()->toISOString(),
                    'metadata' => [
                        'report_id' => $overdueReport->id,
                        'next_send_at' => $overdueReport->next_send_at?->toISOString()
                    ]
                ];
            }

            // Get scheduled reports that are due soon (within 24 hours)
            $dueSoonReports = ReportSchedule::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotNull('next_send_at')
                ->where(function($q) {
                    $nextRun = Carbon::now()->addDay();
                    $q->where('next_send_at', '<=', $nextRun)
                      ->where('next_send_at', '>', Carbon::now());
                })
                ->get();
                
            foreach ($dueSoonReports as $dueReport) {
                $alerts[] = [
                    'id' => 'due-report-' . $dueReport->id,
                    'title' => 'Report Scheduled Soon',
                    'message' => "Report '{$dueReport->name}' is scheduled to send soon",
                    'severity' => 'low',
                    'status' => 'unread',
                    'type' => 'report_due',
                    'source' => 'report',
                    'createdAt' => now()->toISOString(),
                    'metadata' => [
                        'report_id' => $dueReport->id,
                        'next_send_at' => $dueReport->next_send_at?->toISOString()
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load reports alerts', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load reports alerts',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Reports Activity
     * 
     * Round 32: Refactored to use FormatsActivityEntries trait for consistency
     */
    public function getActivity(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);
            $user = Auth::user();
            $limit = (int) $request->get('limit', 10);

            $activity = [];
            
            // Get recent report sends
            $recentReports = ReportSchedule::where('tenant_id', $tenantId)
                ->whereNotNull('last_sent_at')
                ->orderBy('last_sent_at', 'desc')
                ->limit($limit)
                ->get();
                
            foreach ($recentReports as $report) {
                // Use trait method to format activity entry consistently
                $activity[] = $this->formatActivityEntry([
                    'id' => 'report-' . $report->id,
                    'type' => 'report',
                    'action' => 'sent',
                    'description' => "Report '{$report->name}' was sent",
                    'timestamp' => $report->last_sent_at->toISOString(),
                ], $user);
            }

            return response()->json([
                'success' => true,
                'data' => $activity
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load reports activity', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load reports activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contract cost overruns
     * 
     * Round 47: Cost Overruns Dashboard
     * 
     * Returns lists of contracts that are over budget or over actual cost.
     */
    public function getContractCostOverruns(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'status' => $request->query('status'),
                'search' => $request->query('search'),
                'min_budget_diff' => $request->query('min_budget_diff'),
                'min_actual_diff' => $request->query('min_actual_diff'),
                'limit' => $request->query('limit'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $data = $this->contractCostOverrunsService->getContractCostOverrunsForTenant($tenantId, $filters);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load contract cost overruns', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load contract cost overruns',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contract cost overruns table
     * 
     * Round 49: Full-page Cost Overruns Table
     * 
     * Returns paginated, sortable list of contracts with overruns.
     */
    public function getContractCostOverrunsTable(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'client_id' => $request->query('client_id'),
                'project_id' => $request->query('project_id'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
                'type' => $request->query('type'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $pagination = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 25),
            ];

            $sort = [
                'sort_by' => $request->query('sort_by', 'overrun_amount'),
                'sort_direction' => $request->query('sort_direction', 'desc'),
            ];

            $data = $this->contractCostOverrunsService->getContractCostOverrunsTableForTenant(
                $tenantId,
                $filters,
                $pagination,
                $sort
            );

            return response()->json([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load contract cost overruns table', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'COST_OVERRUNS_TABLE_ERROR',
                'message' => 'Failed to load contract cost overruns table',
                'traceId' => $request->header('X-Request-Id', 'unknown')
            ], 500);
        }
    }

    /**
     * Export contract cost overruns to CSV
     * 
     * Round 49: Full-page Cost Overruns Export
     * Round 51: Added sort support to match table sorting
     * 
     * Exports contracts with overruns to CSV file.
     */
    public function exportContractCostOverruns(Request $request)
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'client_id' => $request->query('client_id'),
                'project_id' => $request->query('project_id'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
                'type' => $request->query('type'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            // Round 51: Read sort parameters (optional, defaults handled in service)
            $sort = [
                'sort_by' => $request->query('sort_by'),
                'sort_direction' => $request->query('sort_direction'),
            ];

            // Remove null values
            $sort = array_filter($sort, fn($value) => $value !== null);

            return $this->contractCostOverrunsService->exportContractCostOverrunsForTenant($tenantId, $filters, $sort);
        } catch (\Throwable $e) {
            $traceId = $request->header('X-Request-Id', 'unknown');
            
            Log::error('Failed to export contract cost overruns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'route' => $request->path(),
                'traceId' => $traceId,
            ]);
            
            // Round 50: Return JSON error for SPA requests, abort for non-JSON
            if ($request->expectsJson() || $request->wantsJson()) {
                return \App\Services\ErrorEnvelopeService::error(
                    'EXPORT_FAILED',
                    'Không thể xuất cost overruns',
                    [],
                    500,
                    $traceId
                );
            }
            
            // Fallback for non-JSON requests (e.g., direct browser download)
            abort(500, 'Failed to export contract cost overruns: ' . $e->getMessage());
        }
    }

    /**
     * Get project cost portfolio
     * 
     * Round 51: Project Cost Portfolio
     * 
     * Returns paginated, sortable list of projects with aggregated cost metrics.
     */
    public function getProjectCostPortfolio(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'client_id' => $request->query('client_id'),
                'status' => $request->query('status'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $pagination = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 25),
            ];

            $sort = [
                'sort_by' => $request->query('sort_by', 'overrun_amount_total'),
                'sort_direction' => $request->query('sort_direction', 'desc'),
            ];

            $data = $this->portfolioReportsService->getProjectCostPortfolioForTenant(
                $tenantId,
                $filters,
                $pagination,
                $sort
            );

            return response()->json([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load project cost portfolio', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'PROJECT_COST_PORTFOLIO_ERROR',
                'message' => 'Failed to load project cost portfolio',
                'traceId' => $request->header('X-Request-Id', 'unknown')
            ], 500);
        }
    }

    /**
     * Get client cost portfolio
     * 
     * Round 53: Client Cost Portfolio
     * 
     * Returns paginated, sortable list of clients with aggregated cost metrics.
     */
    public function getClientCostPortfolio(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'client_id' => $request->query('client_id'),
                'status' => $request->query('status'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $pagination = [
                'page' => $request->query('page', 1),
                'per_page' => $request->query('per_page', 25),
            ];

            $sort = [
                'sort_by' => $request->query('sort_by', 'overrun_amount_total'),
                'sort_direction' => $request->query('sort_direction', 'desc'),
            ];

            $data = $this->portfolioReportsService->getClientCostPortfolioForTenant(
                $tenantId,
                $filters,
                $pagination,
                $sort
            );

            return response()->json([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load client cost portfolio', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'CLIENT_COST_PORTFOLIO_ERROR',
                'message' => 'Failed to load client cost portfolio',
                'traceId' => $request->header('X-Request-Id', 'unknown')
            ], 500);
        }
    }

    /**
     * Export project cost portfolio to CSV
     * 
     * Round 66: Project Cost Portfolio Export
     * 
     * Exports projects with cost metrics to CSV file.
     * Uses same filters and sort as table endpoint, but exports all matching items (no pagination).
     */
    public function exportProjectCostPortfolio(Request $request)
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'client_id' => $request->query('client_id'),
                'status' => $request->query('status'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $sort = [
                'sort_by' => $request->query('sort_by', 'overrun_amount_total'),
                'sort_direction' => $request->query('sort_direction', 'desc'),
            ];

            $items = $this->portfolioReportsService->getProjectCostPortfolioForTenantExport(
                $tenantId,
                $filters,
                $sort
            );

            $filename = 'tenant-' . $tenantId . '-project-cost-portfolio-' . now()->format('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ];

            return response()->stream(function () use ($items) {
                $out = fopen('php://output', 'w');

                // Add BOM for UTF-8 (Excel compatibility)
                fwrite($out, "\xEF\xBB\xBF");

                // CSV headers
                fputcsv($out, [
                    'ClientName',
                    'ProjectCode',
                    'ProjectName',
                    'ContractsCount',
                    'ContractsValueTotal',
                    'BudgetTotal',
                    'ActualTotal',
                    'OverrunAmountTotal',
                    'Currency',
                ]);

                foreach ($items as $item) {
                    fputcsv($out, [
                        $item['client']['name'] ?? '',
                        $item['project_code'],
                        $item['project_name'],
                        $item['contracts_count'],
                        $item['contracts_value_total'] ?? '',
                        $item['budget_total'],
                        $item['actual_total'],
                        $item['overrun_amount_total'],
                        $item['currency'] ?? 'USD',
                    ]);
                }

                fclose($out);
            }, 200, $headers);
        } catch (\Throwable $e) {
            $traceId = $request->header('X-Request-Id', 'unknown');
            
            Log::error('Failed to export project cost portfolio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'route' => $request->path(),
                'traceId' => $traceId,
            ]);
            
            // Return JSON error for SPA requests, abort for non-JSON
            if ($request->expectsJson() || $request->wantsJson()) {
                return \App\Services\ErrorEnvelopeService::error(
                    'EXPORT_FAILED',
                    'Không thể xuất project cost portfolio',
                    [],
                    500,
                    $traceId
                );
            }
            
            // Fallback for non-JSON requests (e.g., direct browser download)
            abort(500, 'Failed to export project cost portfolio: ' . $e->getMessage());
        }
    }

    /**
     * Export client cost portfolio to CSV
     * 
     * Round 66: Client Cost Portfolio Export
     * 
     * Exports clients with cost metrics to CSV file.
     * Uses same filters and sort as table endpoint, but exports all matching items (no pagination).
     */
    public function exportClientCostPortfolio(Request $request)
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $filters = [
                'search' => $request->query('search'),
                'client_id' => $request->query('client_id'),
                'status' => $request->query('status'),
                'min_overrun_amount' => $request->query('min_overrun_amount'),
            ];

            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);

            $sort = [
                'sort_by' => $request->query('sort_by', 'overrun_amount_total'),
                'sort_direction' => $request->query('sort_direction', 'desc'),
            ];

            $items = $this->portfolioReportsService->getClientCostPortfolioForTenantExport(
                $tenantId,
                $filters,
                $sort
            );

            $filename = 'tenant-' . $tenantId . '-client-cost-portfolio-' . now()->format('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ];

            return response()->stream(function () use ($items) {
                $out = fopen('php://output', 'w');

                // Add BOM for UTF-8 (Excel compatibility)
                fwrite($out, "\xEF\xBB\xBF");

                // CSV headers
                fputcsv($out, [
                    'ClientName',
                    'ProjectsCount',
                    'ContractsCount',
                    'ContractsValueTotal',
                    'BudgetTotal',
                    'ActualTotal',
                    'OverrunAmountTotal',
                    'Currency',
                ]);

                foreach ($items as $item) {
                    fputcsv($out, [
                        $item['client_name'],
                        $item['projects_count'],
                        $item['contracts_count'],
                        $item['contracts_value_total'] ?? '',
                        $item['budget_total'],
                        $item['actual_total'],
                        $item['overrun_amount_total'],
                        $item['currency'] ?? 'USD',
                    ]);
                }

                fclose($out);
            }, 200, $headers);
        } catch (\Throwable $e) {
            $traceId = $request->header('X-Request-Id', 'unknown');
            
            Log::error('Failed to export client cost portfolio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'route' => $request->path(),
                'traceId' => $traceId,
            ]);
            
            // Return JSON error for SPA requests, abort for non-JSON
            if ($request->expectsJson() || $request->wantsJson()) {
                return \App\Services\ErrorEnvelopeService::error(
                    'EXPORT_FAILED',
                    'Không thể xuất client cost portfolio',
                    [],
                    500,
                    $traceId
                );
            }
            
            // Fallback for non-JSON requests (e.g., direct browser download)
            abort(500, 'Failed to export client cost portfolio: ' . $e->getMessage());
        }
    }

    /**
     * Get project health portfolio
     * 
     * Round 74: Project Health Portfolio
     * 
     * Returns health summary for all projects of a tenant.
     */
    public function projectHealth(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $items = $this->projectHealthPortfolioService->getProjectHealthForTenant($tenantId);

            return response()->json([
                'ok' => true,
                'data' => $items
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load project health portfolio', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'PROJECT_HEALTH_PORTFOLIO_ERROR',
                'message' => 'Failed to load project health portfolio',
                'traceId' => $request->header('X-Request-Id', 'unknown')
            ], 500);
        }
    }

    /**
     * Export project health portfolio to CSV
     * 
     * Round 79: Project Health Portfolio Export
     * 
     * Exports projects with health metrics to CSV file.
     * Uses same data as projectHealth endpoint, but exports all items (no pagination).
     */
    public function exportProjectHealthPortfolio(Request $request)
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            $items = $this->projectHealthPortfolioService->getProjectHealthForTenant($tenantId);

            $filename = 'tenant-' . $tenantId . '-project-health-portfolio-' . now()->format('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ];

            return response()->stream(function () use ($items) {
                $out = fopen('php://output', 'w');

                // Add BOM for UTF-8 (Excel compatibility)
                fwrite($out, "\xEF\xBB\xBF");

                // CSV headers
                fputcsv($out, [
                    'ProjectCode',
                    'ProjectName',
                    'ClientName',
                    'ProjectStatus',
                    'ScheduleStatus',
                    'CostStatus',
                    'OverallStatus',
                    'TasksCompletionRate',
                    'BlockedTasksRatio',
                    'OverdueTasks',
                    'CostOverrunPercent',
                ]);

                foreach ($items as $item) {
                    $project = $item['project'] ?? [];
                    $health = $item['health'] ?? [];

                    fputcsv($out, [
                        $project['code'] ?? '',
                        $project['name'] ?? '',
                        $project['client']['name'] ?? '',
                        $project['status'] ?? '',
                        $health['schedule_status'] ?? '',
                        $health['cost_status'] ?? '',
                        $health['overall_status'] ?? '',
                        $health['tasks_completion_rate'] !== null ? number_format($health['tasks_completion_rate'], 2) : '',
                        $health['blocked_tasks_ratio'] !== null ? number_format($health['blocked_tasks_ratio'], 2) : '',
                        $health['overdue_tasks'] ?? 0,
                        $health['cost_overrun_percent'] !== null ? number_format($health['cost_overrun_percent'], 2) : '',
                    ]);
                }

                fclose($out);
            }, 200, $headers);
        } catch (\Throwable $e) {
            $traceId = $request->header('X-Request-Id', 'unknown');
            
            Log::error('Failed to export project health portfolio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'route' => $request->path(),
                'traceId' => $traceId,
            ]);
            
            // Return JSON error for SPA requests, abort for non-JSON
            if ($request->expectsJson() || $request->wantsJson()) {
                return \App\Services\ErrorEnvelopeService::error(
                    'EXPORT_FAILED',
                    'Không thể xuất project health portfolio',
                    [],
                    500,
                    $traceId
                );
            }
            
            // Fallback for non-JSON requests (e.g., direct browser download)
            abort(500, 'Failed to export project health portfolio: ' . $e->getMessage());
        }
    }

    /**
     * Get project health portfolio history
     * 
     * Round 91: Project Health Portfolio History API (backend-only)
     * 
     * Returns aggregated daily portfolio health history for the current tenant.
     * Shows per-day counts of projects by health status (good, warning, critical).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function projectHealthPortfolioHistory(Request $request): JsonResponse
    {
        $tenantId = null;
        try {
            $tenantId = $this->getTenantId($request);

            // Get days parameter (default: 30, clamp: 1-90)
            $days = (int) $request->query('days', 30);
            $days = max(1, min($days, 90));

            $history = $this->projectHealthPortfolioHistoryService->getTenantPortfolioHistory($tenantId, $days);

            return response()->json([
                'ok' => true,
                'data' => $history->values()->all()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load project health portfolio history', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'ok' => false,
                'code' => 'PROJECT_HEALTH_PORTFOLIO_HISTORY_ERROR',
                'message' => 'Failed to load project health portfolio history',
                'traceId' => $request->header('X-Request-Id', 'unknown')
            ], 500);
        }
    }
}

