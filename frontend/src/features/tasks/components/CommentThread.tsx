import React, { memo } from 'react';
import { spacing } from '../../../shared/tokens/spacing';
import { radius } from '../../../shared/tokens/radius';
import type { TaskComment } from '../api';

export interface CommentThreadProps {
  /** Comment data */
  comment: TaskComment;
  /** Current user ID */
  currentUserId?: string | number;
  /** Handler for replying to comment */
  onReply?: (commentId: string | number) => void;
  /** Handler for editing comment */
  onEdit?: (comment: TaskComment) => void;
  /** Handler for deleting comment */
  onDelete?: (commentId: string | number) => void;
  /** Whether to show reply form */
  showReplyForm?: boolean;
}

/**
 * CommentThread - Component to display a single comment with replies
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const CommentThread: React.FC<CommentThreadProps> = memo(({
  comment,
  currentUserId,
  onReply,
  onEdit,
  onDelete,
  showReplyForm = false,
}) => {
  const isOwnComment = currentUserId && comment.user_id === currentUserId;
  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
      return 'Just now';
    } else if (diffMins < 60) {
      return `${diffMins}m ago`;
    } else if (diffHours < 24) {
      return `${diffHours}h ago`;
    } else if (diffDays < 7) {
      return `${diffDays}d ago`;
    } else {
      return date.toLocaleDateString();
    }
  };

  return (
    <div
      className="flex items-start gap-3"
      style={{
        padding: spacing.sm,
        borderRadius: radius.md,
      }}
      data-testid={`comment-thread-${comment.id}`}
    >
      {/* Avatar */}
      <div
        style={{
          width: 32,
          height: 32,
          borderRadius: '50%',
          backgroundColor: 'var(--muted-surface)',
          color: 'var(--muted)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '12px',
          fontWeight: 600,
          flexShrink: 0,
        }}
      >
        {comment.user?.avatar ? (
          <img
            src={comment.user.avatar}
            alt={comment.user.name}
            style={{ width: '100%', height: '100%', borderRadius: '50%' }}
          />
        ) : (
          <span>
            {comment.user?.name?.charAt(0).toUpperCase() || 'U'}
          </span>
        )}
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 mb-1">
          <span className="text-sm font-medium" style={{ color: 'var(--text)' }}>
            {comment.user?.name || 'Unknown User'}
          </span>
          <span className="text-xs" style={{ color: 'var(--muted)' }}>
            {formatTimestamp(comment.created_at)}
          </span>
          {comment.is_pinned && (
            <span className="text-xs" style={{ color: 'var(--color-semantic-warning-600)' }}>
              Pinned
            </span>
          )}
        </div>
        <p className="text-sm" style={{ color: 'var(--text)' }}>
          {comment.content}
        </p>
        <div className="flex items-center gap-3 mt-2">
          {onReply && (
            <button
              onClick={() => onReply(comment.id)}
              className="text-xs underline"
              style={{ color: 'var(--color-semantic-primary-600)' }}
              aria-label={`Reply to comment ${comment.id}`}
            >
              Reply
            </button>
          )}
          {isOwnComment && onEdit && (
            <button
              onClick={() => onEdit(comment)}
              className="text-xs underline"
              style={{ color: 'var(--color-semantic-primary-600)' }}
              aria-label={`Edit comment ${comment.id}`}
            >
              Edit
            </button>
          )}
          {isOwnComment && onDelete && (
            <button
              onClick={() => onDelete(comment.id)}
              className="text-xs underline"
              style={{ color: 'var(--color-semantic-danger-600)' }}
              aria-label={`Delete comment ${comment.id}`}
            >
              Delete
            </button>
          )}
        </div>

        {/* Replies */}
        {comment.replies && comment.replies.length > 0 && (
          <div className="mt-3 ml-4 space-y-2" style={{ borderLeft: '2px solid var(--border)' }}>
            {comment.replies.map((reply) => (
              <CommentThread
                key={reply.id}
                comment={reply}
                currentUserId={currentUserId}
                onReply={onReply}
                onEdit={onEdit}
                onDelete={onDelete}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
});

CommentThread.displayName = 'CommentThread';

