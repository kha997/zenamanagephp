import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { ProjectsMetrics, Trend } from '../../entities/app/projects/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface ProjectsKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<ProjectsMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllProjects?: () => void;
  onViewActiveProjects?: () => void;
  onViewCompletedProjects?: () => void;
  onViewOverdueProjects?: () => void;
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
    ? t('projects.vsLastWeek', { defaultValue: 'vs last week' })
    : t('projects.vsLastMonth', { defaultValue: 'vs last month' });

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
 * ProjectsKpiStrip - KPI strip component for Projects page
 * 
 * Displays 4 key metrics:
 * - Total Projects
 * - Active Projects
 * - Completed Projects
 * - Overdue Projects
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Responsive grid layout
 * - Accessibility support
 */
export const ProjectsKpiStrip: React.FC<ProjectsKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllProjects,
  onViewActiveProjects,
  onViewCompletedProjects,
  onViewOverdueProjects,
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
              {t('projects.metricsError', { defaultValue: 'Failed to load metrics' })}
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
      aria-label="Projects metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Projects */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllProjects?.() || navigate('/app/projects');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllProjects?.() || navigate('/app/projects');
          }
        }}
        aria-label="View all projects"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('projects.totalProjects', { defaultValue: 'Total Projects' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_projects}
              </p>
              <TrendIndicator trend={data.trends?.total_projects} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Active Projects */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewActiveProjects?.() || navigate('/app/projects?status=active');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewActiveProjects?.() || navigate('/app/projects?status=active');
          }
        }}
        aria-label="View active projects"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('projects.activeProjects', { defaultValue: 'Active Projects' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.active_projects}
              </p>
              <TrendIndicator trend={data.trends?.active_projects} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.active_projects} active projects`}>
                {t('projects.active', { defaultValue: 'Active' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Completed Projects */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewCompletedProjects?.() || navigate('/app/projects?status=completed');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewCompletedProjects?.() || navigate('/app/projects?status=completed');
          }
        }}
        aria-label="View completed projects"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('projects.completedProjects', { defaultValue: 'Completed Projects' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.completed_projects}
              </p>
              <TrendIndicator trend={data.trends?.completed_projects} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.completed_projects} completed projects`}>
                {t('projects.completed', { defaultValue: 'Completed' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Overdue Projects */}
      <Card 
        className={`hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98] ${
          data.overdue_projects === 0 ? 'opacity-60 cursor-not-allowed' : ''
        }`}
        onClick={() => {
          if (data.overdue_projects > 0) {
            onViewOverdueProjects?.() || navigate('/app/projects?status=overdue');
          }
        }}
        role="button"
        tabIndex={data.overdue_projects > 0 ? 0 : -1}
        onKeyDown={(e) => {
          if (data.overdue_projects > 0 && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onViewOverdueProjects?.() || navigate('/app/projects?status=overdue');
          }
        }}
        aria-label={data.overdue_projects > 0 ? "View overdue projects" : "No overdue projects"}
        aria-disabled={data.overdue_projects === 0}
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('projects.overdueProjects', { defaultValue: 'Overdue Projects' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.overdue_projects}
              </p>
              <TrendIndicator trend={data.trends?.overdue_projects} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="warning" aria-label={`${data.overdue_projects} overdue projects`}>
                {t('projects.overdue', { defaultValue: 'Overdue' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

ProjectsKpiStrip.displayName = 'ProjectsKpiStrip';

export default ProjectsKpiStrip;

