<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ProjectSearchService - Advanced search and filtering for projects
 */
class ProjectSearchService
{
    /**
     * Advanced search with multiple criteria
     */
    public function advancedSearch(array $criteria, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildAdvancedQuery($criteria);
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Full-text search across project fields
     */
    public function fullTextSearch(string $searchTerm, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Full-text search
        $query->where(function (Builder $q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%");
        })->orWhereHas('projectManager', function (Builder $pmQuery) use ($searchTerm) {
            $pmQuery->where('name', 'like', "%{$searchTerm}%");
        });
        
        // Apply additional filters
        $query = $this->applyFilters($query, $filters);
        
        return $query->with(['client', 'projectManager', 'teamMembers'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Search by date range with advanced options
     */
    public function searchByDateRange(array $dateCriteria, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Date range filters
        if (!empty($dateCriteria['start_date_from'])) {
            $query->where('start_date', '>=', $dateCriteria['start_date_from']);
        }
        
        if (!empty($dateCriteria['start_date_to'])) {
            $query->where('start_date', '<=', $dateCriteria['start_date_to']);
        }
        
        if (!empty($dateCriteria['end_date_from'])) {
            $query->where('end_date', '>=', $dateCriteria['end_date_from']);
        }
        
        if (!empty($dateCriteria['end_date_to'])) {
            $query->where('end_date', '<=', $dateCriteria['end_date_to']);
        }
        
        // Created date range
        if (!empty($dateCriteria['created_from'])) {
            $query->where('created_at', '>=', $dateCriteria['created_from']);
        }
        
        if (!empty($dateCriteria['created_to'])) {
            $query->where('created_at', '<=', $dateCriteria['created_to']);
        }
        
        // Apply additional filters
        $query = $this->applyFilters($query, $filters);
        
        return $query->with(['client', 'projectManager', 'teamMembers'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Search by budget range
     */
    public function searchByBudget(array $budgetCriteria, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Budget filters
        if (!empty($budgetCriteria['budget_min'])) {
            $query->where('budget_planned', '>=', $budgetCriteria['budget_min']);
        }
        
        if (!empty($budgetCriteria['budget_max'])) {
            $query->where('budget_planned', '<=', $budgetCriteria['budget_max']);
        }
        
        if (!empty($budgetCriteria['budget_utilization_min'])) {
            $query->whereRaw('(budget_actual / budget_planned) * 100 >= ?', [$budgetCriteria['budget_utilization_min']]);
        }
        
        if (!empty($budgetCriteria['budget_utilization_max'])) {
            $query->whereRaw('(budget_actual / budget_planned) * 100 <= ?', [$budgetCriteria['budget_utilization_max']]);
        }
        
        // Apply additional filters
        $query = $this->applyFilters($query, $filters);
        
        return $query->with(['client', 'projectManager', 'teamMembers'])
                    ->orderBy('budget_planned', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Search by team members
     */
    public function searchByTeamMembers(array $teamCriteria, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Team member filters
        if (!empty($teamCriteria['team_member_ids'])) {
            $query->whereHas('teamMembers', function (Builder $q) use ($teamCriteria) {
                $q->whereIn('id', $teamCriteria['team_member_ids']);
            });
        }
        
        if (!empty($teamCriteria['project_manager_id'])) {
            $query->where('pm_id', $teamCriteria['project_manager_id']);
        }
        
        if (!empty($teamCriteria['client_id'])) {
            $query->where('client_id', $teamCriteria['client_id']);
        }
        
        if (!empty($teamCriteria['team_size_min'])) {
            $query->has('teamMembers', '>=', $teamCriteria['team_size_min']);
        }
        
        if (!empty($teamCriteria['team_size_max'])) {
            $query->has('teamMembers', '<=', $teamCriteria['team_size_max']);
        }
        
        // Apply additional filters
        $query = $this->applyFilters($query, $filters);
        
        return $query->with(['client', 'projectManager', 'teamMembers'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Search by progress and performance
     */
    public function searchByPerformance(array $performanceCriteria, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Performance filters
        if (!empty($performanceCriteria['progress_min'])) {
            $query->where('progress', '>=', $performanceCriteria['progress_min']);
        }
        
        if (!empty($performanceCriteria['progress_max'])) {
            $query->where('progress', '<=', $performanceCriteria['progress_max']);
        }
        
        if (!empty($performanceCriteria['overdue_only'])) {
            $query->overdue();
        }
        
        if (!empty($performanceCriteria['completed_only'])) {
            $query->where('status', Project::STATUS_COMPLETED);
        }
        
        if (!empty($performanceCriteria['active_only'])) {
            $query->active();
        }
        
        // Apply additional filters
        $query = $this->applyFilters($query, $filters);
        
        return $query->with(['client', 'projectManager', 'teamMembers'])
                    ->orderBy('progress', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(string $query, string $tenantId = null): array
    {
        $suggestions = [];
        
        // Project name suggestions
        $projectNames = Project::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('name', 'like', "%{$query}%")
            ->limit(5)
            ->pluck('name')
            ->toArray();
        
        $suggestions['projects'] = $projectNames;
        
        // Client name suggestions
        $clientNames = Project::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereHas('client', function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with('client')
            ->limit(5)
            ->get()
            ->pluck('client.name')
            ->unique()
            ->toArray();
        
        $suggestions['clients'] = $clientNames;
        
        // Project manager suggestions
        $pmNames = Project::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereHas('projectManager', function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with('projectManager')
            ->limit(5)
            ->get()
            ->pluck('projectManager.name')
            ->unique()
            ->toArray();
        
        $suggestions['project_managers'] = $pmNames;
        
        // Tag suggestions
        $tags = Project::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereJsonContains('tags', $query)
            ->limit(10)
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->filter(fn($tag) => str_contains(strtolower($tag), strtolower($query)))
            ->take(5)
            ->toArray();
        
        $suggestions['tags'] = array_values($tags);
        
        return $suggestions;
    }

    /**
     * Get filter options for advanced search
     */
    public function getFilterOptions(string $tenantId = null): array
    {
        $query = Project::query();
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return [
            'statuses' => Project::VALID_STATUSES,
            'priorities' => Project::VALID_PRIORITIES,
            'clients' => $query->with('client')
                              ->get()
                              ->pluck('client')
                              ->filter()
                              ->pluck('name', 'id')
                              ->unique()
                              ->toArray(),
            'project_managers' => $query->with('projectManager')
                                       ->get()
                                       ->pluck('projectManager')
                                       ->filter()
                                       ->pluck('name', 'id')
                                       ->unique()
                                       ->toArray(),
            'team_members' => User::query()
                                 ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                                 ->pluck('name', 'id')
                                 ->toArray(),
            'budget_ranges' => $this->getBudgetRanges($query),
            'date_ranges' => $this->getDateRanges($query)
        ];
    }

    /**
     * Build advanced query from criteria
     */
    private function buildAdvancedQuery(array $criteria): Builder
    {
        $query = Project::query();
        
        // Apply tenant isolation
        if (!empty($criteria['tenant_id'])) {
            $query->forTenant($criteria['tenant_id']);
        }
        
        // Text search
        if (!empty($criteria['search'])) {
            $query->search($criteria['search']);
        }
        
        // Status filter
        if (!empty($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $query->whereIn('status', $criteria['status']);
            } else {
                $query->byStatus($criteria['status']);
            }
        }
        
        // Priority filter
        if (!empty($criteria['priority'])) {
            if (is_array($criteria['priority'])) {
                $query->whereIn('priority', $criteria['priority']);
            } else {
                $query->byPriority($criteria['priority']);
            }
        }
        
        // Date filters
        if (!empty($criteria['start_date_from'])) {
            $query->where('start_date', '>=', $criteria['start_date_from']);
        }
        
        if (!empty($criteria['start_date_to'])) {
            $query->where('start_date', '<=', $criteria['start_date_to']);
        }
        
        if (!empty($criteria['end_date_from'])) {
            $query->where('end_date', '>=', $criteria['end_date_from']);
        }
        
        if (!empty($criteria['end_date_to'])) {
            $query->where('end_date', '<=', $criteria['end_date_to']);
        }
        
        // Budget filters
        if (!empty($criteria['budget_min'])) {
            $query->where('budget_planned', '>=', $criteria['budget_min']);
        }
        
        if (!empty($criteria['budget_max'])) {
            $query->where('budget_planned', '<=', $criteria['budget_max']);
        }
        
        // Progress filters
        if (!empty($criteria['progress_min'])) {
            $query->where('progress', '>=', $criteria['progress_min']);
        }
        
        if (!empty($criteria['progress_max'])) {
            $query->where('progress', '<=', $criteria['progress_max']);
        }
        
        // Team filters
        if (!empty($criteria['client_id'])) {
            $query->where('client_id', $criteria['client_id']);
        }
        
        if (!empty($criteria['pm_id'])) {
            $query->where('pm_id', $criteria['pm_id']);
        }
        
        if (!empty($criteria['team_member_id'])) {
            $query->whereHas('teamMembers', function (Builder $q) use ($criteria) {
                $q->where('id', $criteria['team_member_id']);
            });
        }
        
        // Special filters
        if (!empty($criteria['overdue'])) {
            $query->overdue();
        }
        
        if (!empty($criteria['active_only'])) {
            $query->active();
        }
        
        if (!empty($criteria['has_tasks'])) {
            $query->has('tasks');
        }
        
        if (!empty($criteria['has_milestones'])) {
            $query->has('milestones');
        }
        
        return $query;
    }

    /**
     * Apply additional filters to query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->byStatus($filters['status']);
            }
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $query->whereIn('priority', $filters['priority']);
            } else {
                $query->byPriority($filters['priority']);
            }
        }
        
        // Progress filter
        if (!empty($filters['progress_min'])) {
            $query->where('progress', '>=', $filters['progress_min']);
        }
        
        if (!empty($filters['progress_max'])) {
            $query->where('progress', '<=', $filters['progress_max']);
        }
        
        return $query;
    }

    /**
     * Get budget ranges for filter options
     */
    private function getBudgetRanges(Builder $query): array
    {
        $budgets = $query->selectRaw('MIN(budget_planned) as min, MAX(budget_planned) as max')
                         ->first();
        
        if (!$budgets || !$budgets->min) {
            return [];
        }
        
        $min = $budgets->min;
        $max = $budgets->max;
        
        return [
            'under_10k' => ['min' => 0, 'max' => 10000],
            '10k_to_50k' => ['min' => 10000, 'max' => 50000],
            '50k_to_100k' => ['min' => 50000, 'max' => 100000],
            '100k_to_500k' => ['min' => 100000, 'max' => 500000],
            'over_500k' => ['min' => 500000, 'max' => null]
        ];
    }

    /**
     * Get date ranges for filter options
     */
    private function getDateRanges(Builder $query): array
    {
        return [
            'last_week' => ['from' => now()->subWeek(), 'to' => now()],
            'last_month' => ['from' => now()->subMonth(), 'to' => now()],
            'last_3_months' => ['from' => now()->subMonths(3), 'to' => now()],
            'last_6_months' => ['from' => now()->subMonths(6), 'to' => now()],
            'last_year' => ['from' => now()->subYear(), 'to' => now()],
            'this_year' => ['from' => now()->startOfYear(), 'to' => now()->endOfYear()],
            'next_month' => ['from' => now(), 'to' => now()->addMonth()],
            'next_3_months' => ['from' => now(), 'to' => now()->addMonths(3)],
            'next_6_months' => ['from' => now(), 'to' => now()->addMonths(6)],
            'next_year' => ['from' => now(), 'to' => now()->addYear()]
        ];
    }
}
