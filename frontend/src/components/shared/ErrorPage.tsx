import React from 'react';
import { Button } from '../ui/primitives/Button';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';

export interface ErrorPageProps {
  /** Error code (404, 500, etc.) */
  code?: number;
  /** Error title */
  title?: string;
  /** Error message */
  message?: string;
  /** Action button text */
  actionText?: string;
  /** Action button handler */
  onAction?: () => void;
  /** Show retry button */
  showRetry?: boolean;
  /** Retry handler */
  onRetry?: () => void;
}

/**
 * ErrorPage - Displays error pages (404, 500, etc.)
 * 
 * Follows Apple-style design spec with tokens and spacing.
 */
export const ErrorPage: React.FC<ErrorPageProps> = ({
  code = 500,
  title,
  message,
  actionText = 'Go Home',
  onAction,
  showRetry = false,
  onRetry,
}) => {
  const defaultMessages: Record<number, { title: string; message: string }> = {
    404: {
      title: 'Page Not Found',
      message: "The page you're looking for doesn't exist or has been moved.",
    },
    403: {
      title: 'Access Denied',
      message: "You don't have permission to access this resource.",
    },
    500: {
      title: 'Server Error',
      message: 'Something went wrong on our end. Please try again later.',
    },
    503: {
      title: 'Service Unavailable',
      message: 'The service is temporarily unavailable. Please try again later.',
    },
  };

  const errorInfo = defaultMessages[code] || defaultMessages[500];
  const displayTitle = title || errorInfo.title;
  const displayMessage = message || errorInfo.message;

  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '100vh',
        padding: spacing.xl,
        textAlign: 'center',
      }}
      data-testid="error-page"
    >
      <div
        style={{
          maxWidth: '600px',
          padding: spacing.xl,
        }}
      >
        <div
          style={{
            fontSize: '72px',
            fontWeight: 700,
            color: 'var(--muted)',
            marginBottom: spacing.md,
          }}
        >
          {code}
        </div>
        <h1
          style={{
            fontSize: '32px',
            fontWeight: 600,
            marginBottom: spacing.md,
          }}
        >
          {displayTitle}
        </h1>
        <p
          style={{
            fontSize: '16px',
            color: 'var(--muted)',
            marginBottom: spacing.xl,
          }}
        >
          {displayMessage}
        </p>
        <div className="flex gap-3 justify-center">
          {showRetry && onRetry && (
            <Button onClick={onRetry}>Retry</Button>
          )}
          <Button
            onClick={onAction || (() => (window.location.href = '/app'))}
            style={{
              padding: `${spacing.md}px ${spacing.lg}px`,
              borderRadius: radius.md,
            }}
          >
            {actionText}
          </Button>
        </div>
      </div>
    </div>
  );
};

