<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use App\Services\TaskAssignmentShareException;
use App\Services\TaskAssignmentShares;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\TaskAssignment;
use Src\CoreProject\Resources\TaskAssignmentResource;
use Src\CoreProject\Requests\StoreTaskAssignmentRequest;
use Src\CoreProject\Requests\UpdateTaskAssignmentRequest;
use Src\RBAC\Middleware\RBACMiddleware;
use Illuminate\Support\Facades\Auth;
use Src\Foundation\Utils\JSendResponse;

/**
 * Controller xử lý các hoạt động CRUD cho TaskAssignment
 * 
 * @package Src\CoreProject\Controllers
 */
class TaskAssignmentController
{
    /**
     * Constructor - áp dụng RBAC middleware
     */
    public function __construct()
    {
        // Xóa middleware khỏi constructor - sẽ áp dụng trong routes
        // $this->middleware(RBACMiddleware::class);
    }

    /**
     * UAT-RECOVERY-04b (O-13): the CoreProject models have no registered policy, so decide on the canonical
     * App\Models\Task row (TaskPolicy::assign: owning-project PM holding task.assign). Fail-closed.
     */
    private function actorCanAssign(string $taskId): bool
    {
        $actor = Auth::guard('api')->user() ?? Auth::user();
        $canonical = \App\Models\Task::query()->whereKey($taskId)->first();

        return $actor !== null && $canonical !== null && $actor->can('assign', $canonical);
    }

    /**
     * Lấy danh sách assignments của một task qua flat query/body contract.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $taskId = (string) ($request->query('task_id', $request->input('task_id', '')));
            if ($taskId === '') {
                return JSendResponse::fail([
                    'task_id' => ['Trường task_id là bắt buộc.'],
                ], 422);
            }

            $assignments = $this->taskAssignmentQuery()
                ->where('task_id', $taskId)
                ->with(['task'])
                ->get();
            $assignments->each(fn (TaskAssignment $assignment): TaskAssignment => $this->normalizeAssignmentForResource($assignment));

            return JSendResponse::success([
                'assignments' => TaskAssignmentResource::collection($assignments)
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách assignments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo assignment mới qua flat body contract.
     */
    public function store(StoreTaskAssignmentRequest $request): JsonResponse
    {
        try {
            $assignmentData = $request->validated();
            $taskId = (string) $assignmentData['task_id'];
            $task = $this->findTaskOrFail($taskId);

            // UAT-RECOVERY-04b (O-13): owning-project PM only; assignee must belong to the task's tenant.
            if (!$this->actorCanAssign((string) $task->id)) {
                return JSendResponse::error('Forbidden', 403);
            }
            if (!\App\Models\User::query()->whereKey($assignmentData['user_id'])->where('tenant_id', $task->tenant_id)->exists()) {
                return JSendResponse::error('User không thuộc tenant của task.', 422);
            }

            // OWN-2026-024 SPLIT-PERCENT: duplicate check, 100 % check and insert under one task-row lock; tenant_id comes from
            // the task (it was left NULL before), assigned_at/assigned_by from the server; the response is re-read from the DB.
            $actor = Auth::guard('api')->user() ?? Auth::user();

            try {
                $assignmentId = TaskAssignmentShares::withTaskLock((string) $task->id, function () use ($assignmentData, $task, $taskId, $actor): string {
                    if (TaskAssignmentShares::isAssigned($taskId, (string) $assignmentData['user_id'])) {
                        throw TaskAssignmentShareException::duplicate();
                    }
                    TaskAssignmentShares::assertFits($taskId, (float) $assignmentData['split_percent']);

                    return (string) TaskAssignment::create([
                        'task_id' => $taskId,
                        'user_id' => (string) $assignmentData['user_id'],
                        'split_percent' => (float) $assignmentData['split_percent'],
                        'role' => (string) $assignmentData['role'],
                        'tenant_id' => (string) $task->tenant_id,
                        'assigned_at' => now(),
                        'assigned_by' => $actor !== null ? (string) $actor->getAuthIdentifier() : null,
                    ])->getKey();
                });
            } catch (TaskAssignmentShareException $e) {
                return JSendResponse::error(match ($e->reason) {
                    TaskAssignmentShareException::REASON_DUPLICATE => 'User đã được phân công cho task này.',
                    TaskAssignmentShareException::REASON_EXCEEDS_TOTAL => 'Tổng phần trăm phân chia không được vượt quá 100%. Hiện tại: ' . $e->currentTotal . '%',
                    default => 'Task không tồn tại.',
                }, $e->reason === TaskAssignmentShareException::REASON_TASK_NOT_FOUND ? 404 : 400);
            }

            $assignment = TaskAssignment::query()->with(['task'])->findOrFail($assignmentId);
            $this->normalizeAssignmentForResource($assignment);

            // Dispatch event
            if (class_exists(\Src\CoreProject\Events\TaskAssignmentCreated::class)) {
                event(new \Src\CoreProject\Events\TaskAssignmentCreated($assignment));
            }

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment),
                'message' => 'Task assignment đã được tạo thành công.'
            ], 201);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết một assignment qua flat item route.
     */
    public function show(string $taskAssignment): JsonResponse
    {
        try {
            $assignment = $this->findAssignmentOrFail($taskAssignment);
            $this->normalizeAssignmentForResource($assignment);

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment)
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin assignment qua flat item route.
     */
    public function update(UpdateTaskAssignmentRequest $request, string $taskAssignment): JsonResponse
    {
        try {
            $assignment = $this->findAssignmentOrFail($taskAssignment);

            // UAT-RECOVERY-04b (O-13): reassignment uses the same boundary.
            if (!$this->actorCanAssign((string) $assignment->task_id)) {
                return JSendResponse::error('Forbidden', 403);
            }

            $assignmentData = $request->validated();
            $taskId = (string) $assignment->task_id;

            // OWN-2026-024 SPLIT-PERCENT: the 100 % check (excluding this assignment) and the update run under the task lock.
            try {
                TaskAssignmentShares::withTaskLock($taskId, function () use ($assignment, $assignmentData, $taskId): void {
                    if (isset($assignmentData['split_percent'])) {
                        TaskAssignmentShares::assertFits($taskId, (float) $assignmentData['split_percent'], (string) $assignment->getKey());
                    }
                    $assignment->update($assignmentData);
                });
            } catch (TaskAssignmentShareException $e) {
                return JSendResponse::error(
                    $e->reason === TaskAssignmentShareException::REASON_EXCEEDS_TOTAL
                        ? 'Tổng phần trăm phân chia không được vượt quá 100%. Hiện tại (không bao gồm assignment này): ' . $e->currentTotal . '%'
                        : 'Task không tồn tại.',
                    $e->reason === TaskAssignmentShareException::REASON_TASK_NOT_FOUND ? 404 : 400
                );
            }

            $assignment = TaskAssignment::query()->with(['task'])->findOrFail($assignment->getKey());
            $this->normalizeAssignmentForResource($assignment);

            // Dispatch event
            if (class_exists(\Src\CoreProject\Events\TaskAssignmentUpdated::class)) {
                event(new \Src\CoreProject\Events\TaskAssignmentUpdated($assignment));
            }

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment),
                'message' => 'Task assignment đã được cập nhật thành công.'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa assignment qua flat item route.
     */
    public function destroy(string $taskAssignment): JsonResponse
    {
        try {
            $assignment = $this->findAssignmentOrFail($taskAssignment);

            // UAT-RECOVERY-04b (O-13): unassignment uses the same boundary.
            if (!$this->actorCanAssign((string) $assignment->task_id)) {
                return JSendResponse::error('Forbidden', 403);
            }

            $assignment->delete();

            // Dispatch event
            if (class_exists(\Src\CoreProject\Events\TaskAssignmentDeleted::class)) {
                event(new \Src\CoreProject\Events\TaskAssignmentDeleted($assignment));
            }

            return JSendResponse::success([
                'message' => 'Task assignment đã được xóa thành công.'
            ]);
        } catch (ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa task assignment: ' . $e->getMessage(), 500);
        }
    }

    private function findAssignmentOrFail(string $assignmentId): TaskAssignment
    {
        return $this->taskAssignmentQuery()
            ->whereKey($assignmentId)
            ->with(['task'])
            ->firstOrFail();
    }

    private function findTaskOrFail(string $taskId): Task
    {
        $query = Task::query()->whereKey($taskId);
        $tenantId = $this->currentTenantId();

        if ($tenantId !== null) {
            $query->whereHas('project', function (Builder $builder) use ($tenantId): void {
                $builder->where('tenant_id', $tenantId);
            });
        }

        return $query->firstOrFail();
    }

    private function taskAssignmentQuery(): Builder
    {
        $query = TaskAssignment::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId !== null) {
            $query->whereHas('task.project', function (Builder $builder) use ($tenantId): void {
                $builder->where('tenant_id', $tenantId);
            });
        }

        return $query;
    }

    private function currentTenantId(): ?string
    {
        $tenantId = request()->attributes->get('tenant_id');

        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        $appTenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return is_string($appTenantId) && $appTenantId !== '' ? $appTenantId : null;
    }

    private function normalizeAssignmentForResource(TaskAssignment $assignment): TaskAssignment
    {
        foreach (['assigned_at', 'created_at', 'updated_at'] as $attribute) {
            $value = $assignment->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                $assignment->setAttribute($attribute, Carbon::parse($value));
            }
        }

        return $assignment;
    }

    /**
     * Lấy thống kê assignments của user trong project
     *
     * @param string $projectId
     * @param string $userId
     * @return JsonResponse
     */
    public function userStats(string $projectId, string $userId): JsonResponse
    {
        try {
            $assignments = TaskAssignment::whereHas('task', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->where('user_id', $userId)
                ->with(['task'])
                ->get();

            $stats = [
                'total_assignments' => $assignments->count(),
                'total_workload_percentage' => $assignments->sum('split_percent'),
                'tasks_by_status' => $assignments->groupBy('task.status')->map->count(),
                'assignments' => TaskAssignmentResource::collection($assignments)
            ];

            return JSendResponse::success($stats);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thống kê assignments: ' . $e->getMessage(), 500);
        }
    }
}
