<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\InteractionLogs\Models\InteractionLog;

/**
 * Service xử lý truy vấn cho Interaction Logs
 * 
 * Cung cấp các phương thức query với filtering, sorting và pagination
 * Đảm bảo security và visibility rules
 */
class InteractionLogQueryService
{
    /**
     * Lấy danh sách interaction logs với filtering và pagination
     * 
     * @param array $filters Các bộ lọc: project_id, task_id, type, visibility, etc.
     * @return LengthAwarePaginator
     */
    public function getLogsList(array $filters = []): LengthAwarePaginator
    {
        $query = InteractionLog::query()
            ->with(['project', 'linkedTask', 'creator'])
            ->orderBy('created_at', 'desc');
        
        // Apply filters
        $this->applyFilters($query, $filters);
        
        // Apply visibility rules
        $this->applyVisibilityRules($query, $filters);
        
        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $perPage = min($perPage, 100); // Giới hạn tối đa 100 items per page
        
        return $query->paginate($perPage);
    }

    /**
     * Lấy chi tiết interaction log
     * 
     * @param string $id ID của log
     * @return InteractionLog
     * @throws ModelNotFoundException
     */
    public function getLogDetail(string $id): InteractionLog
    {
        $log = InteractionLog::with(['project', 'linkedTask', 'creator'])
            ->findOrFail($id);
        
        // Kiểm tra quyền xem
        if (!$this->canViewLog($log)) {
            throw new \Exception('Không có quyền xem interaction log này');
        }
        
        return $log;
    }

    /**
     * Lấy logs theo project
     * 
     * @param string $projectId
     * @param array $filters
     * @return Collection
     */
    public function getLogsByProject(string $projectId, array $filters = []): Collection
    {
        $query = InteractionLog::query()
            ->with(['linkedTask', 'creator'])
            ->forProject($projectId)
            ->orderBy('created_at', 'desc');
        
        // Apply additional filters
        $this->applyFilters($query, $filters);
        
        // Apply visibility rules
        $this->applyVisibilityRules($query, $filters);
        
        // Limit results để tránh quá tải
        $limit = $filters['limit'] ?? 100;
        $limit = min($limit, 500);
        
        return $query->limit($limit)->get();
    }

    /**
     * Lấy logs theo task
     * 
     * @param string $taskId
     * @param array $filters
     * @return Collection
     */
    public function getLogsByTask(string $taskId, array $filters = []): Collection
    {
        $query = InteractionLog::query()
            ->with(['project', 'creator'])
            ->forTask($taskId)
            ->orderBy('created_at', 'desc');
        
        // Apply additional filters
        $this->applyFilters($query, $filters);
        
        // Apply visibility rules
        $this->applyVisibilityRules($query, $filters);
        
        return $query->get();
    }

    /**
     * Lấy logs hiển thị cho client (đã được approve)
     * 
     * @param string $projectId
     * @param array $filters
     * @return Collection
     */
    public function getClientVisibleLogs(string $projectId, array $filters = []): Collection
    {
        $query = InteractionLog::query()
            ->with(['linkedTask', 'creator'])
            ->forProject($projectId)
            ->clientVisible()
            ->orderBy('created_at', 'desc');
        
        // Apply type filter if provided
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }
        
        // Apply tag path filter if provided
        if (!empty($filters['tag_path'])) {
            $query->withTagPath($filters['tag_path']);
        }
        
        return $query->get();
    }

    /**
     * Tìm kiếm logs theo từ khóa
     * 
     * @param string $keyword
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function searchLogs(string $keyword, array $filters = []): LengthAwarePaginator
    {
        $query = InteractionLog::query()
            ->with(['project', 'linkedTask', 'creator'])
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            })
            ->orderBy('created_at', 'desc');
        
        // Apply filters
        $this->applyFilters($query, $filters);
        
        // Apply visibility rules
        $this->applyVisibilityRules($query, $filters);
        
        $perPage = $filters['per_page'] ?? 15;
        $perPage = min($perPage, 100);
        
        return $query->paginate($perPage);
    }

    /**
     * Lấy thống kê logs theo project
     * 
     * @param string $projectId
     * @return array
     */
    public function getLogStatistics(string $projectId): array
    {
        $baseQuery = InteractionLog::forProject($projectId);
        
        return [
            'total_logs' => $baseQuery->count(),
            'by_type' => $baseQuery->selectRaw('type, COUNT(*) as count')
                                  ->groupBy('type')
                                  ->pluck('count', 'type')
                                  ->toArray(),
            'by_visibility' => $baseQuery->selectRaw('visibility, COUNT(*) as count')
                                        ->groupBy('visibility')
                                        ->pluck('count', 'visibility')
                                        ->toArray(),
            'client_approved_count' => $baseQuery->where('visibility', InteractionLog::VISIBILITY_CLIENT)
                                                ->where('client_approved', true)
                                                ->count(),
            'pending_approval_count' => $baseQuery->where('visibility', InteractionLog::VISIBILITY_CLIENT)
                                                 ->where('client_approved', false)
                                                 ->count(),
        ];
    }

    /**
     * Apply các bộ lọc lên query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     */
    private function applyFilters($query, array $filters): void
    {
        // Filter by project
        if (!empty($filters['project_id'])) {
            $query->forProject($filters['project_id']);
        }
        
        // Filter by task
        if (!empty($filters['task_id'])) {
            $query->forTask($filters['task_id']);
        }
        
        // Filter by type
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }
        
        // Filter by visibility
        if (!empty($filters['visibility'])) {
            $query->withVisibility($filters['visibility']);
        }
        
        // Filter by tag path
        if (!empty($filters['tag_path'])) {
            $query->withTagPath($filters['tag_path']);
        }
        
        // Filter by creator
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }
        
        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        
        // Filter by client approval status
        if (isset($filters['client_approved'])) {
            $query->where('client_approved', $filters['client_approved']);
        }
    }

    /**
     * Apply visibility rules dựa trên user role và context
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     */
    private function applyVisibilityRules($query, array $filters): void
    {
        // Nếu là client view, chỉ hiển thị logs đã được approve
        if (!empty($filters['client_view']) && $filters['client_view'] === true) {
            $query->clientVisible();
            return;
        }
        
        
        // Tạm thời cho phép xem tất cả nếu không phải client view
        // Sẽ implement sau khi có RBAC system hoàn chỉnh
    }

    /**
     * Kiểm tra quyền xem log
     * 
     * @param InteractionLog $log
     * @return bool
     */
    private function canViewLog(InteractionLog $log): bool
    {
        
        // Tạm thời return true, sẽ implement sau khi có RBAC
        return true;
    }
}