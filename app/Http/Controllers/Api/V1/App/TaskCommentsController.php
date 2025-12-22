<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\TaskCommentManagementService;
use App\Events\TaskCommentCreated;
use App\Events\TaskCommentUpdated;
use App\Events\TaskCommentDeleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Task Comments API Controller (V1)
 * 
 * Pure API controller for task comment operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified TaskCommentManagementController for API routes.
 */
class TaskCommentsController extends BaseApiV1Controller
{
    public function __construct(
        private TaskCommentManagementService $commentService
    ) {}

    /**
     * Get comments for a task
     * 
     * @param string $taskId
     * @param Request $request
     * @return JsonResponse
     */
    public function getCommentsForTask(string $taskId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'type',
                'is_internal',
                'user_id',
                'search',
                'date_from',
                'date_to',
                'per_page',
                'include_replies'
            ]);

            $tenantId = $this->getTenantId();
            $comments = $this->commentService->getCommentsForTask($taskId, $filters, $tenantId);

            if (method_exists($comments, 'items')) {
                return $this->paginatedResponse(
                    $comments->items(),
                    [
                        'current_page' => $comments->currentPage(),
                        'last_page' => $comments->lastPage(),
                        'per_page' => $comments->perPage(),
                        'total' => $comments->total(),
                    ],
                    'Comments retrieved successfully'
                );
            }

            return $this->successResponse($comments, 'Comments retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['task_id' => $taskId]);
            return $this->errorResponse(
                'Failed to fetch comments: ' . $e->getMessage(),
                500,
                null,
                'COMMENTS_FETCH_FAILED'
            );
        }
    }

    /**
     * Get comment by ID
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $comment = $this->commentService->getCommentById($id, $tenantId);
            
            return $this->successResponse($comment, 'Comment retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['comment_id' => $id]);
            return $this->errorResponse(
                'Comment not found: ' . $e->getMessage(),
                404,
                null,
                'COMMENT_NOT_FOUND'
            );
        }
    }

    /**
     * Create a new comment
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|string|ulid',
                'content' => 'required|string|max:5000',
                'type' => 'nullable|string|in:comment,status_change,assignment,mention,system',
                'metadata' => 'nullable|array',
                'is_internal' => 'nullable|boolean',
                'is_pinned' => 'nullable|boolean',
                'parent_id' => 'nullable|string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $comment = $this->commentService->createComment($validated, $tenantId);
            
            event(new TaskCommentCreated($comment, Auth::user()));
            
            return $this->successResponse($comment, 'Comment created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (\Exception $e) {
            $this->logError($e, ['data' => $request->all()]);
            return $this->errorResponse(
                'Failed to create comment: ' . $e->getMessage(),
                500,
                null,
                'COMMENT_CREATE_FAILED'
            );
        }
    }

    /**
     * Update a comment
     * 
     * @param string $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'sometimes|string|max:5000',
                'type' => 'sometimes|string|in:comment,status_change,assignment,mention,system',
                'metadata' => 'nullable|array',
                'is_internal' => 'sometimes|boolean',
                'is_pinned' => 'sometimes|boolean',
            ]);

            $tenantId = $this->getTenantId();
            $comment = $this->commentService->updateComment($id, $validated, $tenantId);
            
            event(new TaskCommentUpdated($comment, Auth::user(), $validated));
            
            return $this->successResponse($comment, 'Comment updated successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage(), 403, null, 'FORBIDDEN');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Comment not found', 404, null, 'COMMENT_NOT_FOUND');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (\Exception $e) {
            $this->logError($e, ['comment_id' => $id, 'data' => $request->all()]);
            return $this->errorResponse(
                'Failed to update comment: ' . $e->getMessage(),
                500,
                null,
                'COMMENT_UPDATE_FAILED'
            );
        }
    }

    /**
     * Delete a comment
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $comment = $this->commentService->getCommentById($id, $tenantId);
            
            $this->commentService->deleteComment($id, $tenantId);
            
            event(new TaskCommentDeleted(
                $comment->id,
                $comment->task_id,
                $comment->task->project_id,
                $comment->task->tenant_id,
                Auth::user()
            ));
            
            return $this->successResponse(null, 'Comment deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['comment_id' => $id]);
            return $this->errorResponse(
                'Failed to delete comment: ' . $e->getMessage(),
                500,
                null,
                'COMMENT_DELETE_FAILED'
            );
        }
    }

    /**
     * Toggle pin comment
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function togglePin(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $comment = $this->commentService->togglePinComment($id, $tenantId);
            
            return $this->successResponse($comment, 'Comment pin status updated successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['comment_id' => $id]);
            return $this->errorResponse(
                'Failed to toggle pin: ' . $e->getMessage(),
                500,
                null,
                'COMMENT_PIN_FAILED'
            );
        }
    }
}

