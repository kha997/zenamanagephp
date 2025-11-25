import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { ReportsMetrics, Trend } from '../../entities/app/reports/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface ReportsKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<ReportsMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllReports?: () => void;
  onViewRecentReports?: () => void;
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
    ? t('reports.vsLastWeek', { defaultValue: 'vs last week' })
    : t('reports.vsLastMonth', { defaultValue: 'vs last month' });

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
 * ReportsKpiStrip - KPI strip component for Reports page
 */
export const ReportsKpiStrip: React.FC<ReportsKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllReports,
  onViewRecentReports,
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
              {t('reports.metricsError', { defaultValue: 'Failed to load metrics' })}
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
      aria-label="Reports metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Reports */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllReports?.() || navigate('/app/reports');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllReports?.() || navigate('/app/reports');
          }
        }}
        aria-label="View all reports"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('reports.totalReports', { defaultValue: 'Total Reports' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_reports}
              </p>
              <TrendIndicator trend={data.trends?.total_reports} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Recent Reports */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewRecentReports?.() || navigate('/app/reports');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewRecentReports?.() || navigate('/app/reports');
          }
        }}
        aria-label="View recent reports"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('reports.recentReports', { defaultValue: 'Recent Reports' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.recent_reports}
              </p>
              <TrendIndicator trend={data.trends?.recent_reports} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.recent_reports} recent reports`}>
                {t('reports.thisPeriod', { defaultValue: 'This Period' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Downloads */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          navigate('/app/reports');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate('/app/reports');
          }
        }}
        aria-label="View downloads"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('reports.downloads', { defaultValue: 'Downloads' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.downloads}
              </p>
              <TrendIndicator trend={data.trends?.downloads} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" aria-label="Total downloads">
                {t('reports.last30Days', { defaultValue: 'Last 30 Days' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* By Type Summary */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          navigate('/app/reports');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate('/app/reports');
          }
        }}
        aria-label="View report types"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('reports.types', { defaultValue: 'Report Types' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {Object.keys(data.by_type || {}).length}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" aria-label="Report types">
                {t('reports.active', { defaultValue: 'Active' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

ReportsKpiStrip.displayName = 'ReportsKpiStrip';

export default ReportsKpiStrip;

