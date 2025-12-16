import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../../shared/ui/card';
import { Badge } from '../../../shared/ui/badge';
import { MetricsSkeleton } from '../../../shared/ui/skeleton';
import type { DashboardStats } from '../types';

export interface DashboardKpiStripProps {
  /** Stats data from API */
  stats?: DashboardStats | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllProjects?: () => void;
  onViewActiveProjects?: () => void;
  onViewAllTasks?: () => void;
  onViewOverdueTasks?: () => void;
  /** Optional className */
  className?: string;
}

/**
 * DashboardKpiStrip - KPI strip component for Dashboard page
 * Displays key metrics: Projects, Tasks, Users
 */
export const DashboardKpiStrip: React.FC<DashboardKpiStripProps> = memo(({
  stats,
  loading = false,
  error = null,
  onRefresh,
  onViewAllProjects,
  onViewActiveProjects,
  onViewAllTasks,
  onViewOverdueTasks,
  className,
}) => {
  const navigate = useNavigate();

  if (loading) {
    return <MetricsSkeleton />;
  }

  if (error) {
    return (
      <Card role="alert" aria-live="polite">
        <CardContent className="p-6">
          <div className="text-center text-[var(--color-text-muted)]">
            <p className="mb-2">Failed to load dashboard metrics</p>
            {onRefresh && (
              <button
                onClick={onRefresh}
                className="text-sm text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                aria-label="Retry loading metrics"
              >
                Retry
              </button>
            )}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!stats) {
    return null;
  }

  const handleViewProjects = () => {
    if (onViewAllProjects) {
      onViewAllProjects();
    } else {
      navigate('/app/projects');
    }
  };

  const handleViewActiveProjects = () => {
    if (onViewActiveProjects) {
      onViewActiveProjects();
    } else {
      navigate('/app/projects?status=active');
    }
  };

  const handleViewTasks = () => {
    if (onViewAllTasks) {
      onViewAllTasks();
    } else {
      navigate('/app/tasks');
    }
  };

  const handleViewOverdueTasks = () => {
    if (onViewOverdueTasks) {
      onViewOverdueTasks();
    } else {
      navigate('/app/tasks?status=overdue');
    }
  };

  return (
    <Card className={className} data-testid="kpi-strip">
      <CardContent className="p-6">
        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          {/* Total Projects */}
          <div className="flex flex-col gap-2" data-testid="kpi-total-projects">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                Total Projects
              </span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-[var(--color-text-primary)]">
                {stats.projects.total}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" className="text-xs">
                {stats.projects.active} active
              </Badge>
              <button
                onClick={handleViewProjects}
                className="text-xs text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                aria-label="View all projects"
              >
                View all
              </button>
            </div>
          </div>

          {/* Active Projects */}
          <div className="flex flex-col gap-2" data-testid="kpi-active-projects">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                Active Projects
              </span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-[var(--color-semantic-success-600)]">
                {stats.projects.active}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" className="text-xs">
                {stats.projects.completed} completed
              </Badge>
              <button
                onClick={handleViewActiveProjects}
                className="text-xs text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                aria-label="View active projects"
              >
                View
              </button>
            </div>
          </div>

          {/* Total Tasks */}
          <div className="flex flex-col gap-2" data-testid="kpi-total-tasks">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                Total Tasks
              </span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-[var(--color-text-primary)]">
                {stats.tasks.total}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" className="text-xs">
                {stats.tasks.completed} completed
              </Badge>
              <button
                onClick={handleViewTasks}
                className="text-xs text-[var(--color-semantic-primary-600)] hover:text-[var(--color-semantic-primary-700)] underline"
                aria-label="View all tasks"
              >
                View all
              </button>
            </div>
          </div>

          {/* In Progress Tasks */}
          <div className="flex flex-col gap-2" data-testid="kpi-in-progress-tasks">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                In Progress
              </span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-bold text-[var(--color-semantic-warning-600)]">
                {stats.tasks.in_progress}
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" className="text-xs">
                {stats.tasks.completed} done
              </Badge>
            </div>
          </div>

          {/* Overdue Tasks */}
          <div className="flex flex-col gap-2" data-testid="kpi-overdue-tasks">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                Overdue Tasks
              </span>
            </div>
            <div className="flex items-baseline gap-2">
              <span className={`text-2xl font-bold ${
                stats.tasks.overdue > 0 
                  ? 'text-[var(--color-semantic-danger-600)]' 
                  : 'text-[var(--color-text-primary)]'
              }`}>
                {stats.tasks.overdue}
              </span>
            </div>
            <div className="flex items-center gap-2">
              {stats.tasks.overdue > 0 && (
                <button
                  onClick={handleViewOverdueTasks}
                  className="text-xs text-[var(--color-semantic-danger-600)] hover:text-[var(--color-semantic-danger-700)] underline"
                  aria-label="View overdue tasks"
                >
                  View overdue
                </button>
              )}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
});

DashboardKpiStrip.displayName = 'DashboardKpiStrip';

