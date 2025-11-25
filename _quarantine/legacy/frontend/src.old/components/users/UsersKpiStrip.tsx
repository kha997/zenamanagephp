import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { UsersMetrics, Trend } from '../../entities/app/users/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface UsersKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<UsersMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllUsers?: () => void;
  onViewActiveUsers?: () => void;
  onViewNewUsers?: () => void;
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
    ? t('users.vsLastWeek', { defaultValue: 'vs last week' })
    : t('users.vsLastMonth', { defaultValue: 'vs last month' });

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
 * UsersKpiStrip - KPI strip component for Users page
 */
export const UsersKpiStrip: React.FC<UsersKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllUsers,
  onViewActiveUsers,
  onViewNewUsers,
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
              {t('users.metricsError', { defaultValue: 'Failed to load metrics' })}
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
      className={`grid grid-cols-2 gap-4 md:grid-cols-3 ${className || ''}`}
      role="region"
      aria-label="Users metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Users */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllUsers?.() || navigate('/app/users');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllUsers?.() || navigate('/app/users');
          }
        }}
        aria-label="View all users"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('users.totalUsers', { defaultValue: 'Total Users' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_users}
              </p>
              <TrendIndicator trend={data.trends?.total_users} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Active Users */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewActiveUsers?.() || navigate('/app/users?is_active=1');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewActiveUsers?.() || navigate('/app/users?is_active=1');
          }
        }}
        aria-label="View active users"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('users.activeUsers', { defaultValue: 'Active Users' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.active_users}
              </p>
              <TrendIndicator trend={data.trends?.active_users} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.active_users} active users`}>
                {t('users.active', { defaultValue: 'Active' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* New Users */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewNewUsers?.() || navigate('/app/users');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewNewUsers?.() || navigate('/app/users');
          }
        }}
        aria-label="View new users"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('users.newUsers', { defaultValue: 'New Users' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.new_users}
              </p>
              <TrendIndicator trend={data.trends?.new_users} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.new_users} new users this period`}>
                {t('users.thisPeriod', { defaultValue: 'This Period' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

UsersKpiStrip.displayName = 'UsersKpiStrip';

export default UsersKpiStrip;

