<?php declare(strict_types=1);

namespace App\Services;
use Illuminate\Support\Facades\Auth;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Src\Compensation\Events\CompensationApplied;
use Src\Compensation\Events\CompensationPreviewed;
use Src\Compensation\Models\Contract;
use Src\Compensation\Models\TaskCompensation;
use Src\CoreProject\Models\Project;
use Src\Foundation\Helpers\AuthHelper;

/**
 * Service xử lý business logic cho Compensation module
 * 
 * Chức năng chính:
 * - Tính toán compensation cho tasks và assignments
 * - Đồng bộ task assignments với compensation
 * - Preview compensation trước khi apply
 * - Apply contract cho compensations
 * - Quản lý compensation workflow
 */
class CompensationService
{
    /**
     * Lấy ID người dùng hiện tại một cách an toàn
     * 
     * @return int|null
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function resolveActorId(): ?int
    {
        if (!AuthHelper::check()) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }
        return AuthHelper::id();
    }

    /**
     * Đồng bộ task assignments với compensation
     * Tạo TaskCompensation records cho các assignments chưa có
     *
     * @param string $projectId
     * @return array
     */
    public function syncTaskAssignments(string $projectId): array
    {
        $actorId = $this->resolveActorId();
        if (!$actorId) {
            throw new \Exception('User not authenticated');
        }

        return DB::transaction(function () 
            
            $syncedCount = 0;
            $skippedCount = 0;
            
            foreach ($tasks as $task) {
                foreach ($task->assignments as $assignment) {
                    // Kiểm tra xem đã có TaskCompensation chưa
                    $existingCompensation = TaskCompensation::where('task_id', $task->id)
                                                          ->where('assignment_id', $assignment->id)
                                                          ->first();
                    
                    if (!$existingCompensation) {
                        // Tạo TaskCompensation mới
                        TaskCompensation::create([
                            'task_id' => $task->id,
                            'assignment_id' => $assignment->id,
                            'base_contract_value_percent' => $assignment->split_percent,
                            'effective_contract_value_percent' => $assignment->split_percent,
                            'status' => TaskCompensation::STATUS_PENDING,
                            'created_by' => $actorId,
                            'updated_by' => $actorId,
                        ]);
                        
                        $syncedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
            
            Log::info('Task assignments synced', [
                'project_id' => $projectId,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount
            ]);
            
            return [
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'total_tasks' => $tasks->count()
            ];
        });
    }
    
    /**
     * Preview compensation trước khi apply contract
     *
     * @param string $projectId
     * @param string $contractId
     * @param array $taskIds Optional - chỉ preview cho specific tasks
     * @return array
     */
    public function previewCompensation(string $projectId, string $contractId, array $taskIds = []): array
    {
        $actorId = $this->resolveActorId();
        if (!$actorId) {
            throw new \Exception('User not authenticated');
        }

        // Validate contract
        $contract = Contract::where('id', $contractId)
                           ->where('project_id', $projectId)
                           ->firstOrFail();
        
        if (!$contract->canApplyCompensation()) {
            throw new ValidationException('Contract không thể áp dụng compensation');
        }
        
        // Build query cho task compensations
        $query = TaskCompensation::with(['task', 'assignment.user'])
                                ->whereHas('task', function ($q) 
                                })
                                ->where('status', TaskCompensation::STATUS_PENDING);
        
        if (!empty($taskIds)) {
            $query->whereIn('task_id', $taskIds);
        }
        
        $compensations = $query->get();
        
        $previewData = [];
        $totalValue = 0;
        
        foreach ($compensations as $compensation) {
            $currentValue = $compensation->calculateCurrentValue($contract->total_value);
            
            $previewData[] = [
                'compensation_id' => $compensation->id,
                'task_id' => $compensation->task_id,
                'task_name' => $compensation->task->name,
                'user_id' => $compensation->assignment->user_id,
                'user_name' => $compensation->assignment->user->name,
                'base_percent' => $compensation->base_contract_value_percent,
                'effective_percent' => $compensation->effective_contract_value_percent,
                'current_value' => $currentValue,
                'contract_value' => $contract->total_value,
                'status' => $compensation->status
            ];
            
            $totalValue += $currentValue;
        }
        
        // Dispatch preview event
        Event::dispatch(new CompensationPreviewed([
            'project_id' => $projectId,
            'contract_id' => $contractId,
            'preview_data' => $previewData,
            'total_value' => $totalValue,
            'actor_id' => $actorId,
            'timestamp' => Carbon::now()
        ]));
        
        return [
            'contract' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'total_value' => $contract->total_value,
                'status' => $contract->status
            ],
            'compensations' => $previewData,
            'summary' => [
                'total_compensations' => count($previewData),
                'total_value' => $totalValue,
                'remaining_value' => $contract->total_value - $totalValue
            ]
        ];
    }
    
    /**
     * Apply contract cho compensations
     *
     * @param string $projectId
     * @param string $contractId
     * @param array $compensationIds
     * @return array
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function applyContract(string $projectId, string $contractId, array $compensationIds): array
    {
        $actorId = $this->resolveActorId();
    
        return DB::transaction(function () 
            
            if (!$contract->canApplyCompensation()) {
                throw new ValidationException('Contract không thể áp dụng compensation');
            }
            
            // Lấy compensations cần apply
            $compensations = TaskCompensation::whereIn('id', $compensationIds)
                                            ->whereHas('task', function ($q) 
                                            })
                                            ->where('status', TaskCompensation::STATUS_PENDING)
                                            ->get();
            
            if ($compensations->count() !== count($compensationIds)) {
                throw new ValidationException('Một số compensation không hợp lệ hoặc đã được apply');
            }
            
            $appliedCount = 0;
            $totalValue = 0;
            
            foreach ($compensations as $compensation) {
                // Lock compensation với contract - sử dụng actorId thay vì Auth::id()
                $compensation->lockWithContract($contract, $actorId);
                $appliedCount++;
                
                $totalValue += $compensation->calculateCurrentValue($contract->total_value);
            }
            
            // Dispatch applied event
            Event::dispatch(new CompensationApplied([
                'project_id' => $projectId,
                'contract_id' => $contractId,
                'compensation_ids' => $compensationIds,
                'applied_count' => $appliedCount,
                'total_value' => $totalValue,
                'actor_id' => $actorId,
                'timestamp' => Carbon::now()
            ]));
            
            Log::info('Compensations applied', [
                'project_id' => $projectId,
                'contract_id' => $contractId,
                'applied_count' => $appliedCount,
                'total_value' => $totalValue
            ]);
            
            return [
                'applied_count' => $appliedCount,
                'total_value' => $totalValue,
                'contract_id' => $contractId
            ];
        });
    }
    
    /**
     * Cập nhật effective percent cho task compensation
     *
     * @param string $compensationId
     * @param float $newPercent
     * @param string|null $notes
     * @return TaskCompensation
     */
    public function updateEffectivePercent(string $compensationId, float $newPercent, ?string $notes = null): TaskCompensation
    {
        $actorId = $this->resolveActorId();
        if (!$actorId) {
            throw new \Exception('User not authenticated');
        }

        return DB::transaction(function () 
            
            if (!$compensation->canBeUpdated()) {
                throw new ValidationException('Compensation đã được lock, không thể cập nhật');
            }
            
            // Validate percent range
            if ($newPercent < 0 || $newPercent > 100) {
                throw new ValidationException('Percent phải trong khoảng 0-100');
            }
            
            $oldPercent = $compensation->effective_contract_value_percent;
            
            $compensation->updateEffectivePercent($newPercent, $notes);
            
            Log::info('Compensation effective percent updated', [
                'compensation_id' => $compensationId,
                'old_percent' => $oldPercent,
                'new_percent' => $newPercent,
                'notes' => $notes,
                'updated_by' => $actorId
            ]);
            
            return $compensation->fresh();
        });
    }
    
    /**
     * Lấy thống kê compensation cho project
     *
     * @param string $projectId
     * @return array
     */
    public function getProjectStats(string $projectId): array
    {
        // Validate project
        $project = Project::findOrFail($projectId);
        
        // Lấy tất cả compensations của project
        $compensations = TaskCompensation::with(['task', 'assignment.user', 'contract'])
                                        ->whereHas('task', function ($q) 
                                        })
                                        ->get();
        
        // Group by status
        $byStatus = $compensations->groupBy('status');
        
        // Group by user
        $byUser = $compensations->groupBy(function ($compensation) {
            return $compensation->assignment->user_id;
        });
        
        // Calculate totals
        $totalCompensations = $compensations->count();
        $pendingCount = $byStatus->get(TaskCompensation::STATUS_PENDING, collect())->count();
        $lockedCount = $byStatus->get(TaskCompensation::STATUS_LOCKED, collect())->count();
        
        // Calculate values (chỉ cho locked compensations)
        $lockedCompensations = $byStatus->get(TaskCompensation::STATUS_LOCKED, collect());
        $totalLockedValue = 0;
        
        foreach ($lockedCompensations as $compensation) {
            if ($compensation->contract) {
                $totalLockedValue += $compensation->calculateCurrentValue($compensation->contract->total_value);
            }
        }
        
        // User statistics
        $userStats = [];
        foreach ($byUser as $userId => $userCompensations) {
            $user = $userCompensations->first()->assignment->user;
            $userLockedValue = 0;
            
            foreach ($userCompensations as $compensation) {
                if ($compensation->status === TaskCompensation::STATUS_LOCKED && $compensation->contract) {
                    $userLockedValue += $compensation->calculateCurrentValue($compensation->contract->total_value);
                }
            }
            
            $userStats[] = [
                'user_id' => $userId,
                'user_name' => $user->name,
                'total_compensations' => $userCompensations->count(),
                'pending_count' => $userCompensations->where('status', TaskCompensation::STATUS_PENDING)->count(),
                'locked_count' => $userCompensations->where('status', TaskCompensation::STATUS_LOCKED)->count(),
                'locked_value' => $userLockedValue
            ];
        }
        
        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name
            ],
            'summary' => [
                'total_compensations' => $totalCompensations,
                'pending_count' => $pendingCount,
                'locked_count' => $lockedCount,
                'total_locked_value' => $totalLockedValue
            ],
            'by_status' => [
                TaskCompensation::STATUS_PENDING => $pendingCount,
                TaskCompensation::STATUS_LOCKED => $lockedCount
            ],
            'user_stats' => $userStats
        ];
    }
    
    /**
     * Lấy danh sách task compensations với filters
     *
     * @param array $filters
     * @return Collection
     */
    public function getTaskCompensations(array $filters = []): Collection
    {
        $query = TaskCompensation::with(['task', 'assignment.user', 'contract']);
        
        // Apply filters
        if (!empty($filters['project_id'])) {
            $query->whereHas('task', function ($q) 
            });
        }
        
        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }
        
        if (!empty($filters['user_id'])) {
            $query->whereHas('assignment', function ($q) 
            });
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $query->orderBy($sortBy, $sortDirection);
        
        return $query->get();
    }
}