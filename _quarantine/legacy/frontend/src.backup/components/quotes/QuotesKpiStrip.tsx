import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { QuotesMetrics, Trend } from '../../entities/app/quotes/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface QuotesKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<QuotesMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllQuotes?: () => void;
  onViewPendingQuotes?: () => void;
  onViewAcceptedQuotes?: () => void;
  onViewRejectedQuotes?: () => void;
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
    ? t('quotes.vsLastWeek', { defaultValue: 'vs last week' })
    : t('quotes.vsLastMonth', { defaultValue: 'vs last month' });

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
 * QuotesKpiStrip - KPI strip component for Quotes page
 */
export const QuotesKpiStrip: React.FC<QuotesKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllQuotes,
  onViewPendingQuotes,
  onViewAcceptedQuotes,
  onViewRejectedQuotes,
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
              {t('quotes.metricsError', { defaultValue: 'Failed to load metrics' })}
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

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  };

  return (
    <div
      className={`grid grid-cols-2 gap-4 md:grid-cols-4 ${className || ''}`}
      role="region"
      aria-label="Quotes metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Quotes */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllQuotes?.() || navigate('/app/quotes');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllQuotes?.() || navigate('/app/quotes');
          }
        }}
        aria-label="View all quotes"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('quotes.totalQuotes', { defaultValue: 'Total Quotes' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_quotes}
              </p>
              <TrendIndicator trend={data.trends?.total_quotes} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pending Quotes */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewPendingQuotes?.() || navigate('/app/quotes?status=sent');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewPendingQuotes?.() || navigate('/app/quotes?status=sent');
          }
        }}
        aria-label="View pending quotes"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('quotes.pendingQuotes', { defaultValue: 'Pending Quotes' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.pending_quotes}
              </p>
              <TrendIndicator trend={data.trends?.pending_quotes} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="warning" aria-label={`${data.pending_quotes} pending quotes`}>
                {t('quotes.pending', { defaultValue: 'Pending' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Accepted Quotes */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAcceptedQuotes?.() || navigate('/app/quotes?status=accepted');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAcceptedQuotes?.() || navigate('/app/quotes?status=accepted');
          }
        }}
        aria-label="View accepted quotes"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('quotes.acceptedQuotes', { defaultValue: 'Accepted Quotes' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.accepted_quotes}
              </p>
              <TrendIndicator trend={data.trends?.accepted_quotes} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.accepted_quotes} accepted quotes`}>
                {t('quotes.accepted', { defaultValue: 'Accepted' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Total Value */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          navigate('/app/quotes?status=accepted');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate('/app/quotes?status=accepted');
          }
        }}
        aria-label="View total value"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('quotes.totalValue', { defaultValue: 'Total Value' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {formatCurrency(data.total_value)}
              </p>
              <TrendIndicator trend={data.trends?.total_value} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label="Accepted quotes value">
                {t('quotes.accepted', { defaultValue: 'Accepted' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

QuotesKpiStrip.displayName = 'QuotesKpiStrip';

export default QuotesKpiStrip;

