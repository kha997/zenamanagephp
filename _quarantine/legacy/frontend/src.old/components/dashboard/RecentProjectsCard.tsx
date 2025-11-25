import React, { memo, useMemo, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardHeader, CardTitle, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { Button } from '../../shared/ui/button';
import { Skeleton } from '../../shared/ui/skeleton';
import { Progress } from '../../shared/ui/progress';
import { useI18n } from '../../app/i18n-context';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface RecentProject {
  id: string | number;
  name: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  progress: number;
  updated_at: string;
  owner?: {
    id: string | number;
    name: string;
  };
  created_by_name?: string;
}

export interface RecentProjectsCardProps {
  /** Projects data from API */
  projects?: ApiResponse<RecentProject[]> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Maximum projects to display */
  limit?: number;
  /** Optional view all handler */
  onViewAll?: () => void;
  /** Optional className */
  className?: string;
}

/**
 * RecentProjectsCard - Dashboard component for displaying recent projects
 * 
 * Displays recent projects with:
 * - Project name and status
 * - Progress indicator
 * - Owner information
 * - Link to project detail
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Empty states với CTA
 * - Accessibility support
 */
export const RecentProjectsCard: React.FC<RecentProjectsCardProps> = memo(({
  projects,
  loading = false,
  error = null,
  limit = 5,
  onViewAll,
  className,
}) => {
  const { t } = useI18n();

  const getStatusBadgeTone = (status: RecentProject['status']): 'neutral' | 'primary' | 'success' | 'warning' | 'danger' => {
    switch (status) {
      case 'active':
        return 'success';
      case 'completed':
        return 'success';
      case 'planning':
        return 'primary';
      case 'on_hold':
        return 'warning';
      case 'cancelled':
        return 'danger';
      default:
        return 'neutral';
    }
  };

  const formatDate = useCallback((dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }, []);

  const projectList = useMemo(() => {
    // Handle different response structures
    if (!projects) return [];
    
    // If it's already an array (direct data)
    if (Array.isArray(projects)) {
      return projects;
    }
    
    // If it's ApiResponse with data property (most common case)
    if (projects.data && Array.isArray(projects.data)) {
      return projects.data;
    }
    
    // If it's wrapped in success: true, data: [...] (API response format)
    if (projects.success && Array.isArray(projects.data)) {
      return projects.data;
    }
    
    return [];
  }, [projects]);

  return (
    <Card
      role="region"
      aria-label="Recent projects"
      className={className}
      style={{ contain: 'layout style' }}
    >
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle>{t('dashboard.recentProjects', { defaultValue: 'Recent Projects' })}</CardTitle>
          {onViewAll && (
            <Button
              variant="ghost"
              size="sm"
              onClick={onViewAll}
              aria-label="View all projects"
            >
              {t('common.viewAll', { defaultValue: 'View all' })}
            </Button>
          )}
        </div>
        <p className="text-sm text-[var(--color-text-muted)] mt-1">
          {t('dashboard.recentProjectsDescription', { defaultValue: 'Projects you\'ve recently worked on' })}
        </p>
      </CardHeader>

      <CardContent>
        {loading && (
          <div className="space-y-4" role="status" aria-live="polite" aria-label="Loading projects">
            {Array.from({ length: limit }).map((_, i) => (
              <div key={i} className="space-y-2">
                <div className="flex items-center justify-between">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-5 w-16" />
                </div>
                <Skeleton className="h-2 w-full" />
                <div className="flex items-center justify-between">
                  <Skeleton className="h-3 w-24" />
                  <Skeleton className="h-3 w-16" />
                </div>
              </div>
            ))}
          </div>
        )}

        {error && (
          <div className="text-center py-8" role="alert">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.projectsError', { defaultValue: 'Failed to load projects' })}
            </p>
          </div>
        )}

        {!loading && !error && projectList.length > 0 && (
          <div className="space-y-4" role="list" aria-label="Projects list">
            {projectList.slice(0, limit).map((project) => (
              <Link
                key={project.id}
                to={`/app/projects/${project.id}`}
                className="block p-3 rounded-[var(--radius-md)] border border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-muted)] transition-colors"
                role="listitem"
                style={{ willChange: 'background-color' }}
              >
                <div className="flex items-start justify-between gap-3 mb-2">
                  <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-medium text-[var(--color-text-primary)] truncate">
                      {project.name}
                    </h3>
                  </div>
                  <Badge tone={getStatusBadgeTone(project.status)}>
                    {project.status}
                  </Badge>
                </div>
                
                <div className="space-y-2">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-[var(--color-text-muted)]">
                      {t('dashboard.progress', { defaultValue: 'Progress' })}
                    </span>
                    <span className="text-[var(--color-text-primary)] font-medium">
                      {project.progress}%
                    </span>
                  </div>
                  <Progress value={project.progress} size="sm" />
                </div>

                <div className="flex items-center justify-between mt-2 text-xs text-[var(--color-text-muted)]">
                  {project.owner?.name || project.created_by_name ? (
                    <span>
                      {t('dashboard.owner', { defaultValue: 'Owner' })}: {project.owner?.name || project.created_by_name}
                    </span>
                  ) : (
                    <span></span>
                  )}
                  <span>{formatDate(project.updated_at)}</span>
                </div>
              </Link>
            ))}
          </div>
        )}

        {!loading && !error && projectList.length === 0 && (
          <div className="text-center py-8" role="status">
            <div className="text-4xl mb-4 opacity-50" aria-hidden="true">📁</div>
            <p className="text-sm text-[var(--color-text-muted)] mb-2">
              {t('dashboard.noProjects', { defaultValue: 'No recent projects' })}
            </p>
            <p className="text-xs text-[var(--color-text-muted)] mb-4">
              {t('dashboard.noProjectsDescription', { defaultValue: 'Projects you work on will appear here' })}
            </p>
            <Link to="/app/projects/create">
              <Button variant="primary" size="sm">
                {t('dashboard.createProject', { defaultValue: 'Create Project' })}
              </Button>
            </Link>
          </div>
        )}
      </CardContent>
    </Card>
  );
});

RecentProjectsCard.displayName = 'RecentProjectsCard';

export default RecentProjectsCard;
