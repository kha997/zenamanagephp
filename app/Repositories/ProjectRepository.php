<?php declare(strict_types=1);

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * ProjectRepository - Data access layer cho Project
 */
class ProjectRepository
{
    /**
     * Lấy projects với filtering
     */
    public function getProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters);
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Lấy tất cả projects (không phân trang)
     */
    public function getAllProjects(array $filters = []): Collection
    {
        $query = $this->buildQuery($filters);
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Lấy project by ID với relationships
     */
    public function getProjectById(string $id, array $relationships = []): ?Project
    {
        $query = Project::query();
        
        if (!empty($relationships)) {
            $query->with($relationships);
        }
        
        return $query->find($id);
    }

    /**
     * Lấy projects cho dropdown (chỉ id, name, code)
     */
    public function getProjectsForDropdown(string $tenantId = null): Collection
    {
        $query = Project::select('id', 'name', 'code', 'status')
                        ->where('status', '!=', Project::STATUS_ARCHIVED);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * Lấy projects active
     */
    public function getActiveProjects(string $tenantId = null): Collection
    {
        $query = Project::active();
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])->get();
    }

    /**
     * Lấy projects overdue
     */
    public function getOverdueProjects(string $tenantId = null): Collection
    {
        $query = Project::overdue();
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])->get();
    }

    /**
     * Lấy projects theo user
     */
    public function getProjectsByUser(string $userId, string $tenantId = null): Collection
    {
        $user = User::find($userId);
        
        if (!$user) {
            return collect();
        }
        
        $query = Project::query();
        
        // SuperAdmin và Admin có thể xem tất cả projects
        if ($user->hasRole(['SuperAdmin', 'Admin'])) {
            // Không cần filter gì thêm
        } else {
            // Chỉ xem projects mà user là team member
            $query->whereHas('teamMembers', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])->get();
    }

    /**
     * Tìm kiếm projects
     */
    public function searchProjects(string $search, string $tenantId = null, int $limit = 10): Collection
    {
        $query = Project::search($search);
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])
                    ->limit($limit)
                    ->get();
    }

    /**
     * Lấy project statistics
     */
    public function getProjectStatistics(string $tenantId = null): array
    {
        $query = Project::query();
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return [
            'total' => $query->count(),
            'active' => $query->clone()->active()->count(),
            'completed' => $query->clone()->byStatus(Project::STATUS_COMPLETED)->count(),
            'overdue' => $query->clone()->overdue()->count(),
            'on_hold' => $query->clone()->byStatus(Project::STATUS_ON_HOLD)->count(),
            'cancelled' => $query->clone()->byStatus(Project::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Lấy projects theo date range
     */
    public function getProjectsByDateRange(string $startDate, string $endDate, string $tenantId = null): Collection
    {
        $query = Project::inDateRange($startDate, $endDate);
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])->get();
    }

    /**
     * Lấy projects theo priority
     */
    public function getProjectsByPriority(string $priority, string $tenantId = null): Collection
    {
        $query = Project::byPriority($priority);
        
        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        
        return $query->with(['client', 'projectManager'])->get();
    }

    /**
     * Build query với filters
     */
    private function buildQuery(array $filters): Builder
    {
        $query = Project::with(['client', 'projectManager', 'teamMembers']);
        
        // Tenant filter
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
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
        
        // Search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        
        // Overdue filter
        if (!empty($filters['overdue'])) {
            $query->overdue();
        }
        
        // Date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }
        
        // User access control
        if (!empty($filters['user_id'])) {
            $user = User::find($filters['user_id']);
            if ($user && !$user->hasRole(['SuperAdmin', 'Admin'])) {
                $query->whereHas('teamMembers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }
        
        // Client filter
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        
        // Project Manager filter
        if (!empty($filters['pm_id'])) {
            $query->where('pm_id', $filters['pm_id']);
        }
        
        return $query;
    }
}
