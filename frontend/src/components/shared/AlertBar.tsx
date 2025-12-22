import React, { memo } from 'react';
import { Card } from '../ui/primitives/Card';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';
import { shadows } from '../../shared/tokens/shadows';

export interface Alert {
  /** Alert ID */
  id: string | number;
  /** Alert message */
  message: string;
  /** Alert type/severity */
  type?: 'error' | 'warning' | 'info' | 'success';
  /** Alert priority (higher = more important) */
  priority?: number;
  /** Creation timestamp */
  created_at?: string | Date;
  /** Whether alert is dismissed */
  dismissed?: boolean;
  /** Optional metadata */
  metadata?: Record<string, any>;
}

export interface AlertBarProps {
  /** Array of alerts to display */
  alerts?: Alert[];
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Handler for dismissing an alert */
  onDismiss?: (id: string | number) => void;
  /** Handler for dismissing all alerts */
  onDismissAll?: () => void;
  /** Handler for clicking on an alert */
  onAlertClick?: (alert: Alert) => void;
  /** Maximum number of alerts to display (default: 3) */
  maxDisplay?: number;
  /** Optional className */
  className?: string;
}

/**
 * AlertBar - Global alert bar component for all pages
 * 
 * Displays system-wide alerts with priority-based ordering and dismiss functionality.
 * Follows Apple-style design spec with tokens, spacing, and cards.
 * 
 * @example
 * ```tsx
 * <AlertBar
 *   alerts={[
 *     { id: 1, message: 'System maintenance scheduled', type: 'info' },
 *     { id: 2, message: 'Task deadline approaching', type: 'warning' },
 *   ]}
 *   onDismiss={(id) => markAsRead(id)}
 *   onDismissAll={() => markAllAsRead()}
 * />
 * ```
 */
export const AlertBar: React.FC<AlertBarProps> = memo(({
  alerts = [],
  loading = false,
  error = null,
  onDismiss,
  onDismissAll,
  onAlertClick,
  maxDisplay = 3,
  className = '',
}) => {
  if (loading) {
    return (
      <Card
        style={{
          padding: spacing.md,
          borderRadius: radius.lg,
          boxShadow: shadows.xs,
        }}
        className={className}
        data-testid="alert-bar"
      >
        <div className="animate-pulse">
          <div className="h-4 bg-[var(--muted-surface)] rounded w-1/4"></div>
        </div>
      </Card>
    );
  }

  if (error) {
    return (
      <Card
        role="alert"
        aria-live="polite"
        style={{
          padding: spacing.md,
          borderRadius: radius.lg,
          boxShadow: shadows.xs,
          borderLeft: '4px solid var(--color-semantic-danger-600)',
        }}
        className={className}
        data-testid="alert-bar"
      >
        <div className="text-center" style={{ color: 'var(--muted)' }}>
          <p className="text-sm">Failed to load alerts</p>
        </div>
      </Card>
    );
  }

  // Filter out dismissed alerts and sort by priority (higher first)
  const activeAlerts = alerts
    .filter((alert) => !alert.dismissed)
    .sort((a, b) => (b.priority || 0) - (a.priority || 0))
    .slice(0, maxDisplay);

  if (activeAlerts.length === 0) {
    return null;
  }

  const getAlertColor = (type?: string) => {
    switch (type) {
      case 'error':
        return {
          bg: 'var(--color-semantic-danger-50)',
          border: 'var(--color-semantic-danger-200)',
          text: 'var(--color-semantic-danger-900)',
        };
      case 'warning':
        return {
          bg: 'var(--color-semantic-warning-50)',
          border: 'var(--color-semantic-warning-200)',
          text: 'var(--color-semantic-warning-900)',
        };
      case 'info':
        return {
          bg: 'var(--color-semantic-info-50)',
          border: 'var(--color-semantic-info-200)',
          text: 'var(--color-semantic-info-900)',
        };
      case 'success':
        return {
          bg: 'var(--color-semantic-success-50)',
          border: 'var(--color-semantic-success-200)',
          text: 'var(--color-semantic-success-900)',
        };
      default:
        return {
          bg: 'var(--surface)',
          border: 'var(--border)',
          text: 'var(--text)',
        };
    }
  };

  return (
    <Card
      style={{
        padding: spacing.md,
        borderRadius: radius.lg,
        boxShadow: shadows.xs,
        borderLeft: '4px solid var(--color-semantic-info-600)',
      }}
      className={className}
      data-testid="alert-bar"
    >
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 space-y-2">
          {activeAlerts.map((alert) => {
            const colors = getAlertColor(alert.type);
            return (
              <div
                key={alert.id}
                style={{
                  padding: spacing.sm,
                  borderRadius: radius.md,
                  border: `1px solid ${colors.border}`,
                  backgroundColor: colors.bg,
                  color: colors.text,
                  cursor: onAlertClick && alert.metadata?.project_id ? 'pointer' : 'default',
                }}
                onClick={(e) => {
                  if (onAlertClick && alert.metadata?.project_id) {
                    // Only trigger if clicking on the alert container, not on dismiss button
                    if ((e.target as HTMLElement).closest('button[aria-label*="Dismiss"]')) {
                      return;
                    }
                    onAlertClick(alert);
                  }
                }}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1">
                    {alert.type && (
                      <div className="flex items-center gap-2 mb-1">
                        <span
                          className="text-xs font-medium uppercase"
                          style={{ color: colors.text }}
                        >
                          {alert.type}
                        </span>
                        {alert.created_at && (
                          <span
                            className="text-xs"
                            style={{ color: 'var(--muted)' }}
                          >
                            {new Date(alert.created_at).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    )}
                    {onAlertClick && alert.metadata?.project_id ? (
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onAlertClick(alert);
                        }}
                        className="text-sm font-medium text-left hover:underline cursor-pointer transition-all"
                        style={{ color: colors.text }}
                      >
                        {alert.message}
                      </button>
                    ) : (
                      <p className="text-sm font-medium">{alert.message}</p>
                    )}
                  </div>
                  {onDismiss && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        onDismiss(alert.id);
                      }}
                      aria-label={`Dismiss alert ${alert.id}`}
                      className="h-6 w-6 p-0 flex items-center justify-center hover:opacity-70 transition-opacity"
                      style={{ color: colors.text }}
                    >
                      ×
                    </button>
                  )}
                </div>
              </div>
            );
          })}
          {alerts.length > maxDisplay && (
            <p className="text-xs" style={{ color: 'var(--muted)' }}>
              +{alerts.length - maxDisplay} more alerts
            </p>
          )}
        </div>
        {onDismissAll && activeAlerts.length > 0 && (
          <button
            onClick={onDismissAll}
            aria-label="Dismiss all alerts"
            className="text-sm underline"
            style={{ color: 'var(--color-semantic-primary-600)' }}
          >
            Dismiss all
          </button>
        )}
      </div>
    </Card>
  );
});

AlertBar.displayName = 'AlertBar';

