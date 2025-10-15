<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Support\Facades\Cache;

class DashboardRepository
{
    /**
     * Get KPIs for tenant with 60s cache
     */
    public function kpisForTenant(string $tenantId): array
    {
        return Cache::remember("kpi:{$tenantId}", 60, function() use ($tenantId) {
            return [
                'projects' => [
                    'total' => Project::where('tenant_id', $tenantId)->count(),
                    'active' => Project::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                    'completed' => Project::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                ],
                'tasks' => [
                    'total' => Task::where('tenant_id', $tenantId)->count(),
                    'pending' => Task::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                    'in_progress' => Task::where('tenant_id', $tenantId)->where('status', 'in_progress')->count(),
                    'completed' => Task::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                ],
                'clients' => [
                    'total' => Client::where('tenant_id', $tenantId)->count(),
                    'leads' => Client::where('tenant_id', $tenantId)->where('lifecycle_stage', 'lead')->count(),
                    'customers' => Client::where('tenant_id', $tenantId)->where('lifecycle_stage', 'customer')->count(),
                ],
                'quotes' => [
                    'total' => Quote::where('tenant_id', $tenantId)->count(),
                    'pending' => Quote::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                    'accepted' => Quote::where('tenant_id', $tenantId)->where('status', 'accepted')->count(),
                    'total_value' => Quote::where('tenant_id', $tenantId)->sum('final_amount'),
                ],
            ];
        });
    }

    /**
     * Clear KPI cache for tenant
     */
    public function clearKpiCache(string $tenantId): void
    {
        Cache::forget("kpi:{$tenantId}");
    }

    /**
     * Clear all KPI caches
     */
    public function clearAllKpiCaches(): void
    {
        Cache::flush();
    }
}
