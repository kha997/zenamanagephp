<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\Foundation\Helpers\AuthHelper;

use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\TaskAssignment;
use Src\Foundation\Events\EventBus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Thêm import Auth facade

/**
 * Service xử lý logic nghiệp vụ cho Tasks
 */
class TaskService
{
    /**
     * Resolve actor ID từ auth helper với fallback an toàn
     * 
     * @return string|int
     */
    private function resolveActorId()
    {
        try {
            return AuthHelper::idOrSystem(); // Thay thế auth()->id() bằng AuthHelper::id()
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from auth helper', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }

    /**
     * Tạo task mới
     * 
     * @param array $data Dữ liệu task
     * @return Task
     * @throws ValidationException
     */
    public function createTask(array $data): Task
    {
        // Validate dữ liệu đầu vào
        $this->validateTaskData($data);
        
        // Kiểm tra project tồn tại
        $project = Project::findOrFail($data['project_id']);
        
        // Kiểm tra component nếu có
        if (isset($data['component_id'])) {
            $component = Component::where('id', $data['component_id'])
                                ->where('project_id', $project->id)
                                ->firstOrFail();
        }
        
        return DB::transaction(function () use ($data, $project) {
            // Tạo task
            $task = Task::create([
                'project_id' => $data['project_id'],
                'component_id' => $data['component_id'] ?? null,
                'phase_id' => $data['phase_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? Task::STATUS_PENDING,
                'priority' => $data['priority'] ?? Task::PRIORITY_MEDIUM, // Thay từ PRIORITY_NORMAL
                'dependencies' => $data['dependencies'] ?? [],
                'conditional_tag' => $data['conditional_tag'] ?? null,
                'is_hidden' => $data['is_hidden'] ?? false,
                'estimated_hours' => $data['estimated_hours'] ?? 0.0,
                'actual_hours' => $data['actual_hours'] ?? 0.0,
                'progress_percent' => $data['progress_percent'] ?? 0.0,
                'tags' => $data['tags'] ?? [],
                'visibility' => $data['visibility'] ?? 'internal',
                'client_approved' => $data['client_approved'] ?? false
            ]);
            
            // Tạo assignments nếu có
            if (isset($data['assignments']) && is_array($data['assignments'])) {
                $this->createTaskAssignments($task, $data['assignments']);
            }
            
            // Kiểm tra và ẩn task nếu conditional_tag không active
            $this->checkConditionalVisibility($task);
            
            // Dispatch event
            $actorId = $this->resolveActorId();
            $eventPayload = [
                'entityId' => $task->id,
                'task_id' => $task->id,
                'projectId' => $project->id,
                'project_id' => $project->id,
                'component_id' => $task->component?->id,
                'actorId' => $actorId,
                'actor_id' => $actorId,
                'task_data' => $task->toArray()
            ];
            EventBus::dispatch('Project.Task.Created', $eventPayload);
            
            return $task->load(['project', 'component', 'assignments.user']);
        });
    }

    /**
     * Cập nhật task
     * 
     * @param string $taskId ULID của task
     * @param array $data Dữ liệu cập nhật
     * @return Task
     * @throws ValidationException
     */
    public function updateTask(string $taskId, array $data): Task
    {
        $task = Task::where('ulid', $taskId)->firstOrFail();
        $oldData = $task->toArray();
        
        // Validate dữ liệu cập nhật
        $this->validateTaskUpdateData($data, $task);
        
        return DB::transaction(function () use ($task, $data, $oldData) {
            // Cập nhật thông tin cơ bản
            $updateFields = array_intersect_key($data, array_flip([
                'name', 'description', 'start_date', 'end_date', 'status',
                'priority', 'dependencies', 'conditional_tag', 'is_hidden',
                'estimated_hours', 'actual_hours', 'tags', 'visibility', 'assignee_id'
            ]));
            
            if (!empty($updateFields)) {
                $task->update($updateFields);
            }
            
            // Cập nhật progress nếu có (sử dụng method có sẵn để trigger events)
            if (isset($data['progress_percent'])) {
                $task->updateProgress($data['progress_percent']);
            }
            
            // Cập nhật assignments nếu có
            if (isset($data['assignments'])) {
                $this->updateTaskAssignments($task, $data['assignments']);
            }
            
            // Kiểm tra conditional visibility sau khi update
            if (isset($data['conditional_tag'])) {
                $this->checkConditionalVisibility($task);
            }
            
            // Dispatch event
            $actorId = $this->resolveActorId();
            EventBus::dispatch('Project.Task.Updated', [
                'entityId' => $task->id,
                'task_id' => $task->id,
                'projectId' => $task->project->id,
                'project_id' => $task->project->id,
                'component_id' => $task->component?->id,
                'actorId' => $actorId,
                'actor_id' => $actorId,
                'old_data' => $oldData,
                'new_data' => $task->fresh()->toArray(),
                'changed_fields' => array_keys($updateFields)
            ]);
            
            return $task->load(['project', 'component', 'assignments.user']);
        });
    }
    
    /**
     * Xóa task
     * 
     * @param string $taskId ULID của task
     * @return bool
     */
    public function deleteTask(string $taskId): bool
    {
        $task = Task::where('ulid', $taskId)->firstOrFail();
        
        // Kiểm tra dependencies - không cho xóa nếu có task khác phụ thuộc
        $dependentTasks = $task->getDependentTasks();
        if ($dependentTasks->count() > 0) {
            throw new \Exception('Không thể xóa task này vì có ' . $dependentTasks->count() . ' task khác đang phụ thuộc vào nó.');
        }
        
        return DB::transaction(function () use ($task) {
            $taskData = $task->toArray();
            
            // Xóa assignments
            $task->assignments()->delete();
            
            // Dispatch event trước khi xóa
            $actorId = $this->resolveActorId();
            EventBus::dispatch('Project.Task.Deleted', [
                'entityId' => $task->id,
                'task_id' => $task->id,
                'projectId' => $task->project->id,
                'project_id' => $task->project->id,
                'component_id' => $task->component?->id,
                'actorId' => $actorId,
                'actor_id' => $actorId,
                'task_data' => $taskData
            ]);
            
            // Xóa task
            return $task->delete();
        });
    }
    
    /**
     * Lấy danh sách tasks theo project
     * 
     * @param int $projectId ID của project
     * @param array $filters Bộ lọc
     * @return Collection
     */
    public function getTasksByProject(int $projectId, array $filters = []): Collection
    {
        $query = Task::forProject($projectId)
                    ->with(['component', 'assignments.user']);
        
        // Áp dụng filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['component_id'])) {
            $query->forComponent($filters['component_id']);
        }
        
        if (isset($filters['assigned_user_id'])) {
            $query->whereHas('assignments', function ($q) use ($filters) {
                $q->where('user_id', $filters['assigned_user_id']);
            });
        }
        
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        if (isset($filters['show_hidden']) && !$filters['show_hidden']) {
            $query->visible();
        }
        
        if (isset($filters['conditional_tag'])) {
            $query->where('conditional_tag', $filters['conditional_tag']);
        }
        
        // Sắp xếp
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
    
    /**
     * Lấy chi tiết task
     * 
     * @param string $taskId ULID của task
     * @return Task
     */
    public function getTaskDetail(string $taskId): Task
    {
        return Task::where('ulid', $taskId)
                  ->with([
                      'project',
                      'component',
                      'assignments.user',
                      'interactionLogs' => function ($query) {
                          $query->orderBy('created_at', 'desc')->limit(10);
                      }
                  ])
                  ->firstOrFail();
    }
    
    /**
     * Cập nhật trạng thái task
     * 
     * @param string $taskId ULID của task
     * @param string $status Trạng thái mới
     * @return Task
     */
    public function updateTaskStatus(string $taskId, string $status): Task
    {
        if (!in_array($status, Task::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Trạng thái không hợp lệ: ' . $status);
        }
        
        $task = Task::where('ulid', $taskId)->firstOrFail();
        $oldStatus = $task->status;
        
        $task->update(['status' => $status]);
        
        // Auto update progress khi complete
        if ($status === Task::STATUS_COMPLETED && $task->progress_percent < 100) {
            $task->updateProgress(100.0);
        }
        
        // Dispatch event
        $actorId = $this->resolveActorId();
        EventBus::dispatch('Project.Task.StatusChanged', [
            'entityId' => $task->id,
            'task_id' => $task->id,
            'projectId' => $task->project->id,
            'project_id' => $task->project->id,
            'component_id' => $task->component?->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'actorId' => $actorId,
            'actor_id' => $actorId
        ]);
        
        return $task->fresh();
    }
    
    /**
     * Validate dữ liệu task
     * 
     * @param array $data
     * @throws ValidationException
     */
    private function validateTaskData(array $data): void
    {
        $required = ['project_id', 'name'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Trường {$field} là bắt buộc.");
            }
        }
        
        // Validate status
        if (isset($data['status']) && !in_array($data['status'], Task::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Trạng thái không hợp lệ.');
        }
        
        // Validate priority
        if (isset($data['priority']) && !in_array($data['priority'], Task::VALID_PRIORITIES)) {
            throw new \InvalidArgumentException('Độ ưu tiên không hợp lệ.');
        }
        
        // Validate dates
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);
            if ($startDate > $endDate) {
                throw new \InvalidArgumentException('Ngày bắt đầu không thể sau ngày kết thúc.');
            }
        }
    }
    
    /**
     * Validate dữ liệu cập nhật task
     * 
     * @param array $data
     * @param Task $task
     * @throws ValidationException
     */
    private function validateTaskUpdateData(array $data, Task $task): void
    {
        // Validate status
        if (isset($data['status']) && !in_array($data['status'], Task::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Trạng thái không hợp lệ.');
        }
        
        // Validate priority
        if (isset($data['priority']) && !in_array($data['priority'], Task::VALID_PRIORITIES)) {
            throw new \InvalidArgumentException('Độ ưu tiên không hợp lệ.');
        }
        
        // Validate progress
        if (isset($data['progress_percent'])) {
            $progress = (float) $data['progress_percent'];
            if ($progress < 0 || $progress > 100) {
                throw new \InvalidArgumentException('Tiến độ phải từ 0 đến 100%.');
            }
        }
        
        // Validate dates
        $startDate = $data['start_date'] ?? $task->start_date;
        $endDate = $data['end_date'] ?? $task->end_date;
        
        if ($startDate && $endDate) {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            if ($start > $end) {
                throw new \InvalidArgumentException('Ngày bắt đầu không thể sau ngày kết thúc.');
            }
        }
    }
    
    /**
     * Tạo task assignments
     * 
     * @param Task $task
     * @param array $assignments
     */
    private function createTaskAssignments(Task $task, array $assignments): void
    {
        $totalPercentage = 0;
        
        foreach ($assignments as $assignment) {
            $percentage = $assignment['split_percent'] ?? 100.0;
            $totalPercentage += $percentage;
            
            TaskAssignment::create([
                'task_id' => $task->id,
                'user_id' => $assignment['user_id'],
                'split_percent' => $percentage,
                'role' => $assignment['role'] ?? null
            ]);
        }
        
        // Cảnh báo nếu tổng phần trăm không phải 100%
        if (abs($totalPercentage - 100.0) > 0.01) {
            \Log::warning("Task {$task->ulid}: Tổng phần trăm assignment là {$totalPercentage}%, không phải 100%");
        }
    }
    
    /**
     * Cập nhật task assignments
     * 
     * @param Task $task
     * @param array $assignments
     */
    private function updateTaskAssignments(Task $task, array $assignments): void
    {
        // Xóa assignments cũ
        $task->assignments()->delete();
        
        // Tạo assignments mới
        if (!empty($assignments)) {
            $this->createTaskAssignments($task, $assignments);
        }
    }
    
    /**
     * Kiểm tra và cập nhật visibility dựa trên conditional tag
     * 
     * @param Task $task
     */
    private function checkConditionalVisibility(Task $task): void
    {
        if (!$task->conditional_tag) {
            return;
        }
        
        // Sử dụng ConditionalTagService để kiểm tra
        $conditionalTagService = app(ConditionalTagService::class);
        $isTagActive = $conditionalTagService->isTagActive($task->conditional_tag, $task->project_id);
        
        // Cập nhật visibility nếu cần
        if ($task->is_hidden === $isTagActive) {
            $task->update(['is_hidden' => !$isTagActive]);
            
            Log::info("Task visibility updated based on conditional tag", [
                'task_id' => $task->ulid,
                'conditional_tag' => $task->conditional_tag,
                'is_hidden' => !$isTagActive,
                'tag_active' => $isTagActive
            ]);
        }
    }
}
