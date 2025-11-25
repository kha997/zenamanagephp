import React, { memo } from 'react';
import { Card, CardContent } from '../../../shared/ui/card';
import { Badge } from '../../../shared/ui/badge';
import { Button } from '../../../shared/ui/button';
import type { DashboardAlert } from '../types';

export interface AlertBannerProps {
  /** Alerts data from API */
  alerts?: DashboardAlert[];
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Handler for marking alert as read */
  onMarkAsRead?: (id: string | number) => void;
  /** Handler for marking all alerts as read */
  onMarkAllAsRead?: () => void;
  /** Optional className */
  className?: string;
}

/**
 * AlertBanner - Component to display dashboard alerts
 */
export const AlertBanner: React.FC<AlertBannerProps> = memo(({
  alerts = [],
  loading = false,
  error = null,
  onMarkAsRead,
  onMarkAllAsRead,
  className,
}) => {
  if (loading) {
    return (
      <Card className={className}>
        <CardContent className="p-4">
          <div className="animate-pulse">
            <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-1/4"></div>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card role="alert" aria-live="polite" className={className}>
        <CardContent className="p-4">
          <div className="text-center text-[var(--color-text-muted)]">
            <p className="text-sm">Failed to load alerts</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!alerts || alerts.length === 0) {
    return null;
  }

  const getAlertColor = (type: DashboardAlert['type']) => {
    switch (type) {
      case 'error':
        return 'bg-[var(--color-semantic-danger-50)] border-[var(--color-semantic-danger-200)] text-[var(--color-semantic-danger-900)]';
      case 'warning':
        return 'bg-[var(--color-semantic-warning-50)] border-[var(--color-semantic-warning-200)] text-[var(--color-semantic-warning-900)]';
      case 'info':
        return 'bg-[var(--color-semantic-info-50)] border-[var(--color-semantic-info-200)] text-[var(--color-semantic-info-900)]';
      case 'success':
        return 'bg-[var(--color-semantic-success-50)] border-[var(--color-semantic-success-200)] text-[var(--color-semantic-success-900)]';
      default:
        return 'bg-[var(--color-surface-subtle)] border-[var(--color-border-subtle)]';
    }
  };

  const getBadgeTone = (type: DashboardAlert['type']) => {
    switch (type) {
      case 'error':
        return 'danger';
      case 'warning':
        return 'warning';
      case 'info':
        return 'info';
      case 'success':
        return 'success';
      default:
        return 'neutral';
    }
  };

  return (
    <Card className={`${className} border-l-4`} data-testid="alert-banner">
      <CardContent className="p-4">
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1 space-y-2">
            {alerts.slice(0, 3).map((alert) => (
              <div
                key={alert.id}
                className={`p-3 rounded-lg border ${getAlertColor(alert.type)}`}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <Badge tone={getBadgeTone(alert.type)} className="text-xs">
                        {alert.type}
                      </Badge>
                      <span className="text-xs text-[var(--color-text-muted)]">
                        {new Date(alert.created_at).toLocaleDateString()}
                      </span>
                    </div>
                    <p className="text-sm font-medium">{alert.message}</p>
                  </div>
                  {onMarkAsRead && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => onMarkAsRead(alert.id)}
                      aria-label={`Mark alert ${alert.id} as read`}
                      className="h-6 w-6 p-0"
                    >
                      ×
                    </Button>
                  )}
                </div>
              </div>
            ))}
            {alerts.length > 3 && (
              <p className="text-xs text-[var(--color-text-muted)]">
                +{alerts.length - 3} more alerts
              </p>
            )}
          </div>
          {onMarkAllAsRead && alerts.length > 0 && (
            <Button
              variant="outline"
              size="sm"
              onClick={onMarkAllAsRead}
              aria-label="Mark all alerts as read"
            >
              Mark all as read
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
});

AlertBanner.displayName = 'AlertBanner';

