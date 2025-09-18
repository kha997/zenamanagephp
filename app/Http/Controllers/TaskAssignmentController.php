<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TaskAssignmentFormRequest;
use App\Http\Resources\TaskAssignmentResource;
use App\Services\TaskAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTful Controller cho TaskAssignment management
 * 
 * @package App\Http\Controllers
 */
class TaskAssignmentController extends Controller
{
    /**
     * @param TaskAssignmentService $taskAssignmentService
     */
    public function __construct(
        private readonly TaskAssignmentService $taskAssignmentService
    ) {}

    /**
     * Display assignments of a task.
     * GET /api/v1/tasks/{task}/assignments
     */
    public function index(string $taskId): JsonResponse
    {
        try {
            $assignments = $this->taskAssignmentService->getAssignmentsForTask($taskId);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignments' => TaskAssignmentResource::collection($assignments)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách phân công: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created assignment.
     * POST /api/v1/tasks/{task}/assignments
     */
    public function store(TaskAssignmentFormRequest $request, string $taskId): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['task_id'] = $taskId;
            
            $assignment = $this->taskAssignmentService->createAssignment($data);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignment' => new TaskAssignmentResource($assignment)
                ],
                'message' => 'Task đã được phân công thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể phân công task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified assignment.
     * PUT/PATCH /api/v1/assignments/{assignment}
     */
    public function update(TaskAssignmentFormRequest $request, string $assignmentId): JsonResponse
    {
        try {
            $assignment = $this->taskAssignmentService->updateAssignment($assignmentId, $request->validated());
            
            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phân công không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignment' => new TaskAssignmentResource($assignment)
                ],
                'message' => 'Phân công đã được cập nhật thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể cập nhật phân công: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified assignment.
     * DELETE /api/v1/assignments/{assignment}
     */
    public function destroy(string $assignmentId): JsonResponse
    {
        try {
            $deleted = $this->taskAssignmentService->deleteAssignment($assignmentId);
            
            if (!$deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Phân công không tồn tại.'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Phân công đã được xóa thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể xóa phân công: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assignments for a user.
     * GET /api/v1/users/{user}/assignments
     */
    public function getUserAssignments(string $userId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'project_id']);
            $assignments = $this->taskAssignmentService->getAssignmentsForUser($userId, $filters);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'assignments' => TaskAssignmentResource::collection($assignments)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy danh sách phân công của user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assignment statistics for a user.
     * GET /api/v1/users/{user}/assignments/stats
     */
    public function getUserStats(string $userId): JsonResponse
    {
        try {
            $stats = $this->taskAssignmentService->getUserAssignmentStats($userId);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể lấy thống kê phân công: ' . $e->getMessage()
            ], 500);
        }
    }
}