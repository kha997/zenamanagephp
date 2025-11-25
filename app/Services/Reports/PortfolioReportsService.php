<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use App\Models\Client;

/**
 * Service for computing Project Cost Portfolio reports
 * 
 * Round 51: Project Cost Portfolio
 * 
 * Provides aggregated cost metrics per project, scoped by tenant_id for multi-tenant isolation.
 */
class PortfolioReportsService
{
    /**
     * Get project cost portfolio for a tenant
     * 
     * Round 51: Project Cost Portfolio
     * 
     * Returns paginated, sortable list of projects with aggregated cost metrics.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Optional filters:
     *   - search: Search in project_code or project_name
     *   - client_id: Filter by client ID
     *   - status: Filter by project status (active|completed|archived|on_hold|cancelled|planning)
     *   - min_overrun_amount: Minimum overrun_amount_total to include (positive number)
     * @param array $pagination Pagination options:
     *   - page: Page number (default: 1)
     *   - per_page: Items per page (default: 25, max: 100)
     * @param array $sort Sort options:
     *   - sort_by: project_code|project_name|contracts_value_total|overrun_amount_total (default: overrun_amount_total)
     *   - sort_direction: asc|desc (default: desc)
     * @return array Structure:
     *   - items: Array of project cost data, each containing:
     *     - project_id, project_code, project_name
     *     - client: { id, name } (nullable)
     *     - contracts_count: Number of contracts (not deleted)
     *     - contracts_value_total: Sum of contract total_value (only contracts with total_value != null)
     *     - budget_total: Sum of active budget lines across all contracts
     *     - actual_total: Sum of active expenses across all contracts
     *     - overrun_amount_total: Sum of max(0, actual_total - contract_value) per contract
     *     - over_budget_contracts_count: Count of contracts where budget_total > contract_value
     *     - overrun_contracts_count: Count of contracts where actual_total > contract_value
     *     - currency: Project/contract currency (default: USD, simplified - uses first contract currency)
     *   - pagination: { total, per_page, current_page, last_page }
     */
    public function getProjectCostPortfolioForTenant(
        string $tenantId,
        array $filters = [],
        array $pagination = [],
        array $sort = []
    ): array {
        // Base query for projects
        $query = Project::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $projects = $query->with(['client'])->get();

        $items = [];
        $minOverrunAmount = isset($filters['min_overrun_amount']) ? (float) $filters['min_overrun_amount'] : null;

        foreach ($projects as $project) {
            // Get all contracts for this project (not deleted)
            $contracts = Contract::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->get();

            $contractsCount = $contracts->count();
            $contractsValueTotal = 0.0;
            $budgetTotal = 0.0;
            $actualTotal = 0.0;
            $overrunAmountTotal = 0.0;
            $overBudgetContractsCount = 0;
            $overrunContractsCount = 0;
            $currency = 'USD'; // Default, will use first contract's currency if available

            foreach ($contracts as $contract) {
                // Get contract value (only if not null)
                $contractValue = $contract->total_value !== null ? (float) $contract->total_value : null;
                if ($contractValue !== null) {
                    $contractsValueTotal += $contractValue;
                }

                // Get currency from first contract (simplified approach)
                if ($currency === 'USD' && $contract->currency) {
                    $currency = $contract->currency;
                }

                // Calculate budget_total: sum of active budget lines for this contract
                $contractBudgetTotal = (float) $contract->budgetLines()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_amount') ?? 0.0;

                // Calculate actual_total: sum of active expenses for this contract
                $contractActualTotal = (float) $contract->expenses()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount') ?? 0.0;

                $budgetTotal += $contractBudgetTotal;
                $actualTotal += $contractActualTotal;

                // Check over budget
                if ($contractValue !== null && $contractBudgetTotal > $contractValue) {
                    $overBudgetContractsCount++;
                }

                // Check overrun (only for contracts with total_value != null)
                if ($contractValue !== null) {
                    $contractOverrunAmount = max(0, $contractActualTotal - $contractValue);
                    $overrunAmountTotal += $contractOverrunAmount;

                    if ($contractActualTotal > $contractValue) {
                        $overrunContractsCount++;
                    }
                }
            }

            // Apply min_overrun_amount filter
            if ($minOverrunAmount !== null && $overrunAmountTotal < $minOverrunAmount) {
                continue;
            }

            $items[] = [
                'project_id' => (string) $project->id,
                'project_code' => $project->code,
                'project_name' => $project->name,
                'client' => $project->client ? [
                    'id' => (string) $project->client->id,
                    'name' => $project->client->name,
                ] : null,
                'contracts_count' => $contractsCount,
                'contracts_value_total' => $contractsValueTotal > 0 ? $contractsValueTotal : null,
                'budget_total' => $budgetTotal,
                'actual_total' => $actualTotal,
                'overrun_amount_total' => $overrunAmountTotal,
                'over_budget_contracts_count' => $overBudgetContractsCount,
                'overrun_contracts_count' => $overrunContractsCount,
                'currency' => $currency,
            ];
        }

        // Sort
        $sortBy = $sort['sort_by'] ?? 'overrun_amount_total';
        $sortDirection = $sort['sort_direction'] ?? 'desc';

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            // Handle string sorting for project_code and project_name
            if ($sortBy === 'project_code' || $sortBy === 'project_name') {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                $result = strcmp($aValue, $bValue);
            } else {
                // Numeric sorting
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
     * Get project cost summary for a specific project (internal use)
     * 
     * Round 67: Project Overview Cockpit
     * 
     * Returns aggregated cost metrics for a single project, scoped by tenant_id.
     * This is a helper method for ProjectOverviewService to avoid querying the entire portfolio.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param string $projectId Project ID to get summary for
     * @return array|null Structure:
     *   - contracts_count: Number of contracts (not deleted)
     *   - contracts_value_total: Sum of contract total_value (only contracts with total_value != null)
     *   - budget_total: Sum of active budget lines across all contracts
     *   - actual_total: Sum of active expenses across all contracts
     *   - overrun_amount_total: Sum of max(0, actual_total - contract_value) per contract
     *   - over_budget_contracts_count: Count of contracts where budget_total > contract_value
     *   - overrun_contracts_count: Count of contracts where actual_total > contract_value
     *   - currency: Project/contract currency (default: USD, simplified - uses first contract currency)
     *   Returns null if project not found or has no contracts
     */
    public function getProjectCostSummaryForTenant(string $tenantId, string $projectId): ?array
    {
        // Verify project exists and belongs to tenant
        $project = Project::where('tenant_id', $tenantId)
            ->where('id', $projectId)
            ->whereNull('deleted_at')
            ->first();

        if (!$project) {
            return null;
        }

        // Get all contracts for this project (not deleted)
        $contracts = Contract::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->get();

        $contractsCount = $contracts->count();
        
        // If no contracts, return null
        if ($contractsCount === 0) {
            return null;
        }

        $contractsValueTotal = 0.0;
        $budgetTotal = 0.0;
        $actualTotal = 0.0;
        $overrunAmountTotal = 0.0;
        $overBudgetContractsCount = 0;
        $overrunContractsCount = 0;
        $currency = 'USD'; // Default, will use first contract's currency if available

        foreach ($contracts as $contract) {
            // Get contract value (only if not null)
            $contractValue = $contract->total_value !== null ? (float) $contract->total_value : null;
            if ($contractValue !== null) {
                $contractsValueTotal += $contractValue;
            }

            // Get currency from first contract (simplified approach)
            if ($currency === 'USD' && $contract->currency) {
                $currency = $contract->currency;
            }

            // Calculate budget_total: sum of active budget lines for this contract
            $contractBudgetTotal = (float) $contract->budgetLines()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount') ?? 0.0;

            // Calculate actual_total: sum of active expenses for this contract
            $contractActualTotal = (float) $contract->expenses()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('amount') ?? 0.0;

            $budgetTotal += $contractBudgetTotal;
            $actualTotal += $contractActualTotal;

            // Check over budget
            if ($contractValue !== null && $contractBudgetTotal > $contractValue) {
                $overBudgetContractsCount++;
            }

            // Check overrun (only for contracts with total_value != null)
            if ($contractValue !== null) {
                $contractOverrunAmount = max(0, $contractActualTotal - $contractValue);
                $overrunAmountTotal += $contractOverrunAmount;

                if ($contractActualTotal > $contractValue) {
                    $overrunContractsCount++;
                }
            }
        }

        return [
            'contracts_count' => $contractsCount,
            'contracts_value_total' => $contractsValueTotal > 0 ? $contractsValueTotal : null,
            'budget_total' => $budgetTotal,
            'actual_total' => $actualTotal,
            'overrun_amount_total' => $overrunAmountTotal,
            'over_budget_contracts_count' => $overBudgetContractsCount,
            'overrun_contracts_count' => $overrunContractsCount,
            'currency' => $currency,
        ];
    }

    /**
     * Get client cost portfolio for a tenant
     * 
     * Round 53: Client Cost Portfolio
     * 
     * Returns paginated, sortable list of clients with aggregated cost metrics.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Optional filters:
     *   - search: Search in client name or code (if exists)
     *   - client_id: Filter by specific client ID (for drill-down)
     *   - status: Filter by contract status (active|completed|cancelled|draft)
     *   - min_overrun_amount: Minimum overrun_amount_total to include (positive number)
     * @param array $pagination Pagination options:
     *   - page: Page number (default: 1)
     *   - per_page: Items per page (default: 25, max: 100)
     * @param array $sort Sort options:
     *   - sort_by: client_name|contracts_value_total|overrun_amount_total|contracts_count (default: overrun_amount_total)
     *   - sort_direction: asc|desc (default: desc)
     * @return array Structure:
     *   - items: Array of client cost data, each containing:
     *     - client_id, client_code (optional), client_name
     *     - projects_count: Number of unique projects with contracts
     *     - contracts_count: Number of contracts (not deleted)
     *     - contracts_value_total: Sum of contract total_value (only contracts with total_value != null)
     *     - budget_total: Sum of active budget lines across all contracts
     *     - actual_total: Sum of active expenses across all contracts
     *     - overrun_amount_total: Sum of max(0, actual_total - contract_value) per contract
     *     - over_budget_contracts_count: Count of contracts where budget_total > contract_value
     *     - overrun_contracts_count: Count of contracts where actual_total > contract_value
     *     - currency: Contract currency (default: USD, simplified - uses first contract currency)
     *   - pagination: { total, per_page, current_page, last_page }
     */
    public function getClientCostPortfolioForTenant(
        string $tenantId,
        array $filters = [],
        array $pagination = [],
        array $sort = []
    ): array {
        // Base query for clients
        $query = Client::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['client_id'])) {
            $query->where('id', $filters['client_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        $clients = $query->get();

        $items = [];
        $minOverrunAmount = isset($filters['min_overrun_amount']) ? (float) $filters['min_overrun_amount'] : null;
        $statusFilter = $filters['status'] ?? null;

        foreach ($clients as $client) {
            // Get all contracts for this client (not deleted)
            $contractsQuery = Contract::where('tenant_id', $tenantId)
                ->where('client_id', $client->id)
                ->whereNull('deleted_at');

            // Apply status filter if provided
            if ($statusFilter) {
                $contractsQuery->where('status', $statusFilter);
            }

            $contracts = $contractsQuery->get();

            $contractsCount = $contracts->count();
            $contractsValueTotal = 0.0;
            $budgetTotal = 0.0;
            $actualTotal = 0.0;
            $overrunAmountTotal = 0.0;
            $overBudgetContractsCount = 0;
            $overrunContractsCount = 0;
            $currency = 'USD'; // Default, will use first contract's currency if available
            $projectIds = [];

            foreach ($contracts as $contract) {
                // Track unique project IDs
                if ($contract->project_id) {
                    $projectIds[$contract->project_id] = true;
                }

                // Get contract value (only if not null)
                $contractValue = $contract->total_value !== null ? (float) $contract->total_value : null;
                if ($contractValue !== null) {
                    $contractsValueTotal += $contractValue;
                }

                // Get currency from first contract (simplified approach)
                if ($currency === 'USD' && $contract->currency) {
                    $currency = $contract->currency;
                }

                // Calculate budget_total: sum of active budget lines for this contract
                $contractBudgetTotal = (float) $contract->budgetLines()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_amount') ?? 0.0;

                // Calculate actual_total: sum of active expenses for this contract
                $contractActualTotal = (float) $contract->expenses()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount') ?? 0.0;

                $budgetTotal += $contractBudgetTotal;
                $actualTotal += $contractActualTotal;

                // Check over budget
                if ($contractValue !== null && $contractBudgetTotal > $contractValue) {
                    $overBudgetContractsCount++;
                }

                // Check overrun (only for contracts with total_value != null)
                if ($contractValue !== null) {
                    $contractOverrunAmount = max(0, $contractActualTotal - $contractValue);
                    $overrunAmountTotal += $contractOverrunAmount;

                    if ($contractActualTotal > $contractValue) {
                        $overrunContractsCount++;
                    }
                }
            }

            // Apply min_overrun_amount filter
            if ($minOverrunAmount !== null && $overrunAmountTotal < $minOverrunAmount) {
                continue;
            }

            $items[] = [
                'client_id' => (string) $client->id,
                'client_code' => null, // Client model doesn't have code field currently
                'client_name' => $client->name,
                'projects_count' => count($projectIds),
                'contracts_count' => $contractsCount,
                'contracts_value_total' => $contractsValueTotal > 0 ? $contractsValueTotal : null,
                'budget_total' => $budgetTotal,
                'actual_total' => $actualTotal,
                'overrun_amount_total' => $overrunAmountTotal,
                'over_budget_contracts_count' => $overBudgetContractsCount,
                'overrun_contracts_count' => $overrunContractsCount,
                'currency' => $currency,
            ];
        }

        // Sort
        $sortBy = $sort['sort_by'] ?? 'overrun_amount_total';
        $sortDirection = $sort['sort_direction'] ?? 'desc';

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            // Handle string sorting for client_name
            if ($sortBy === 'client_name') {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                $result = strcmp($aValue, $bValue);
            } else {
                // Numeric sorting
                $aValue = $a[$sortBy] ?? 0;
                $bValue = $b[$sortBy] ?? 0;
                // Handle null values for contracts_value_total
                if ($sortBy === 'contracts_value_total') {
                    $aValue = $aValue ?? 0;
                    $bValue = $bValue ?? 0;
                }
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
     * Get project cost portfolio for export (no pagination)
     * 
     * Round 66: Project Cost Portfolio Export
     * 
     * Returns all items matching filters, sorted, without pagination.
     * Reuses getProjectCostPortfolioForTenant logic but returns full dataset.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Same filters as getProjectCostPortfolioForTenant
     * @param array $sort Sort options (sort_by, sort_direction)
     * @return array Array of project cost data items (same structure as getProjectCostPortfolioForTenant items)
     */
    public function getProjectCostPortfolioForTenantExport(
        string $tenantId,
        array $filters = [],
        array $sort = []
    ): array {
        // Base query for projects
        $query = Project::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $projects = $query->with(['client'])->get();

        $items = [];
        $minOverrunAmount = isset($filters['min_overrun_amount']) ? (float) $filters['min_overrun_amount'] : null;

        foreach ($projects as $project) {
            // Get all contracts for this project (not deleted)
            $contracts = Contract::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->whereNull('deleted_at')
                ->get();

            $contractsCount = $contracts->count();
            $contractsValueTotal = 0.0;
            $budgetTotal = 0.0;
            $actualTotal = 0.0;
            $overrunAmountTotal = 0.0;
            $overBudgetContractsCount = 0;
            $overrunContractsCount = 0;
            $currency = 'USD'; // Default, will use first contract's currency if available

            foreach ($contracts as $contract) {
                // Get contract value (only if not null)
                $contractValue = $contract->total_value !== null ? (float) $contract->total_value : null;
                if ($contractValue !== null) {
                    $contractsValueTotal += $contractValue;
                }

                // Get currency from first contract (simplified approach)
                if ($currency === 'USD' && $contract->currency) {
                    $currency = $contract->currency;
                }

                // Calculate budget_total: sum of active budget lines for this contract
                $contractBudgetTotal = (float) $contract->budgetLines()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_amount') ?? 0.0;

                // Calculate actual_total: sum of active expenses for this contract
                $contractActualTotal = (float) $contract->expenses()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount') ?? 0.0;

                $budgetTotal += $contractBudgetTotal;
                $actualTotal += $contractActualTotal;

                // Check over budget
                if ($contractValue !== null && $contractBudgetTotal > $contractValue) {
                    $overBudgetContractsCount++;
                }

                // Check overrun (only for contracts with total_value != null)
                if ($contractValue !== null) {
                    $contractOverrunAmount = max(0, $contractActualTotal - $contractValue);
                    $overrunAmountTotal += $contractOverrunAmount;

                    if ($contractActualTotal > $contractValue) {
                        $overrunContractsCount++;
                    }
                }
            }

            // Apply min_overrun_amount filter
            if ($minOverrunAmount !== null && $overrunAmountTotal < $minOverrunAmount) {
                continue;
            }

            $items[] = [
                'project_id' => (string) $project->id,
                'project_code' => $project->code,
                'project_name' => $project->name,
                'client' => $project->client ? [
                    'id' => (string) $project->client->id,
                    'name' => $project->client->name,
                ] : null,
                'contracts_count' => $contractsCount,
                'contracts_value_total' => $contractsValueTotal > 0 ? $contractsValueTotal : null,
                'budget_total' => $budgetTotal,
                'actual_total' => $actualTotal,
                'overrun_amount_total' => $overrunAmountTotal,
                'over_budget_contracts_count' => $overBudgetContractsCount,
                'overrun_contracts_count' => $overrunContractsCount,
                'currency' => $currency,
            ];
        }

        // Sort
        $sortBy = $sort['sort_by'] ?? 'overrun_amount_total';
        $sortDirection = $sort['sort_direction'] ?? 'desc';

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            // Handle string sorting for project_code and project_name
            if ($sortBy === 'project_code' || $sortBy === 'project_name') {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                $result = strcmp($aValue, $bValue);
            } else {
                // Numeric sorting
                $aValue = $a[$sortBy] ?? 0;
                $bValue = $b[$sortBy] ?? 0;
                $result = $bValue <=> $aValue; // Default desc for numeric
            }
            
            if ($sortDirection === 'asc') {
                $result = -$result;
            }
            return $result;
        });

        return $items;
    }

    /**
     * Get client cost portfolio for export (no pagination)
     * 
     * Round 66: Client Cost Portfolio Export
     * 
     * Returns all items matching filters, sorted, without pagination.
     * Reuses getClientCostPortfolioForTenant logic but returns full dataset.
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @param array $filters Same filters as getClientCostPortfolioForTenant
     * @param array $sort Sort options (sort_by, sort_direction)
     * @return array Array of client cost data items (same structure as getClientCostPortfolioForTenant items)
     */
    public function getClientCostPortfolioForTenantExport(
        string $tenantId,
        array $filters = [],
        array $sort = []
    ): array {
        // Base query for clients
        $query = Client::where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['client_id'])) {
            $query->where('id', $filters['client_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        $clients = $query->get();

        $items = [];
        $minOverrunAmount = isset($filters['min_overrun_amount']) ? (float) $filters['min_overrun_amount'] : null;
        $statusFilter = $filters['status'] ?? null;

        foreach ($clients as $client) {
            // Get all contracts for this client (not deleted)
            $contractsQuery = Contract::where('tenant_id', $tenantId)
                ->where('client_id', $client->id)
                ->whereNull('deleted_at');

            // Apply status filter if provided
            if ($statusFilter) {
                $contractsQuery->where('status', $statusFilter);
            }

            $contracts = $contractsQuery->get();

            $contractsCount = $contracts->count();
            $contractsValueTotal = 0.0;
            $budgetTotal = 0.0;
            $actualTotal = 0.0;
            $overrunAmountTotal = 0.0;
            $overBudgetContractsCount = 0;
            $overrunContractsCount = 0;
            $currency = 'USD'; // Default, will use first contract's currency if available
            $projectIds = [];

            foreach ($contracts as $contract) {
                // Track unique project IDs
                if ($contract->project_id) {
                    $projectIds[$contract->project_id] = true;
                }

                // Get contract value (only if not null)
                $contractValue = $contract->total_value !== null ? (float) $contract->total_value : null;
                if ($contractValue !== null) {
                    $contractsValueTotal += $contractValue;
                }

                // Get currency from first contract (simplified approach)
                if ($currency === 'USD' && $contract->currency) {
                    $currency = $contract->currency;
                }

                // Calculate budget_total: sum of active budget lines for this contract
                $contractBudgetTotal = (float) $contract->budgetLines()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_amount') ?? 0.0;

                // Calculate actual_total: sum of active expenses for this contract
                $contractActualTotal = (float) $contract->expenses()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount') ?? 0.0;

                $budgetTotal += $contractBudgetTotal;
                $actualTotal += $contractActualTotal;

                // Check over budget
                if ($contractValue !== null && $contractBudgetTotal > $contractValue) {
                    $overBudgetContractsCount++;
                }

                // Check overrun (only for contracts with total_value != null)
                if ($contractValue !== null) {
                    $contractOverrunAmount = max(0, $contractActualTotal - $contractValue);
                    $overrunAmountTotal += $contractOverrunAmount;

                    if ($contractActualTotal > $contractValue) {
                        $overrunContractsCount++;
                    }
                }
            }

            // Apply min_overrun_amount filter
            if ($minOverrunAmount !== null && $overrunAmountTotal < $minOverrunAmount) {
                continue;
            }

            $items[] = [
                'client_id' => (string) $client->id,
                'client_code' => null, // Client model doesn't have code field currently
                'client_name' => $client->name,
                'projects_count' => count($projectIds),
                'contracts_count' => $contractsCount,
                'contracts_value_total' => $contractsValueTotal > 0 ? $contractsValueTotal : null,
                'budget_total' => $budgetTotal,
                'actual_total' => $actualTotal,
                'overrun_amount_total' => $overrunAmountTotal,
                'over_budget_contracts_count' => $overBudgetContractsCount,
                'overrun_contracts_count' => $overrunContractsCount,
                'currency' => $currency,
            ];
        }

        // Sort
        $sortBy = $sort['sort_by'] ?? 'overrun_amount_total';
        $sortDirection = $sort['sort_direction'] ?? 'desc';

        usort($items, function ($a, $b) use ($sortBy, $sortDirection) {
            // Handle string sorting for client_name
            if ($sortBy === 'client_name') {
                $aValue = $a[$sortBy] ?? '';
                $bValue = $b[$sortBy] ?? '';
                $result = strcmp($aValue, $bValue);
            } else {
                // Numeric sorting
                $aValue = $a[$sortBy] ?? 0;
                $bValue = $b[$sortBy] ?? 0;
                // Handle null values for contracts_value_total
                if ($sortBy === 'contracts_value_total') {
                    $aValue = $aValue ?? 0;
                    $bValue = $bValue ?? 0;
                }
                $result = $bValue <=> $aValue; // Default desc for numeric
            }
            
            if ($sortDirection === 'asc') {
                $result = -$result;
            }
            return $result;
        });

        return $items;
    }
}

