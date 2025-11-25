import React from 'react';
import { Button } from '../ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface EmptyStateProps {
  /** Icon or emoji */
  icon?: string | React.ReactNode;
  /** Title */
  title: string;
  /** Description */
  description?: string;
  /** Primary action button text */
  actionText?: string;
  /** Primary action handler */
  onAction?: () => void;
  /** Secondary action button text */
  secondaryActionText?: string;
  /** Secondary action handler */
  onSecondaryAction?: () => void;
}

/**
 * EmptyState - Displays empty state with CTA
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const EmptyState: React.FC<EmptyStateProps> = ({
  icon,
  title,
  description,
  actionText,
  onAction,
  secondaryActionText,
  onSecondaryAction,
}) => {
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: spacing.xl * 2,
        textAlign: 'center',
      }}
      data-testid="empty-state"
    >
      {icon && (
        <div
          style={{
            fontSize: '64px',
            marginBottom: spacing.lg,
          }}
        >
          {typeof icon === 'string' ? icon : icon}
        </div>
      )}
      <h3
        style={{
          fontSize: '20px',
          fontWeight: 600,
          marginBottom: spacing.md,
        }}
      >
        {title}
      </h3>
      {description && (
        <p
          style={{
            fontSize: '14px',
            color: 'var(--muted)',
            marginBottom: spacing.xl,
            maxWidth: '400px',
          }}
        >
          {description}
        </p>
      )}
      <div className="flex gap-3">
        {actionText && onAction && (
          <Button
            onClick={onAction}
            style={{
              padding: `${spacing.md}px ${spacing.lg}px`,
              borderRadius: radius.md,
            }}
          >
            {actionText}
          </Button>
        )}
        {secondaryActionText && onSecondaryAction && (
          <Button
            onClick={onSecondaryAction}
            style={{
              padding: `${spacing.md}px ${spacing.lg}px`,
              borderRadius: radius.md,
              backgroundColor: 'var(--muted-surface)',
            }}
          >
            {secondaryActionText}
          </Button>
        )}
      </div>
    </div>
  );
};

