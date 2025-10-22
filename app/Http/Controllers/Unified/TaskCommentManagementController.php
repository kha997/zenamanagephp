<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Services\TaskCommentManagementService;
use App\Support\ApiResponse;
use App\Events\TaskCommentCreated;
use App\Events\TaskCommentUpdated;
use App\Events\TaskCommentDeleted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TaskCommentManagementController extends Controller
{
    public function __construct(
        private TaskCommentManagementService $commentService
    ) {}

    /**
     * Get comments for a task
     */
    public function getCommentsForTask(string $taskId, Request $request): JsonResponse
    {
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

        try {
            $comments = $this->commentService->getCommentsForTask($taskId, $filters, (string) auth()->user()->tenant_id);
            return ApiResponse::paginated(
                $comments->items(),
                [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total(),
                ]
            );
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Task not found');
        } catch (\Exception $e) {
            Log::error('Error fetching comments for task: ' . $e->getMessage(), ['task_id' => $taskId]);
            return ApiResponse::error('Failed to fetch comments', 500);
        }
    }

    /**
     * Get comment by ID
     */
    public function getComment(string $id): JsonResponse
    {
        try {
            $comment = $this->commentService->getCommentById($id, (string) auth()->user()->tenant_id);
            return ApiResponse::success($comment);
        } catch (\Exception $e) {
            Log::error('Error fetching comment: ' . $e->getMessage(), ['comment_id' => $id]);
            return ApiResponse::error('Comment not found', 404);
        }
    }

    /**
     * Create a new comment
     */
    public function createComment(Request $request): JsonResponse
    {
        $request->validate([
            'task_id' => 'required|string|ulid',
            'content' => 'required|string|max:5000',
            'type' => 'nullable|string|in:comment,status_change,assignment,mention,system',
            'metadata' => 'nullable|array',
            'is_internal' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'parent_id' => 'nullable|string|ulid',
        ]);

        try {
            $comment = $this->commentService->createComment($request->all(), (string) auth()->user()->tenant_id);
            
            // Dispatch real-time event
            event(new TaskCommentCreated($comment, auth()->user()));
            
            return ApiResponse::created($comment);
        } catch (\Exception $e) {
            Log::error('Error creating comment: ' . $e->getMessage(), ['data' => $request->all()]);
            return ApiResponse::error('Failed to create comment', 500);
        }
    }

    /**
     * Update a comment
     */
    public function updateComment(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'sometimes|string|max:5000',
            'type' => 'sometimes|string|in:comment,status_change,assignment,mention,system',
            'metadata' => 'nullable|array',
            'is_internal' => 'sometimes|boolean',
            'is_pinned' => 'sometimes|boolean',
        ]);

        try {
            $comment = $this->commentService->updateComment($id, $request->all(), (string) auth()->user()->tenant_id);
            
            // Dispatch real-time event
            event(new TaskCommentUpdated($comment, auth()->user(), $request->all()));
            
            return ApiResponse::success($comment);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Comment not found');
        } catch (\Exception $e) {
            Log::error('Error updating comment: ' . $e->getMessage(), ['comment_id' => $id, 'data' => $request->all()]);
            return ApiResponse::error('Failed to update comment', 500);
        }
    }

    /**
     * Delete a comment
     */
    public function deleteComment(string $id): JsonResponse
    {
        try {
            // Get comment data before deletion for event dispatch
            $comment = $this->commentService->getCommentById($id, (string) auth()->user()->tenant_id);
            
            $this->commentService->deleteComment($id, (string) auth()->user()->tenant_id);
            
            // Dispatch real-time event
            event(new TaskCommentDeleted(
                $comment->id,
                $comment->task_id,
                $comment->task->project_id,
                $comment->task->tenant_id,
                auth()->user()
            ));
            
            return ApiResponse::noContent();
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Comment not found');
        } catch (\Exception $e) {
            Log::error('Error deleting comment: ' . $e->getMessage(), ['comment_id' => $id]);
            return ApiResponse::error('Failed to delete comment', 500);
        }
    }

    /**
     * Pin/unpin a comment
     */
    public function togglePinComment(string $id, Request $request): JsonResponse
    {
        try {
            $desired = $request->has('is_pinned') ? $request->boolean('is_pinned') : null;
            $comment = $this->commentService->togglePinComment($id, $desired, (string) auth()->user()->tenant_id);
            return ApiResponse::success($comment);
        } catch (AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Comment not found');
        } catch (\Exception $e) {
            Log::error('Error toggling comment pin: ' . $e->getMessage(), ['comment_id' => $id]);
            return ApiResponse::error('Failed to toggle comment pin', 500);
        }
    }

    /**
     * Get comment statistics for a task
     */
    public function getCommentStatistics(string $taskId): JsonResponse
    {
        try {
            $stats = $this->commentService->getCommentStatistics($taskId, (string) auth()->user()->tenant_id);
            return ApiResponse::success($stats);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::notFound('Task not found');
        } catch (\Exception $e) {
            Log::error('Error fetching comment statistics: ' . $e->getMessage(), ['task_id' => $taskId]);
            return ApiResponse::error('Failed to fetch comment statistics', 500);
        }
    }
}
