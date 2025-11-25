import React, { memo, useMemo } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Skeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface Activity {
  id: string;
  type: string;
  action: string;
  description: string;
  timestamp: string;
  user?: {
    id: string;
    name: string;
    avatar?: string;
  };
}

export interface ActivityFeedProps {
  /** Activities data from API */
  activities?: ApiResponse<Activity[]> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Maximum items to display */
  limit?: number;
  /** Optional view all handler */
  onViewAll?: () => void;
  /** Optional className */
  className?: string;
  /** Whether to show header */
  showHeader?: boolean;
}

/**
 * ActivityFeed - Reusable activity feed component
 * 
 * Displays recent activities with:
 * - Activity type icons
 * - User information
 * - Relative timestamps
 * - Empty states
 * 
 * Features:
 * - Loading skeletons
 * - Error states
 * - Empty states với CTAs
 * - Accessibility support
 * - Auto-refresh ready (can be extended)
 */
export const ActivityFeed: React.FC<ActivityFeedProps> = memo(({
  activities,
  loading = false,
  error = null,
  limit = 10,
  onViewAll,
  className,
  showHeader = true,
}) => {
  const { t } = useI18n();

  const timeAgo = (date: string) => {
    const now = new Date();
    const activityDate = new Date(date);
    const diffInMinutes = Math.floor((now.getTime() - activityDate.getTime()) / (1000 * 60));

    if (diffInMinutes < 1) return t('common.justNow', { defaultValue: 'Just now' });
    if (diffInMinutes < 60) return t('common.minutesAgo', { defaultValue: `${diffInMinutes}m ago` }, diffInMinutes);
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return t('common.hoursAgo', { defaultValue: `${diffInHours}h ago` }, diffInHours);
    
    const diffInDays = Math.floor(diffInHours / 24);
    return t('common.daysAgo', { defaultValue: `${diffInDays}d ago` }, diffInDays);
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'project':
        return '📁';
      case 'task':
        return '✓';
      case 'user':
        return '👤';
      case 'comment':
        return '💬';
      default:
        return '📌';
    }
  };

  const activityList = useMemo(() => activities?.data || [], [activities]);

  return (
    <Card
      role="region"
      aria-label="Recent activity"
      aria-live="polite"
      aria-atomic="false"
      className={className}
      style={{ contain: 'layout style' }}
    >
      {showHeader && (
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>{t('dashboard.recentActivity', { defaultValue: 'Recent Activity' })}</CardTitle>
            {onViewAll && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onViewAll}
                aria-label="View all activities"
              >
                {t('common.viewAll', { defaultValue: 'View all' })}
              </Button>
            )}
          </div>
          <p className="text-sm text-[var(--color-text-muted)] mt-1">
            {t('dashboard.recentActivityDescription', { defaultValue: 'Latest updates and changes across your workspace' })}
          </p>
        </CardHeader>
      )}

      <CardContent>
        {loading && (
          <div className="space-y-4" role="status" aria-live="polite" aria-label="Loading activities">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-start gap-4">
                <Skeleton className="h-8 w-8 rounded-full" />
                <div className="flex-1">
                  <Skeleton className="h-4 w-full mb-2" />
                  <Skeleton className="h-3 w-2/3" />
                </div>
              </div>
            ))}
          </div>
        )}

        {error && (
          <div className="text-center py-8" role="alert">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('dashboard.activityError', { defaultValue: 'Failed to load activity' })}
            </p>
          </div>
        )}

        {!loading && !error && activityList.length > 0 && (
          <div className="space-y-4" role="list" aria-label="Activity list">
            {activityList.slice(0, limit).map((activity) => (
              <div
                key={activity.id}
                className="flex items-start gap-3"
                role="listitem"
              >
                <div className="flex-shrink-0">
                  <div
                    className="h-8 w-8 rounded-full bg-[var(--color-semantic-primary-100)] flex items-center justify-center text-lg"
                    aria-hidden="true"
                  >
                    {getActivityIcon(activity.type)}
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-[var(--color-text-primary)]">
                    {activity.description}
                  </p>
                  <div className="flex items-center gap-2 mt-1">
                    {activity.user && (
                      <span className="text-xs text-[var(--color-text-muted)]">
                        {activity.user.name}
                      </span>
                    )}
                    <span className="text-xs text-[var(--color-text-muted)]" aria-label={`Activity time: ${timeAgo(activity.timestamp)}`}>
                      {timeAgo(activity.timestamp)}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {!loading && !error && activityList.length === 0 && (
          <div className="text-center py-8" role="status">
            <div className="text-4xl mb-4 opacity-50" aria-hidden="true">📌</div>
            <p className="text-sm text-[var(--color-text-muted)] mb-2">
              {t('dashboard.noActivity', { defaultValue: 'No recent activity' })}
            </p>
            <p className="text-xs text-[var(--color-text-muted)]">
              {t('dashboard.noActivityDescription', { defaultValue: 'Activities will appear here as they happen' })}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
});

ActivityFeed.displayName = 'ActivityFeed';

export default ActivityFeed;

