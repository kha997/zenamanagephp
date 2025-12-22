<?php declare(strict_types=1);

namespace App\Services;

use App\Models\TaskComment;
use App\Models\Task;
use App\Models\User;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Task Comment Management Service
 * 
 * Handles all task comment-related business logic including CRUD operations,
 * threaded comments, mentions, and comment management features.
 */
class TaskCommentManagementService
{
    use ServiceBaseTrait;

    /**
     * Get comments for a task with pagination
     */
    public function getCommentsForTask(string|int $taskId, array $filters = [], ?string $tenantId = null): LengthAwarePaginator
    {
        $this->validateTenantAccess($tenantId);

        // Verify task exists and belongs to tenant
        if (!Task::where('id', $taskId)->where('tenant_id', $tenantId)->exists()) {
            throw new ModelNotFoundException('Task not found');
        }

        $query = TaskComment::with(['user', 'replies.user'])
            ->where('task_id', $taskId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Apply filters
        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_internal']) && $filters['is_internal'] !== null) {
            $query->where('is_internal', $filters['is_internal']);
        }

        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where('content', 'like', '%' . $filters['search'] . '%');
        }

        // Only show root comments (not replies) for main list
        if (!isset($filters['include_replies']) || !$filters['include_replies']) {
            $query->whereNull('parent_id');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->ordered()->paginate($perPage);
    }

    /**
     * Get comment by ID with tenant isolation
     */
    public function getCommentById(string|int $id, ?string $tenantId = null): ?TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
        return TaskComment::with(['user', 'parent', 'replies.user'])
            ->where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Create new comment
     */
    public function createComment(array $data, ?string $tenantId = null): TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify parent task exists
        $task = Task::where('id', $data['task_id'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
        
        if (!$task) {
            throw new \InvalidArgumentException('Parent task not found');
        }
        
        $data['tenant_id'] = $tenantId;
        $data['user_id'] = (string) auth()->id();
        
        // Process mentions in content
        $data['content'] = $this->processMentions($data['content'], $data['task_id'], $tenantId);
        
        $comment = TaskComment::create($data);
        
        $this->logCrudOperation('created', $comment, $data);
        
        return $comment->load(['user', 'task', 'parent']);
    }

    /**
     * Update comment
     */
    public function updateComment(string|int $id, array $data, ?string $tenantId = null): TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
        $comment = TaskComment::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        // Only allow author to edit their own comments
        if ($comment->user_id !== (string) auth()->id()) {
            Log::error('Authorization failed for comment update', [
                'comment_user_id' => $comment->user_id,
                'auth_user_id' => (string) auth()->id(),
                'comment_id' => $id
            ]);
            throw new AuthorizationException('You can only edit your own comments');
        }
        
        // Process mentions in content if content is being updated
        if (isset($data['content'])) {
            $data['content'] = $this->processMentions($data['content'], $comment->task_id, $tenantId);
        }
        
        $comment->update($data);
        
        $this->logCrudOperation('updated', $comment, $data);
        
        return $comment->load(['user', 'task', 'parent']);
    }

    /**
     * Delete comment
     */
    public function deleteComment(string|int $id, ?string $tenantId = null): bool
    {
        $this->validateTenantAccess($tenantId);
        
        $comment = TaskComment::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        // Only allow author to delete their own comments
        if ($comment->user_id !== (string) auth()->id()) {
            throw new AuthorizationException('You can only delete your own comments');
        }
        
        $this->logCrudOperation('deleted', $comment, ['content' => $comment->content]);
        
        return $comment->delete();
    }

    /**
     * Create reply to comment
     */
    public function createReply(string|int $parentId, array $data, ?string $tenantId = null): TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
        $parentComment = TaskComment::where('id', $parentId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $data['task_id'] = $parentComment->task_id;
        $data['parent_id'] = $parentId;
        
        return $this->createComment($data, $tenantId);
    }

    /**
     * Pin/unpin comment
     */
    public function togglePinComment(string|int $id, ?bool $desiredState = null, ?string $tenantId = null): TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
        $comment = TaskComment::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $newState = $desiredState ?? !$comment->is_pinned;
        $comment->update(['is_pinned' => $newState]);
        
        $this->logCrudOperation('pinned', $comment, [
            'is_pinned' => $comment->is_pinned
        ]);
        
        return $comment->load(['user', 'task']);
    }

    /**
     * Get recent comments for user
     */
    public function getRecentCommentsForUser(string|int $userId, int $limit = 10, ?string $tenantId = null): Collection
    {
        $this->validateTenantAccess($tenantId);
        
        return TaskComment::with(['user', 'task'])
            ->where('user_id', $userId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get mentions for user
     */
    public function getMentionsForUser(string|int $userId, int $limit = 20, ?string $tenantId = null): Collection
    {
        $this->validateTenantAccess($tenantId);
        
        return TaskComment::with(['user', 'task'])
            ->where('type', TaskComment::TYPE_MENTION)
            ->whereJsonContains('metadata->mentioned_user_id', $userId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search comments
     */
    public function searchComments(string $searchTerm, array $filters = [], ?string $tenantId = null): LengthAwarePaginator
    {
        $this->validateTenantAccess($tenantId);
        
        $query = TaskComment::with(['user', 'task'])
            ->where('content', 'like', '%' . $searchTerm . '%')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Apply additional filters
        if (isset($filters['task_id']) && $filters['task_id']) {
            $query->where('task_id', $filters['task_id']);
        }

        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get comment statistics for a task
     */
    public function getCommentStatistics(string|int $taskId, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify task exists and belongs to tenant
        if (!Task::where('id', $taskId)->where('tenant_id', $tenantId)->exists()) {
            throw new ModelNotFoundException('Task not found');
        }
        
        $baseQuery = TaskComment::where('task_id', $taskId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        
        return [
            'total' => (clone $baseQuery)->count(),
            'comments' => (clone $baseQuery)->where('type', TaskComment::TYPE_COMMENT)->count(),
            'status_changes' => (clone $baseQuery)->where('type', TaskComment::TYPE_STATUS_CHANGE)->count(),
            'assignments' => (clone $baseQuery)->where('type', TaskComment::TYPE_ASSIGNMENT)->count(),
            'mentions' => (clone $baseQuery)->where('type', TaskComment::TYPE_MENTION)->count(),
            'replies' => (clone $baseQuery)->whereNotNull('parent_id')->count(),
            'pinned' => (clone $baseQuery)->where('is_pinned', true)->count(),
            'internal' => (clone $baseQuery)->where('is_internal', true)->count(),
            'public' => (clone $baseQuery)->where('is_internal', false)->count()
        ];
    }

    /**
     * Process mentions in comment content
     */
    private function processMentions(string $content, string $taskId, ?string $tenantId): string
    {
        // Find @mentions in content
        preg_match_all('/@(\w+)/', $content, $matches);
        
        if (empty($matches[1])) {
            return $content;
        }
        
        foreach ($matches[1] as $username) {
            $user = User::where('name', 'like', '%' . $username . '%')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->first();
            
            if ($user) {
                    // Create mention comment
                    TaskComment::createMentionComment($taskId, (string) auth()->id(), $user->id, $content, $tenantId);
                
                // Replace @username with @[User Name] for better display
                $content = str_replace('@' . $username, '@[' . $user->name . ']', $content);
            }
        }
        
        return $content;
    }

    /**
     * Create system comment
     */
    public function createSystemComment(string|int $taskId, string $content, array $metadata = [], ?string $tenantId = null): TaskComment
    {
        $this->validateTenantAccess($tenantId);
        
            return TaskComment::create([
                'tenant_id' => $tenantId,
                'task_id' => $taskId,
                'user_id' => (string) auth()->id(),
                'content' => $content,
                'type' => TaskComment::TYPE_SYSTEM,
                'metadata' => $metadata,
                'is_internal' => true
            ]);
    }
}
