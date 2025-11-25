import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { DashboardMetrics, Trend } from '../../entities/dashboard/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface KpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<DashboardMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewProjects?: () => void;
  onViewTasks?: () => void;
  onViewPendingTasks?: () => void;
  onViewOverdueTasks?: () => void;
  onViewTeamMembers?: () => void;
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
    ? t('dashboard.vsLastWeek', { defaultValue: 'vs last week' })
    : t('dashboard.vsLastMonth', { defaultValue: 'vs last month' });

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
 * KpiStrip - Reusable KPI strip component
 * 
 * Displays 4 key metrics:
 * - Total Projects (with active count)
 * - Total Tasks (with completed count)
 * - Pending Tasks (with overdue count)
 * - Team Members (with active status)
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Responsive grid layout
 * - Accessibility support
 * - Trend indicators
 */
export const KpiStrip: React.FC<KpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewProjects,
  onViewTasks,
  onViewPendingTasks,
  onViewOverdueTasks,
  onViewTeamMembers,
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
              {t('dashboard.metricsError', { defaultValue: 'Failed to load metrics' })}
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
      aria-label="Dashboard metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Projects */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewProjects?.() || navigate('/app/projects');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewProjects?.() || navigate('/app/projects');
          }
        }}
        aria-label="View all projects"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.totalProjects', { defaultValue: 'Total Projects' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.totalProjects}
              </p>
              <TrendIndicator trend={data.trends?.totalProjects} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.activeProjects} active projects`}>
                {data.activeProjects} active
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Total Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewTasks?.() || navigate('/app/tasks');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewTasks?.() || navigate('/app/tasks');
          }
        }}
        aria-label="View all tasks"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.totalTasks', { defaultValue: 'Total Tasks' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.totalTasks}
              </p>
              <TrendIndicator trend={data.trends?.totalTasks} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.completedTasks} completed tasks`}>
                {data.completedTasks} completed
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pending Tasks */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          // If there are overdue tasks, navigate to overdue, otherwise pending
          if (data.overdueTasks > 0) {
            onViewOverdueTasks?.() || navigate('/app/tasks?status=overdue');
          } else {
            onViewPendingTasks?.() || navigate('/app/tasks?status=pending');
          }
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (data.overdueTasks > 0) {
              onViewOverdueTasks?.() || navigate('/app/tasks?status=overdue');
            } else {
              onViewPendingTasks?.() || navigate('/app/tasks?status=pending');
            }
          }
        }}
        aria-label={data.overdueTasks > 0 ? "View overdue tasks" : "View pending tasks"}
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.pendingTasks', { defaultValue: 'Pending Tasks' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.pendingTasks}
              </p>
              <TrendIndicator trend={data.trends?.pendingTasks} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="warning" aria-label={`${data.overdueTasks} overdue tasks`}>
                {data.overdueTasks} overdue
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Team Members */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewTeamMembers?.() || navigate('/app/users');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewTeamMembers?.() || navigate('/app/users');
          }
        }}
        aria-label="View team members"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.teamMembers', { defaultValue: 'Team Members' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.teamMembers}
              </p>
              <TrendIndicator trend={data.trends?.teamMembers} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" aria-label="Active team members">
                Active
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

KpiStrip.displayName = 'KpiStrip';

export default KpiStrip;

