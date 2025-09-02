<?php declare(strict_types=1);

namespace Src\InteractionLogs\Services;

use Src\Foundation\Helpers\AuthHelper;

use Src\InteractionLogs\Models\InteractionLog;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\Foundation\Events\EventBus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Service xử lý logic nghiệp vụ cho Interaction Logs
 * 
 * Quản lý việc tạo, cập nhật, xóa và approve interaction logs
 * Đảm bảo business rules về visibility và client approval
 */
class InteractionLogService
{
    /**
     * Tạo interaction log mới
     * 
     * @param array $data Dữ liệu log bao gồm: project_id, type, description, etc.
     * @return InteractionLog
     * @throws ValidationException
     */
    public function createLog(array $data): InteractionLog
    {
        // Validate dữ liệu đầu vào
        $this->validateLogData($data);
        
        // Kiểm tra project tồn tại
        $project = Project::findOrFail($data['project_id']);
        
        // Kiểm tra task nếu có linked_task_id
        if (!empty($data['linked_task_id'])) {
            $task = Task::where('id', $data['linked_task_id'])
                       ->where('project_id', $data['project_id'])
                       ->firstOrFail();
        }
        
        DB::beginTransaction();
        try {
            // Chuẩn bị dữ liệu để tạo log
            $logData = [
                'project_id' => $data['project_id'],
                'linked_task_id' => $data['linked_task_id'] ?? null,
                'type' => $data['type'],
                'description' => $data['description'],
                'tag_path' => $data['tag_path'] ?? null,
                'visibility' => $data['visibility'] ?? InteractionLog::VISIBILITY_INTERNAL,
                'client_approved' => false, // Mặc định chưa approve
                'created_by' => AuthHelper::id() ?? $data['created_by']
            ];
            
            // Tạo interaction log
            $log = InteractionLog::create($logData);
            
            // Dispatch event
            EventBus::dispatch('InteractionLog.Created', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'type' => $log->type,
                'visibility' => $log->visibility,
                'actor_id' => $log->created_by,
                'timestamp' => now()
            ]);
            
            DB::commit();
            
            return $log->load(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cập nhật interaction log
     * 
     * @param string $id ID của log
     * @param array $data Dữ liệu cập nhật
     * @return InteractionLog
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function updateLog(string $id, array $data): InteractionLog
    {
        $log = InteractionLog::findOrFail($id);
        
        // Validate dữ liệu cập nhật
        $this->validateLogData($data, true);
        
        // Kiểm tra quyền cập nhật (chỉ người tạo hoặc admin mới được cập nhật)
        if ($log->created_by !== AuthHelper::id() && !$this->hasAdminPermission()) {
            throw new \Exception('Không có quyền cập nhật interaction log này');
        }
        
        DB::beginTransaction();
        try {
            $oldData = $log->toArray();
            
            // Cập nhật các trường được phép
            $updateData = [];
            $allowedFields = ['description', 'tag_path', 'visibility'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            // Nếu thay đổi visibility thành client, reset client_approved
            if (isset($updateData['visibility']) && 
                $updateData['visibility'] === InteractionLog::VISIBILITY_CLIENT &&
                $log->visibility !== InteractionLog::VISIBILITY_CLIENT) {
                $updateData['client_approved'] = false;
            }
            
            $log->update($updateData);
            
            // Dispatch event nếu có thay đổi
            if (!empty($updateData)) {
                EventBus::dispatch('InteractionLog.Updated', [
                    'log_id' => $log->id,
                    'project_id' => $log->project_id,
                    'linked_task_id' => $log->linked_task_id,
                    'changed_fields' => array_keys($updateData),
                    'old_data' => $oldData,
                    'new_data' => $log->fresh()->toArray(),
                    'actor_id' => AuthHelper::id(),
                    'timestamp' => now()
                ]);
            }
            
            DB::commit();
            
            return $log->fresh(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xóa interaction log
     * 
     * @param string $id ID của log
     * @return bool
     * @throws ModelNotFoundException
     */
    public function deleteLog(string $id): bool
    {
        $log = InteractionLog::findOrFail($id);
        
        // Kiểm tra quyền xóa
        if ($log->created_by !== AuthHelper::id() && !$this->hasAdminPermission()) {
            throw new \Exception('Không có quyền xóa interaction log này');
        }
        
        DB::beginTransaction();
        try {
            // Dispatch event trước khi xóa
            EventBus::dispatch('InteractionLog.Deleted', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'type' => $log->type,
                'actor_id' => AuthHelper::id(),
                'timestamp' => now()
            ]);
            
            $result = $log->delete();
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve log để hiển thị cho client
     * 
     * @param string $id ID của log
     * @return InteractionLog
     * @throws ModelNotFoundException
     */
    public function approveForClient(string $id): InteractionLog
    {
        $log = InteractionLog::findOrFail($id);
        
        // Kiểm tra quyền approve (chỉ admin hoặc project manager)
        if (!$this->hasApprovalPermission($log->project_id)) {
            throw new \Exception('Không có quyền approve interaction log này');
        }
        
        // Chỉ approve được log có visibility = client
        if ($log->visibility !== InteractionLog::VISIBILITY_CLIENT) {
            throw new \Exception('Chỉ có thể approve log có visibility là client');
        }
        
        DB::beginTransaction();
        try {
            $log->update(['client_approved' => true]);
            
            // Dispatch event
            EventBus::dispatch('InteractionLog.ClientApproved', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'approved_by' => AuthHelper::id(),
                'timestamp' => now()
            ]);
            
            DB::commit();
            
            return $log->fresh(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Revoke client approval
     * 
     * @param string $id ID của log
     * @return InteractionLog
     * @throws ModelNotFoundException
     */
    public function revokeClientApproval(string $id): InteractionLog
    {
        $log = InteractionLog::findOrFail($id);
        
        // Kiểm tra quyền revoke
        if (!$this->hasApprovalPermission($log->project_id)) {
            throw new \Exception('Không có quyền revoke approval cho interaction log này');
        }
        
        DB::beginTransaction();
        try {
            $log->update(['client_approved' => false]);
            
            // Dispatch event
            EventBus::dispatch('InteractionLog.ClientApprovalRevoked', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'revoked_by' => AuthHelper::id(),
                'timestamp' => now()
            ]);
            
            DB::commit();
            
            return $log->fresh(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate dữ liệu interaction log
     * 
     * @param array $data
     * @param bool $isUpdate
     * @throws ValidationException
     */
    private function validateLogData(array $data, bool $isUpdate = false): void
    {
        $rules = [];
        
        if (!$isUpdate) {
            $rules = [
                'project_id' => 'required|string|exists:projects,id',
                'type' => 'required|string|in:' . implode(',', InteractionLog::VALID_TYPES),
                'description' => 'required|string|max:5000',
            ];
        }
        
        // Rules cho cả create và update
        $commonRules = [
            'linked_task_id' => 'nullable|string|exists:tasks,id',
            'tag_path' => 'nullable|string|max:500',
            'visibility' => 'nullable|string|in:' . implode(',', InteractionLog::VALID_VISIBILITIES),
        ];
        
        $rules = array_merge($rules, $commonRules);
        
        $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Kiểm tra quyền admin
     * 
     * @return bool
     */
    private function hasAdminPermission(): bool
    {
        // TODO: Implement proper RBAC check
        // Tạm thời return true, sẽ implement sau khi có RBAC
        return true;
    }

    /**
     * Kiểm tra quyền approve cho project
     * 
     * @param string $projectId
     * @return bool
     */
    private function hasApprovalPermission(string $projectId): bool
    {
        // TODO: Implement proper RBAC check for project-specific permissions
        // Tạm thời return true, sẽ implement sau khi có RBAC
        return true;
    }

    /**
     * Lấy danh sách interaction logs của project với phân trang
     * 
     * @param int $projectId ID của project
     * @param string|null $type Loại interaction log
     * @param string $visibility Mức độ hiển thị (internal/client)
     * @param int $perPage Số lượng items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProjectLogs(int $projectId, ?string $type = null, string $visibility = 'internal', int $perPage = 15)
    {
        $query = InteractionLog::query()
            ->with(['linkedTask', 'creator'])
            ->where('project_id', $projectId)
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by visibility
        if ($visibility === 'client') {
            $query->where('visibility', InteractionLog::VISIBILITY_CLIENT)
                  ->where('client_approved', true);
        } else {
            $query->where('visibility', InteractionLog::VISIBILITY_INTERNAL);
        }

        return $query->paginate($perPage);
    }

    /**
     * Lấy danh sách logs theo tag path với phân trang
     * 
     * @param int $projectId ID của project
     * @param string $tagPath Tag path để filter
     * @param int $perPage Số lượng items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLogsByTagPath(int $projectId, string $tagPath, int $perPage = 15)
    {
        return InteractionLog::query()
            ->with(['linkedTask', 'creator'])
            ->where('project_id', $projectId)
            ->where('tag_path', 'LIKE', "%{$tagPath}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Autocomplete cho tag_path trong project
     * 
     * @param int $projectId ID của project
     * @param string $query Từ khóa tìm kiếm
     * @param int $limit Số lượng gợi ý tối đa
     * @return array
     */
    public function autocompleteTagPath(int $projectId, string $query = '', int $limit = 10): array
    {
        $queryBuilder = InteractionLog::query()
            ->select('tag_path')
            ->where('project_id', $projectId)
            ->whereNotNull('tag_path')
            ->where('tag_path', '!=', '')
            ->distinct();

        if (!empty($query)) {
            $queryBuilder->where('tag_path', 'LIKE', "%{$query}%");
        }

        $results = $queryBuilder
            ->orderBy('tag_path')
            ->limit($limit)
            ->pluck('tag_path')
            ->toArray();

        return array_map(function($tagPath) {
            return [
                'value' => $tagPath,
                'label' => $tagPath
            ];
        }, $results);
    }

    /**
     * Lấy thống kê interaction logs của project
     * 
     * @param int $projectId ID của project
     * @param string|null $dateFrom Ngày bắt đầu (Y-m-d)
     * @param string|null $dateTo Ngày kết thúc (Y-m-d)
     * @return array
     */
    public function getProjectStats(int $projectId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = InteractionLog::query()->where('project_id', $projectId);

        // Apply date filters if provided
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totalLogs = $query->count();

        // Statistics by type
        $byType = $query->clone()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Statistics by visibility
        $byVisibility = $query->clone()
            ->selectRaw('visibility, COUNT(*) as count')
            ->groupBy('visibility')
            ->pluck('count', 'visibility')
            ->toArray();

        // Client approved logs
        $clientApproved = $query->clone()
            ->where('visibility', InteractionLog::VISIBILITY_CLIENT)
            ->where('client_approved', true)
            ->count();

        // Recent activity (last 7 days)
        $recentActivity = $query->clone()
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Top tag paths
        $topTagPaths = $query->clone()
            ->whereNotNull('tag_path')
            ->where('tag_path', '!=', '')
            ->selectRaw('tag_path, COUNT(*) as count')
            ->groupBy('tag_path')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'tag_path')
            ->toArray();

        return [
            'total_logs' => $totalLogs,
            'by_type' => $byType,
            'by_visibility' => $byVisibility,
            'client_approved' => $clientApproved,
            'recent_activity' => $recentActivity,
            'top_tag_paths' => $topTagPaths,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    /**
     * Cập nhật interaction log với instance
     * 
     * @param InteractionLog $log Instance của log
     * @param array $data Dữ liệu cập nhật
     * @return InteractionLog
     * @throws ValidationException
     */
    public function updateLogInstance(InteractionLog $log, array $data): InteractionLog
    {
        // Validate dữ liệu cập nhật
        $this->validateLogData($data, true);
        
        // Kiểm tra quyền cập nhật (chỉ người tạo hoặc admin mới được cập nhật)
        if ($log->created_by !== AuthHelper::id() && !$this->hasAdminPermission()) {
            throw new \Exception('Không có quyền cập nhật interaction log này');
        }
        
        DB::beginTransaction();
        try {
            $oldData = $log->toArray();
            
            // Cập nhật các trường được phép
            $updateData = [];
            $allowedFields = ['description', 'tag_path', 'visibility'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            // Nếu thay đổi visibility thành client, reset client_approved
            if (isset($updateData['visibility']) && 
                $updateData['visibility'] === InteractionLog::VISIBILITY_CLIENT &&
                $log->visibility !== InteractionLog::VISIBILITY_CLIENT) {
                $updateData['client_approved'] = false;
            }
            
            $log->update($updateData);
            
            // Dispatch event nếu có thay đổi
            if (!empty($updateData)) {
                EventBus::dispatch('InteractionLog.Updated', [
                    'log_id' => $log->id,
                    'project_id' => $log->project_id,
                    'linked_task_id' => $log->linked_task_id,
                    'changed_fields' => array_keys($updateData),
                    'old_data' => $oldData,
                    'new_data' => $log->fresh()->toArray(),
                    'actor_id' => AuthHelper::id(),
                    'timestamp' => now()
                ]);
            }
            
            DB::commit();
            
            return $log->fresh(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Xóa interaction log với instance
     * 
     * @param InteractionLog $log Instance của log
     * @return bool
     */
    public function deleteLogInstance(InteractionLog $log): bool
    {
        // Kiểm tra quyền xóa
        if ($log->created_by !== AuthHelper::id() && !$this->hasAdminPermission()) {
            throw new \Exception('Không có quyền xóa interaction log này');
        }
        
        DB::beginTransaction();
        try {
            // Dispatch event trước khi xóa
            EventBus::dispatch('InteractionLog.Deleted', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'type' => $log->type,
                'actor_id' => AuthHelper::id(),
                'timestamp' => now()
            ]);
            
            $result = $log->delete();
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve log để hiển thị cho client với instance
     * 
     * @param InteractionLog $log Instance của log
     * @return InteractionLog
     */
    public function approveForClientInstance(InteractionLog $log): InteractionLog
    {
        // Kiểm tra quyền approve (chỉ admin hoặc project manager)
        if (!$this->hasApprovalPermission($log->project_id)) {
            throw new \Exception('Không có quyền approve interaction log này');
        }
        
        // Chỉ approve được log có visibility = client
        if ($log->visibility !== InteractionLog::VISIBILITY_CLIENT) {
            throw new \Exception('Chỉ có thể approve log có visibility là client');
        }
        
        DB::beginTransaction();
        try {
            $log->update(['client_approved' => true]);
            
            // Dispatch event
            EventBus::dispatch('InteractionLog.ClientApproved', [
                'log_id' => $log->id,
                'project_id' => $log->project_id,
                'linked_task_id' => $log->linked_task_id,
                'approved_by' => AuthHelper::id(),
                'timestamp' => now()
            ]);
            
            DB::commit();
            
            return $log->fresh(['project', 'linkedTask', 'creator']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}