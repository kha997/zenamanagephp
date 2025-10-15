<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    public function create(array $data): Project
    {
        return Project::create($data);
    }
    
    public function findById(string $id, string $tenantId): Project
    {
        return Project::where('id', $id)
                     ->where('tenant_id', $tenantId)
                     ->firstOrFail();
    }
    
    public function update(string $id, array $data, string $tenantId): Project
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        $project->update($data);
        return $project;
    }
    
    public function delete(string $id, string $tenantId): bool
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        return $project->delete();
    }
    
    public function getList(array $filters = [], string $userId = null, string $tenantId = null): Collection
    {
        $query = Project::query();
        
        // MANDATORY: Every query must filter by tenant_id
        if (!$tenantId) {
            throw new \InvalidArgumentException('tenant_id is required for all queries');
        }
        
        $query->where('tenant_id', $tenantId);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->get();
    }
    
    public function getAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Project::query();
        
        // MANDATORY: Every query must filter by tenant_id
        if (!isset($filters['tenant_id']) || !$filters['tenant_id']) {
            throw new \InvalidArgumentException('tenant_id is required for all queries');
        }
        
        $query->where('tenant_id', $filters['tenant_id']);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->paginate($perPage);
    }
}