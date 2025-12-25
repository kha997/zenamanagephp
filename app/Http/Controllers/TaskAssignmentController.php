<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\CoreProject\Models\TaskAssignment;
use App\Http\Resources\TaskAssignmentResource;
use Src\Foundation\Utils\JSendResponse;
use Src\RBAC\Middleware\RBACMiddleware;

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
     * Lấy danh sách assignments của một task
     *
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId, string $taskId): JsonResponse
    {
        try {
            $assignments = TaskAssignment::where('task_id', $taskId)
                ->whereHas('task', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->with(['task', 'user'])
                ->orderBy('split_percent', 'desc')
                ->get();

            return JSendResponse::success([
                'assignments' => TaskAssignmentResource::collection($assignments)
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách assignments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo assignment mới
     *
     * @param StoreTaskAssignmentRequest $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function store(StoreTaskAssignmentRequest $request, string $projectId, string $taskId): JsonResponse
    {
        try {
            $assignmentData = $request->validated();
            $assignmentData['task_id'] = $taskId;

            // Kiểm tra tổng split_percent không vượt quá 100%
            $currentTotal = TaskAssignment::where('task_id', $taskId)->sum('split_percent');
            if ($currentTotal + $assignmentData['split_percent'] > 100) {
                return JSendResponse::error(
                    'Tổng phần trăm phân chia không được vượt quá 100%. Hiện tại: ' . $currentTotal . '%',
                    400
                );
            }

            $assignment = TaskAssignment::create($assignmentData);
            $assignment->load(['task', 'user']);

            // Dispatch event
            event(new \Src\CoreProject\Events\TaskAssignmentCreated($assignment));

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment),
                'message' => 'Task assignment đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết một assignment
     *
     * @param string $projectId
     * @param string $taskId
     * @param string $assignmentId
     * @return JsonResponse
     */
    public function show(string $projectId, string $taskId, string $assignmentId): JsonResponse
    {
        try {
            $assignment = TaskAssignment::where('id', $assignmentId)
                ->where('task_id', $taskId)
                ->whereHas('task', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->with(['task', 'user'])
                ->firstOrFail();

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin assignment
     *
     * @param UpdateTaskAssignmentRequest $request
     * @param string $projectId
     * @param string $taskId
     * @param string $assignmentId
     * @return JsonResponse
     */
    public function update(UpdateTaskAssignmentRequest $request, string $projectId, string $taskId, string $assignmentId): JsonResponse
    {
        try {
            $assignment = TaskAssignment::where('id', $assignmentId)
                ->where('task_id', $taskId)
                ->whereHas('task', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->firstOrFail();

            $assignmentData = $request->validated();

            // Kiểm tra tổng split_percent không vượt quá 100%
            if (isset($assignmentData['split_percent'])) {
                $currentTotal = TaskAssignment::where('task_id', $taskId)
                    ->where('id', '!=', $assignmentId)
                    ->sum('split_percent');
                    
                if ($currentTotal + $assignmentData['split_percent'] > 100) {
                    return JSendResponse::error(
                        'Tổng phần trăm phân chia không được vượt quá 100%. Hiện tại (không bao gồm assignment này): ' . $currentTotal . '%',
                        400
                    );
                }
            }

            $assignment->update($assignmentData);
            $assignment->load(['task', 'user']);

            // Dispatch event
            event(new \Src\CoreProject\Events\TaskAssignmentUpdated($assignment));

            return JSendResponse::success([
                'assignment' => new TaskAssignmentResource($assignment),
                'message' => 'Task assignment đã được cập nhật thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật task assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa assignment
     *
     * @param string $projectId
     * @param string $taskId
     * @param string $assignmentId
     * @return JsonResponse
     */
    public function destroy(string $projectId, string $taskId, string $assignmentId): JsonResponse
    {
        try {
            $assignment = TaskAssignment::where('id', $assignmentId)
                ->where('task_id', $taskId)
                ->whereHas('task', function ($query) use ($projectId) {
                    $query->where('project_id', $projectId);
                })
                ->firstOrFail();

            $assignment->delete();

            // Dispatch event
            event(new \Src\CoreProject\Events\TaskAssignmentDeleted($assignment));

            return JSendResponse::success([
                'message' => 'Task assignment đã được xóa thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Task assignment không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa task assignment: ' . $e->getMessage(), 500);
        }
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
