<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Contract;
use Illuminate\Http\StreamedResponse;

/**
 * Service for computing Contract Cost Overruns
 * 
 * Round 47: Cost Overruns Dashboard + Export
 * 
 * Provides lists of contracts that are over budget or over actual cost,
 * scoped by tenant_id for multi-tenant isolation.
 */
class ContractCostOverrunsService
{
    /**
     * Get contract cost overruns for a tenant
     * 
     * Round 49: Added detailed docblock for field meanings
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Optional filters:
     *   - status: Filter by contract status
     *   - search: Search in code, name, or client name
     *   - min_budget_diff: Minimum budget_vs_contract_diff to include
     *   - min_actual_diff: Minimum -contract_vs_actual_diff to include (positive value)
     *   - limit: Limit per list (default: 10)
     * @return array Structure:
     *   - over_budget_contracts: Contracts where budget_total > contract_value
     *     Each item contains:
     *       - budget_vs_contract_diff: budget_total - contract_value (positive when over budget)
     *   - overrun_contracts: Contracts where actual_total > contract_value
     *     Each item contains:
     *       - contract_vs_actual_diff: contract_value - actual_total (negative when overrun)
     *       - overrun_amount: actual_total - contract_value (only > 0 when overrun, i.e., actual_total > contract_value)
     */
    public function getContractCostOverrunsForTenant(string $tenantId, array $filters = []): array
    {
        // Get contracts with total_value != null (only these can have overruns)
        $query = Contract::where('tenant_id', $tenantId)
            ->whereNotNull('total_value')
            ->whereNull('deleted_at');

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $contracts = $query->with(['client', 'project'])->get();

        $overBudgetContracts = [];
        $overrunContracts = [];
        $limit = (int) ($filters['limit'] ?? 10);
        $minBudgetDiff = isset($filters['min_budget_diff']) ? (float) $filters['min_budget_diff'] : null;
        $minActualDiff = isset($filters['min_actual_diff']) ? (float) $filters['min_actual_diff'] : null;

        foreach ($contracts as $contract) {
            $contractValue = (float) $contract->total_value;

            // Calculate budget_total: sum of active budget lines
            $budgetTotal = (float) $contract->budgetLines()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount') ?? 0.0;

            // Calculate actual_total: sum of active expenses
            $actualTotal = (float) $contract->expenses()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('amount') ?? 0.0;

            // Calculate differences
            $budgetVsContractDiff = $budgetTotal - $contractValue;
            $contractVsActualDiff = $contractValue - $actualTotal;

            // Check over budget
            if ($budgetTotal > $contractValue) {
                // Apply min_budget_diff filter if set
                if ($minBudgetDiff === null || $budgetVsContractDiff >= $minBudgetDiff) {
                    $overBudgetContracts[] = [
                        'id' => (string) $contract->id,
                        'code' => $contract->code,
                        'name' => $contract->name,
                        'client_name' => $contract->client?->name,
                        'project_name' => $contract->project?->name,
                        'status' => $contract->status,
                        'currency' => $contract->currency,
                        'contract_value' => $contractValue,
                        'budget_total' => $budgetTotal,
                        'budget_vs_contract_diff' => $budgetVsContractDiff,
                    ];
                }
            }

            // Check overrun (actual > contract)
            if ($actualTotal > $contractValue) {
                // Apply min_actual_diff filter if set (note: we use -contractVsActualDiff which is positive when overrun)
                $actualOverrun = -$contractVsActualDiff;
                if ($minActualDiff === null || $actualOverrun >= $minActualDiff) {
                    $overrunContracts[] = [
                        'id' => (string) $contract->id,
                        'code' => $contract->code,
                        'name' => $contract->name,
                        'client_name' => $contract->client?->name,
                        'project_name' => $contract->project?->name,
                        'status' => $contract->status,
                        'currency' => $contract->currency,
                        'contract_value' => $contractValue,
                        'actual_total' => $actualTotal,
                        'contract_vs_actual_diff' => $contractVsActualDiff,
                        'overrun_amount' => $actualOverrun, // actual_total - contract_value (positive when overrun)
                    ];
                }
            }
        }

        // Sort by diff (descending) and limit
        usort($overBudgetContracts, fn($a, $b) => $b['budget_vs_contract_diff'] <=> $a['budget_vs_contract_diff']);
        // Sort overrun_contracts by overrun_amount descending (overrun nhiều đứng trước)
        usort($overrunContracts, fn($a, $b) => $b['overrun_amount'] <=> $a['overrun_amount']);

        return [
            'over_budget_contracts' => array_slice($overBudgetContracts, 0, $limit),
            'overrun_contracts' => array_slice($overrunContracts, 0, $limit),
        ];
    }

    /**
     * Get contract cost overruns table data for a tenant
     * 
     * Round 49: Full-page Cost Overruns Table
     * 
     * Returns paginated, sortable list of contracts with overruns (budget or actual).
     * Each contract includes all metrics (budget_total, actual_total, diffs, overrun_amount).
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Optional filters:
     *   - search: Search in code or name
     *   - status: Filter by contract status (active|completed|cancelled)
     *   - client_id: Filter by client ID
     *   - project_id: Filter by project ID
     *   - min_overrun_amount: Minimum overrun_amount to include (positive number)
     *   - type: budget|actual|both (default: both)
     * @param array $pagination Pagination options:
     *   - page: Page number (default: 1)
     *   - per_page: Items per page (default: 25, max: 100)
     * @param array $sort Sort options:
     *   - sort_by: code|overrun_amount|budget_vs_contract_diff (default: overrun_amount)
     *   - sort_direction: asc|desc (default: desc)
     * @return array Structure:
     *   - items: Array of contract overrun data, each containing:
     *     - id, code, name, status
     *     - client: { id, name }
     *     - project: { id, name } (nullable)
     *     - contract_value, budget_total, actual_total
     *     - currency: Contract currency (default: USD)
     *     - budget_vs_contract_diff: budget_total - contract_value
     *     - contract_vs_actual_diff: contract_value - actual_total
     *     - overrun_amount: actual_total - contract_value (only > 0 when overrun)
     *   - pagination: { total, per_page, current_page, last_page }
     */
    public function getContractCostOverrunsTableForTenant(
        string $tenantId,
        array $filters = [],
        array $pagination = [],
        array $sort = []
    ): array {
        // Get contracts with total_value != null (only these can have overruns)
        $query = Contract::where('tenant_id', $tenantId)
            ->whereNotNull('total_value')
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $contracts = $query->with(['client', 'project'])->get();

        $items = [];
        $minOverrunAmount = isset($filters['min_overrun_amount']) ? (float) $filters['min_overrun_amount'] : null;
        $type = $filters['type'] ?? 'both';

        foreach ($contracts as $contract) {
            $contractValue = (float) $contract->total_value;

            // Calculate budget_total: sum of active budget lines
            $budgetTotal = (float) $contract->budgetLines()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount') ?? 0.0;

            // Calculate actual_total: sum of active expenses
            $actualTotal = (float) $contract->expenses()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('amount') ?? 0.0;

            // Calculate differences
            $budgetVsContractDiff = $budgetTotal - $contractValue;
            $contractVsActualDiff = $contractValue - $actualTotal;
            $overrunAmount = $actualTotal > $contractValue ? ($actualTotal - $contractValue) : 0.0;

            // Apply type filter
            $hasBudgetOverrun = $budgetTotal > $contractValue;
            $hasActualOverrun = $actualTotal > $contractValue;

            $include = false;
            if ($type === 'budget' && $hasBudgetOverrun) {
                $include = true;
            } elseif ($type === 'actual' && $hasActualOverrun) {
                $include = true;
            } elseif ($type === 'both' && ($hasBudgetOverrun || $hasActualOverrun)) {
                $include = true;
            }

            if (!$include) {
                continue;
            }

            // Apply min_overrun_amount filter (only for actual overruns)
            if ($minOverrunAmount !== null && $overrunAmount < $minOverrunAmount) {
                continue;
            }

            $items[] = [
                'id' => (string) $contract->id,
                'code' => $contract->code,
                'name' => $contract->name,
                'status' => $contract->status,
                'client' => $contract->client ? [
                    'id' => (string) $contract->client->id,
                    'name' => $contract->client->name,
                ] : null,
                'project' => $contract->project ? [
                    'id' => (string) $contract->project->id,
                    'name' => $contract->project->name,
                ] : null,
                'currency' => $contract->currency ?? 'USD', // Round 50: Add currency field
                'contract_value' => $contractValue,
                'budget_total' => $budgetTotal,
                'actual_total' => $actualTotal,
                'budget_vs_contract_diff' => $budgetVsContractDiff,
                'contract_vs_actual_diff' => $contractVsActualDiff,
                'overrun_amount' => $overrunAmount,
            ];
        }

        // Sort
        $sortBy = $sort['sort_by'] ?? 'overrun_amount';
        $sortDirection = $sort['sort_direction'] ?? 'desc';

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            // Round 50: Handle string sorting for 'code' field
            if ($sortBy === 'code') {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                $result = strcmp($aValue, $bValue);
            } else {
                $aValue = $a[$sortBy] ?? 0;
                $bValue = $b[$sortBy] ?? 0;
                $result = $bValue <=> $aValue; // Default desc for numeric
            }
            
            if ($sortDirection === 'asc') {
                $result = -$result;
            }
            return $result;
        });

        // Pagination
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($pagination['per_page'] ?? 25)));
        $total = count($items);
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedItems = array_slice($items, $offset, $perPage);

        return [
            'items' => $paginatedItems,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Export contract cost overruns to CSV
     * 
     * Round 49: Full-page Cost Overruns Export
     * Round 51: Added sort support to match table sorting
     * 
     * Exports contracts with overruns (budget or actual) to CSV file.
     * Uses same filters and sort as table endpoint.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Same filters as getContractCostOverrunsTableForTenant
     * @param array $sort Sort options (sort_by, sort_direction) - optional, defaults to overrun_amount desc
     * @return StreamedResponse CSV file download
     */
    public function exportContractCostOverrunsForTenant(string $tenantId, array $filters = [], array $sort = []): StreamedResponse
    {
        // Get all items (no pagination for export, but use sort if provided)
        $tableData = $this->getContractCostOverrunsTableForTenant($tenantId, $filters, [], $sort);
        $items = $tableData['items'];

        $filename = 'tenant-' . $tenantId . '-cost-overruns-' . now()->format('Y-m-d_H-i-s') . '.csv';

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
                'Code',
                'Name',
                'Status',
                'ClientName',
                'ProjectName',
                'Currency', // Round 50: Add Currency column
                'ContractValue',
                'BudgetTotal',
                'BudgetVsContractDiff',
                'ActualTotal',
                'ContractVsActualDiff',
                'OverrunAmount',
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item['code'],
                    $item['name'],
                    $item['status'],
                    $item['client']['name'] ?? '',
                    $item['project']['name'] ?? '',
                    $item['currency'] ?? 'USD', // Round 50: Add currency value
                    $item['contract_value'],
                    $item['budget_total'],
                    $item['budget_vs_contract_diff'],
                    $item['actual_total'],
                    $item['contract_vs_actual_diff'],
                    $item['overrun_amount'],
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}

