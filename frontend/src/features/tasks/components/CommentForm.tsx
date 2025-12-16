import React, { useState } from 'react';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { spacing } from '../../../shared/tokens/spacing';
import { radius } from '../../../shared/tokens/radius';

export interface CommentFormProps {
  /** Task ID */
  taskId: string | number;
  /** Parent comment ID (for replies) */
  parentId?: string | number;
  /** Initial content (for editing) */
  initialContent?: string;
  /** Submit handler */
  onSubmit: (content: string) => Promise<void>;
  /** Cancel handler */
  onCancel?: () => void;
  /** Loading state */
  loading?: boolean;
  /** Placeholder text */
  placeholder?: string;
  /** Submit button label */
  submitLabel?: string;
}

/**
 * CommentForm - Form component for creating/editing task comments
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const CommentForm: React.FC<CommentFormProps> = ({
  taskId,
  parentId,
  initialContent = '',
  onSubmit,
  onCancel,
  loading = false,
  placeholder = 'Add a comment...',
  submitLabel = 'Comment',
}) => {
  const [content, setContent] = useState(initialContent);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!content.trim() || isSubmitting) return;

    setIsSubmitting(true);
    try {
      await onSubmit(content);
      setContent('');
    } catch (error) {
      console.error('Failed to submit comment:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-3" data-testid="comment-form">
      <div>
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder={placeholder}
          rows={3}
          disabled={loading || isSubmitting}
          style={{
            width: '100%',
            padding: spacing.md,
            borderRadius: radius.md,
            border: '1px solid var(--border)',
            backgroundColor: 'var(--surface)',
            color: 'var(--text)',
            fontSize: '14px',
            fontFamily: 'inherit',
            resize: 'vertical',
          }}
          className="focus:outline-none focus:ring-2 focus:ring-[var(--ring)]"
          aria-label="Comment input"
        />
      </div>
      <div className="flex items-center gap-2 justify-end">
        {onCancel && (
          <Button
            type="button"
            onClick={onCancel}
            disabled={loading || isSubmitting}
            style={{
              padding: `${spacing.sm}px ${spacing.md}px`,
              borderRadius: radius.sm,
            }}
          >
            Cancel
          </Button>
        )}
        <Button
          type="submit"
          disabled={!content.trim() || loading || isSubmitting}
          style={{
            padding: `${spacing.sm}px ${spacing.md}px`,
            borderRadius: radius.sm,
            backgroundColor: 'var(--accent)',
            color: 'white',
          }}
        >
          {isSubmitting ? 'Submitting...' : submitLabel}
        </Button>
      </div>
    </form>
  );
};

