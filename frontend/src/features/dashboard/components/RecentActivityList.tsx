import React, { memo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Badge } from '../../../shared/ui/badge';
import type { ActivityItem } from '../types';

export interface RecentActivityListProps {
  /** Activity items from API */
  activities?: ActivityItem[];
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional title */
  title?: string;
  /** Optional className */
  className?: string;
}

/**
 * RecentActivityList - Component to display recent activity feed
 */
export const RecentActivityList: React.FC<RecentActivityListProps> = memo(({
  activities = [],
  loading = false,
  error = null,
  title = 'Recent Activity',
  className,
}) => {
  if (loading) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-4 bg-[var(--color-surface-subtle)] rounded w-3/4 mb-2"></div>
                <div className="h-3 bg-[var(--color-surface-subtle)] rounded w-1/2"></div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-[var(--color-text-muted)] py-4">
            <p className="text-sm">Failed to load activity</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (!activities || activities.length === 0) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-center text-[var(--color-text-muted)] py-4">
            <p className="text-sm">No recent activity</p>
          </div>
        </CardContent>
      </Card>
    );
  }

  const getTypeColor = (type: ActivityItem['type']) => {
    switch (type) {
      case 'project':
        return 'bg-[var(--color-semantic-primary-100)] text-[var(--color-semantic-primary-700)]';
      case 'task':
        return 'bg-[var(--color-semantic-warning-100)] text-[var(--color-semantic-warning-700)]';
      case 'user':
        return 'bg-[var(--color-semantic-info-100)] text-[var(--color-semantic-info-700)]';
      case 'system':
        return 'bg-[var(--color-surface-subtle)] text-[var(--color-text-secondary)]';
      default:
        return 'bg-[var(--color-surface-subtle)] text-[var(--color-text-secondary)]';
    }
  };

  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
      return 'Just now';
    } else if (diffMins < 60) {
      return `${diffMins}m ago`;
    } else if (diffHours < 24) {
      return `${diffHours}h ago`;
    } else if (diffDays < 7) {
      return `${diffDays}d ago`;
    } else {
      return date.toLocaleDateString();
    }
  };

  return (
    <Card className={className} data-testid="recent-activity">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-3" data-testid="activity-list">
          {activities.map((activity) => (
            <div
              key={activity.id}
              className="flex items-start gap-3 p-3 rounded-lg hover:bg-[var(--color-surface-subtle)] transition-colors"
              data-testid={`activity-item-${activity.id}`}
            >
              <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${getTypeColor(activity.type)}`}>
                <span className="text-xs font-semibold">
                  {activity.type.charAt(0).toUpperCase()}
                </span>
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <Badge tone="neutral" className="text-xs">
                    {activity.type}
                  </Badge>
                  <span className="text-xs text-[var(--color-text-muted)]">
                    {formatTimestamp(activity.timestamp)}
                  </span>
                </div>
                <p className="text-sm text-[var(--color-text-primary)]">
                  {activity.description}
                </p>
                {activity.user && (
                  <p className="text-xs text-[var(--color-text-muted)] mt-1">
                    by {activity.user.name}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
});

RecentActivityList.displayName = 'RecentActivityList';

