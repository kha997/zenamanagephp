import React, { memo } from 'react';
import { Card } from '../ui/primitives/Card';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';
import { shadows } from '../../shared/tokens/shadows';

export interface Activity {
  /** Activity ID */
  id: string | number;
  /** Activity type */
  type?: 'project' | 'task' | 'user' | 'system' | 'document' | 'client' | 'quote';
  /** Activity action */
  action?: string;
  /** Activity description */
  description: string;
  /** Timestamp */
  timestamp: string | Date;
  /** User who performed the activity */
  user?: {
    id: string | number;
    name: string;
    avatar?: string;
  };
  /** Optional metadata */
  metadata?: Record<string, any>;
}

export interface ActivityFeedProps {
  /** Array of activities to display */
  activities?: Activity[];
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Maximum number of activities to display (default: 10) */
  limit?: number;
  /** Optional title */
  title?: string;
  /** Optional className */
  className?: string;
  /** Optional click handler for activities */
  onActivityClick?: (activity: Activity) => void;
}

/**
 * ActivityFeed - Reusable activity feed component for all pages
 * 
 * Displays a timeline-style activity feed with user avatars, timestamps, and descriptions.
 * Follows Apple-style design spec with tokens, spacing, and cards.
 * 
 * @example
 * ```tsx
 * <ActivityFeed
 *   activities={[
 *     { id: 1, description: 'Project created', timestamp: new Date(), user: { id: 1, name: 'John' } },
 *     { id: 2, description: 'Task completed', timestamp: new Date(), user: { id: 2, name: 'Jane' } },
 *   ]}
 *   limit={10}
 *   title="Recent Activity"
 * />
 * ```
 */
export const ActivityFeed: React.FC<ActivityFeedProps> = memo(({
  activities = [],
  loading = false,
  error = null,
  limit = 10,
  title,
  className = '',
  onActivityClick,
}) => {
  if (loading) {
    return (
      <Card
        style={{
          padding: spacing.xl,
          borderRadius: radius.lg,
          boxShadow: shadows.xs,
        }}
        className={className}
        data-testid="activity-feed"
      >
        {title && (
          <h3 className="text-lg font-semibold mb-4" style={{ color: 'var(--text)' }}>
            {title}
          </h3>
        )}
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="animate-pulse">
              <div className="h-4 bg-[var(--muted-surface)] rounded mb-2 w-3/4"></div>
              <div className="h-3 bg-[var(--muted-surface)] rounded w-1/2"></div>
            </div>
          ))}
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
          padding: spacing.xl,
          borderRadius: radius.lg,
          boxShadow: shadows.xs,
        }}
        className={className}
        data-testid="activity-feed"
      >
        {title && (
          <h3 className="text-lg font-semibold mb-4" style={{ color: 'var(--text)' }}>
            {title}
          </h3>
        )}
        <div className="text-center" style={{ color: 'var(--muted)' }}>
          <p className="text-sm">Failed to load activity</p>
        </div>
      </Card>
    );
  }

  const displayedActivities = activities.slice(0, limit);

  if (displayedActivities.length === 0) {
    return (
      <Card
        style={{
          padding: spacing.xl,
          borderRadius: radius.lg,
          boxShadow: shadows.xs,
        }}
        className={className}
        data-testid="activity-feed"
      >
        {title && (
          <h3 className="text-lg font-semibold mb-4" style={{ color: 'var(--text)' }}>
            {title}
          </h3>
        )}
        <div className="text-center" style={{ color: 'var(--muted)' }}>
          <p className="text-sm">No recent activity</p>
        </div>
      </Card>
    );
  }

  const getTypeColor = (type?: string) => {
    switch (type) {
      case 'project':
        return {
          bg: 'var(--color-semantic-primary-100)',
          text: 'var(--color-semantic-primary-700)',
        };
      case 'task':
        return {
          bg: 'var(--color-semantic-warning-100)',
          text: 'var(--color-semantic-warning-700)',
        };
      case 'user':
        return {
          bg: 'var(--color-semantic-info-100)',
          text: 'var(--color-semantic-info-700)',
        };
      case 'document':
        return {
          bg: 'var(--color-semantic-success-100)',
          text: 'var(--color-semantic-success-700)',
        };
      case 'system':
        return {
          bg: 'var(--muted-surface)',
          text: 'var(--muted)',
        };
      default:
        return {
          bg: 'var(--muted-surface)',
          text: 'var(--muted)',
        };
    }
  };

  const formatTimestamp = (timestamp: string | Date) => {
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
    <Card
      style={{
        padding: spacing.xl,
        borderRadius: radius.lg,
        boxShadow: shadows.xs,
      }}
      className={className}
      data-testid="activity-feed"
    >
      {title && (
        <h3 className="text-lg font-semibold mb-4" style={{ color: 'var(--text)' }}>
          {title}
        </h3>
      )}
      <div className="space-y-3" data-testid="activity-list">
        {displayedActivities.map((activity) => {
          const colors = getTypeColor(activity.type);
          return (
            <div
              key={activity.id}
              onClick={() => onActivityClick?.(activity)}
              style={{
                padding: spacing.sm,
                borderRadius: radius.md,
                cursor: onActivityClick ? 'pointer' : 'default',
              }}
              className="flex items-start gap-3 hover:bg-[var(--muted-surface)] transition-colors"
              data-testid={`activity-item-${activity.id}`}
            >
              {/* Avatar/Icon */}
              <div
                style={{
                  flexShrink: 0,
                  width: 32,
                  height: 32,
                  borderRadius: '50%',
                  backgroundColor: colors.bg,
                  color: colors.text,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '12px',
                  fontWeight: 600,
                }}
              >
                {activity.user?.avatar ? (
                  <img
                    src={activity.user.avatar}
                    alt={activity.user.name}
                    style={{ width: '100%', height: '100%', borderRadius: '50%' }}
                  />
                ) : (
                  <span>
                    {activity.type
                      ? activity.type.charAt(0).toUpperCase()
                      : activity.user?.name.charAt(0).toUpperCase() || 'A'}
                  </span>
                )}
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  {activity.type && (
                    <span
                      className="text-xs font-medium uppercase"
                      style={{ color: colors.text }}
                    >
                      {activity.type}
                    </span>
                  )}
                  <span className="text-xs" style={{ color: 'var(--muted)' }}>
                    {formatTimestamp(activity.timestamp)}
                  </span>
                </div>
                <p className="text-sm" style={{ color: 'var(--text)' }}>
                  {activity.description}
                </p>
                {activity.user && (
                  <p className="text-xs mt-1" style={{ color: 'var(--muted)' }}>
                    by {activity.user.name}
                  </p>
                )}
              </div>
            </div>
          );
        })}
      </div>
      {activities.length > limit && (
        <div className="mt-4 text-center">
          <p className="text-xs" style={{ color: 'var(--muted)' }}>
            +{activities.length - limit} more activities
          </p>
        </div>
      )}
    </Card>
  );
});

ActivityFeed.displayName = 'ActivityFeed';

