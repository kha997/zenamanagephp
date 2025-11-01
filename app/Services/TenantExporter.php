<?php

namespace App\Services;

use App\Http\Requests\TenantIndexRequest;
use App\Models\Tenant;

class TenantExporter
{
    /**
     * Get tenant rows for export using the same filtering logic as index
     */
    public static function rows(TenantIndexRequest $request): array
    {
        $validated = $request->validated();
        $query = Tenant::query()->withCount(['users', 'projects']);

        // Search
        if ($q = $validated['q'] ?? null) {
            $query->where(function($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('domain', 'like', "%$q%")
                  ->orWhereJsonContains('settings->ownerEmail', $q);
            });
        }
        
        // Filters
        if ($s = $validated['status'] ?? null)   $query->where('status', $s);
        if ($p = $validated['plan'] ?? null)     $query->where('settings->plan', $p);
        if ($from = $validated['from'] ?? null)  $query->where('created_at', '>=', $from);
        if ($to = $validated['to'] ?? null)      $query->where('created_at', '<=', $to);

        // Sort whitelist
        $allowedSort = ['name', 'created_at', 'updated_at', 'status'];
        $sort = $validated['sort'] ?? '-created_at';
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        
        if (in_array($col, $allowedSort)) {
            $query->orderBy($col, $dir);
        }

        // Get all results (no pagination for export)
        $tenants = $query->get();

        return $tenants->map(function ($tenant) {
            return [
                $tenant->id,
                $tenant->name,
                $tenant->domain,
                $tenant->status,
                $tenant->users_count,
                $tenant->projects_count,
                $tenant->settings['ownerName'] ?? '',
                $tenant->settings['ownerEmail'] ?? '',
                $tenant->settings['plan'] ?? '',
                $tenant->created_at?->format('Y-m-d H:i:s'),
                $tenant->updated_at?->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }
}
