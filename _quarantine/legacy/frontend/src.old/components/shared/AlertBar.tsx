import React, { useState, memo, useMemo, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { Button } from '../../shared/ui/button';
import { Skeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import { useMarkAlertAsRead, useMarkAllAlertsAsRead } from '../../entities/dashboard/hooks';
import type { DashboardAlert } from '../../entities/dashboard/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface AlertBarProps {
  /** Alerts data from API */
  alerts?: ApiResponse<DashboardAlert[]> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional dismiss handler for individual alert */
  onDismiss?: (alertId: string) => void;
  /** Optional dismiss all handler */
  onDismissAll?: () => void;
  /** Maximum alerts to display */
  maxDisplay?: number;
  /** Optional className */
  className?: string;
  /** Whether to show dismiss all button */
  showDismissAll?: boolean;
}

/**
 * AlertBar - Reusable alert bar component
 * 
 * Displays alerts with priority-based ordering:
 * - Critical alerts first
 * - High priority alerts
 * - Medium priority alerts
 * - Low priority alerts
 * 
 * Features:
 * - Priority-based display
 * - Dismiss functionality
 * - Collapsible when many alerts
 * - Loading and error states
 * - Accessibility support
 */
export const AlertBar: React.FC<AlertBarProps> = memo(({
  alerts,
  loading = false,
  error = null,
  onDismiss,
  onDismissAll,
  maxDisplay = 5,
  className,
  showDismissAll = true,
}) => {
  const { t } = useI18n();
  const [isExpanded, setIsExpanded] = useState(false);
  const markAlertAsRead = useMarkAlertAsRead();
  const markAllAlertsAsRead = useMarkAllAlertsAsRead();

  const handleDismiss = useCallback(async (alertId: string) => {
    try {
      await markAlertAsRead.mutateAsync(alertId);
      onDismiss?.(alertId);
    } catch (err) {
      console.error('Failed to dismiss alert:', err);
    }
  }, [markAlertAsRead, onDismiss]);

  const handleDismissAll = useCallback(async () => {
    try {
      await markAllAlertsAsRead.mutateAsync();
      onDismissAll?.();
    } catch (err) {
      console.error('Failed to dismiss all alerts:', err);
    }
  }, [markAllAlertsAsRead, onDismissAll]);

  const handleToggleExpand = useCallback(() => {
    requestAnimationFrame(() => {
      setIsExpanded(prev => !prev);
    });
  }, []);

  if (loading) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="animate-pulse">
                <Skeleton className="h-4 w-3/4 mb-2" />
                <Skeleton className="h-3 w-1/2" />
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card role="alert" aria-live="polite" className={className}>
        <CardHeader>
          <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-[var(--color-text-muted)]">
            {t('dashboard.alertsError', { defaultValue: 'Failed to load alerts' })}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!alerts?.data || alerts.data.length === 0) {
    return null; // Don't render empty alert bar
  }

  // Memoize sorted alerts to avoid recalculation
  const sortedAlerts = useMemo(() => {
    if (!alerts?.data || alerts.data.length === 0) return [];
    return [...alerts.data].sort((a, b) => {
      const priorityOrder: Record<string, number> = {
        critical: 4,
        high: 3,
        medium: 2,
        low: 1,
      };
      return (priorityOrder[b.severity] || 0) - (priorityOrder[a.severity] || 0);
    });
  }, [alerts]);

  const displayedAlerts = useMemo(() => {
    return isExpanded ? sortedAlerts : sortedAlerts.slice(0, maxDisplay);
  }, [sortedAlerts, isExpanded, maxDisplay]);

  const hasMoreAlerts = sortedAlerts.length > maxDisplay;

  const getSeverityColor = (severity: DashboardAlert['severity']) => {
    switch (severity) {
      case 'critical':
        return 'bg-[var(--color-semantic-danger-500)]';
      case 'high':
        return 'bg-[var(--color-semantic-warning-500)]';
      case 'medium':
        return 'bg-[var(--color-semantic-info-500)]';
      case 'low':
        return 'bg-[var(--color-semantic-neutral-400)]';
      default:
        return 'bg-[var(--color-semantic-info-500)]';
    }
  };

  const getSeverityBadgeTone = (severity: DashboardAlert['severity']): 'danger' | 'warning' | 'info' | 'neutral' => {
    switch (severity) {
      case 'critical':
        return 'danger';
      case 'high':
        return 'warning';
      case 'medium':
        return 'info';
      case 'low':
        return 'neutral';
      default:
        return 'info';
    }
  };

  return (
    <Card
      role="region"
      aria-label="Dashboard alerts"
      aria-live="polite"
      aria-atomic="false"
      className={className}
      style={{ contain: 'layout style' }}
    >
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>{t('dashboard.alerts', { defaultValue: 'Alerts' })}</CardTitle>
            <CardDescription>
              {t('dashboard.alertsDescription', { defaultValue: 'Recent notifications and alerts' })}
            </CardDescription>
          </div>
          {showDismissAll && sortedAlerts.length > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleDismissAll}
              aria-label="Dismiss all alerts"
            >
              {t('dashboard.dismissAll', { defaultValue: 'Dismiss All' })}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className="space-y-3">
          {displayedAlerts.map((alert) => (
            <div
              key={alert.id}
              className="flex items-start gap-3 p-3 rounded-[var(--radius-md)] bg-[var(--color-surface-muted)] transition-colors hover:bg-[var(--color-surface-muted)]/80"
              role="alert"
              aria-live="polite"
              style={{ willChange: 'background-color' }}
            >
              <div
                className={`h-2 w-2 rounded-full mt-2 flex-shrink-0 ${getSeverityColor(alert.severity)}`}
                aria-hidden="true"
              />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-[var(--color-text-primary)] truncate">
                  {alert.title}
                </p>
                <p className="text-xs text-[var(--color-text-muted)] truncate mt-1">
                  {alert.message}
                </p>
                <p className="text-xs text-[var(--color-text-muted)] mt-1">
                  {new Date(alert.createdAt).toLocaleDateString()}
                </p>
              </div>
              <div className="flex items-center gap-2 flex-shrink-0">
                <Badge tone={getSeverityBadgeTone(alert.severity)} aria-label={`Alert severity: ${alert.severity}`}>
                  {alert.severity}
                </Badge>
                {onDismiss && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDismiss(alert.id)}
                    aria-label={`Dismiss alert: ${alert.title}`}
                    className="h-6 w-6 p-0"
                  >
                    ×
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>

        {hasMoreAlerts && (
          <div className="mt-4 pt-3 border-t border-[var(--color-border-subtle)]">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleToggleExpand}
              className="w-full"
              aria-expanded={isExpanded}
              aria-controls="alert-list"
            >
              {isExpanded
                ? t('dashboard.showLess', { defaultValue: 'Show Less' })
                : t('dashboard.showMore', { defaultValue: `Show ${sortedAlerts.length - maxDisplay} More` })}
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  );
});

AlertBar.displayName = 'AlertBar';

export default AlertBar;

