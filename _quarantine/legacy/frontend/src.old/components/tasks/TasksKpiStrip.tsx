import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { TasksMetrics, Trend } from '../../entities/app/tasks/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface TasksKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<TasksMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllTasks?: () => void;
  onViewPendingTasks?: () => void;
  onViewInProgressTasks?: () => void;
  onViewCompletedTasks?: () => void;
  onViewOverdueTasks?: () => void;
  /** Optional className */
  className?: string;
}

/**
 * Trend Indicator Component
 */
const TrendIndicator: React.FC<{ trend?: Trend; period?: string }> = ({ trend, period = 'week' }) => {
  const { t } = useI18n();
  
  if (!trend || trend.direction === 'neutral' || trend.value === 0) {
    return null;
  }

  const periodLabel = period === 'week' 
    ? t('tasks.vsLastWeek', { defaultValue: 'vs last week' })
    : t('tasks.vsLastMonth', { defaultValue: 'vs last month' });

  const isPositive = trend.direction === 'up';
  const colorClass = isPositive 
    ? 'text-[var(--color-semantic-success-600)]' 
    : 'text-[var(--color-semantic-danger-600)]';
  
  const arrow = isPositive ? '↑' : '↓';

  return (
    <div className={`flex items-center gap-1 text-xs font-medium ${colorClass}`}>
      <span aria-hidden="true">{arrow}</span>
      <span>{trend.value}%</span>
      <span className="text-[var(--color-text-muted)] font-normal">{periodLabel}</span>
    </div>
  );
};

/**
 * TasksKpiStrip - KPI strip component for Tasks page
 * 
 * Displays 4 key metrics:
 * - Total Tasks
 * - Pending Tasks
 * - In Progress Tasks
 * - Completed Tasks
 * - Overdue Tasks (if any)
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Responsive grid layout
 * - Accessibility support
 * - Trend indicators
 */
export const TasksKpiStrip: React.FC<TasksKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllTasks,
  onViewPendingTasks,
  onViewInProgressTasks,
  onViewCompletedTasks,
  onViewOverdueTasks,
  className,
}) => {
  const { t } = useI18n();
  const navigate = useNavigate();

  if (loading) {
    return <MetricsSkeleton />;
  }

  if (error) {
    return (
      <Card role="alert" aria-live="polite">
        <CardContent className="p-6">
          <div className="text-center text-[var(--color-text-muted)]">
            <p className="mb-2">
              {t('tasks.metricsError', { defaultValue: 'Failed to load metrics' })}
            </p>
            {onRefresh && (
              <button
                onClick={onRefresh}
                className="text-sm text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                aria-label="Retry loading metrics"
              >
                {t('common.retry', { defaultValue: 'Retry' })}
              </button>
            )}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!metrics?.data) {
    return null;
  }

  const { data } = metrics;

  return (
    <div
      className={`grid grid-cols-2 gap-4 md:grid-cols-4 ${className || ''}`}
      role="region"
      aria-label="Tasks metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllTasks?.() || navigate('/app/tasks');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllTasks?.() || navigate('/app/tasks');
          }
        }}
        aria-label="View all tasks"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('tasks.totalTasks', { defaultValue: 'Total Tasks' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_tasks}
              </p>
              <TrendIndicator trend={data.trends?.total_tasks} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pending Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewPendingTasks?.() || navigate('/app/tasks?status=backlog');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewPendingTasks?.() || navigate('/app/tasks?status=backlog');
          }
        }}
        aria-label="View pending tasks"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('tasks.pendingTasks', { defaultValue: 'Pending Tasks' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.pending_tasks}
              </p>
              <TrendIndicator trend={data.trends?.pending_tasks} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="warning" aria-label={`${data.pending_tasks} pending tasks`}>
                {t('tasks.pending', { defaultValue: 'Pending' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* In Progress Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewInProgressTasks?.() || navigate('/app/tasks?status=in_progress');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewInProgressTasks?.() || navigate('/app/tasks?status=in_progress');
          }
        }}
        aria-label="View in progress tasks"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('tasks.inProgressTasks', { defaultValue: 'In Progress' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.in_progress_tasks}
              </p>
              <TrendIndicator trend={data.trends?.in_progress_tasks} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.in_progress_tasks} in progress tasks`}>
                {t('tasks.inProgress', { defaultValue: 'In Progress' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Completed Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewCompletedTasks?.() || navigate('/app/tasks?status=done');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewCompletedTasks?.() || navigate('/app/tasks?status=done');
          }
        }}
        aria-label="View completed tasks"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('tasks.completedTasks', { defaultValue: 'Completed Tasks' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.completed_tasks}
              </p>
              <TrendIndicator trend={data.trends?.completed_tasks} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.completed_tasks} completed tasks`}>
                {t('tasks.completed', { defaultValue: 'Completed' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Overdue Tasks - Show as 5th card if there are overdue tasks, otherwise combine with pending */}
      {data.overdue_tasks > 0 && (
        <Card 
          className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] col-span-2 md:col-span-1"
          onClick={() => {
            onViewOverdueTasks?.() || navigate('/app/tasks?status=overdue');
          }}
          role="button"
          tabIndex={0}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              onViewOverdueTasks?.() || navigate('/app/tasks?status=overdue');
            }
          }}
          aria-label="View overdue tasks"
        >
          <CardContent className="p-4">
            <div className="space-y-2">
              <p className="text-sm text-[var(--color-text-muted)]">
                {t('tasks.overdueTasks', { defaultValue: 'Overdue Tasks' })}
              </p>
              <div className="flex items-baseline justify-between">
                <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                  {data.overdue_tasks}
                </p>
                <TrendIndicator trend={data.trends?.overdue_tasks} period={data.period} />
              </div>
              <div className="flex items-center gap-2">
                <Badge tone="danger" aria-label={`${data.overdue_tasks} overdue tasks`}>
                  {t('tasks.overdue', { defaultValue: 'Overdue' })}
                </Badge>
              </div>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
});

TasksKpiStrip.displayName = 'TasksKpiStrip';

export default TasksKpiStrip;

