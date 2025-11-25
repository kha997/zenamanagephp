import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { ClientsMetrics, Trend } from '../../entities/app/clients/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface ClientsKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<ClientsMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllClients?: () => void;
  onViewActiveClients?: () => void;
  onViewNewClients?: () => void;
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
    ? t('clients.vsLastWeek', { defaultValue: 'vs last week' })
    : t('clients.vsLastMonth', { defaultValue: 'vs last month' });

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
 * ClientsKpiStrip - KPI strip component for Clients page
 */
export const ClientsKpiStrip: React.FC<ClientsKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllClients,
  onViewActiveClients,
  onViewNewClients,
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
              {t('clients.metricsError', { defaultValue: 'Failed to load metrics' })}
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
      aria-label="Clients metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Clients */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllClients?.() || navigate('/app/clients');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllClients?.() || navigate('/app/clients');
          }
        }}
        aria-label="View all clients"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('clients.totalClients', { defaultValue: 'Total Clients' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_clients}
              </p>
              <TrendIndicator trend={data.trends?.total_clients} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Active Clients */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewActiveClients?.() || navigate('/app/clients?lifecycle_stage=customer');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewActiveClients?.() || navigate('/app/clients?lifecycle_stage=customer');
          }
        }}
        aria-label="View active clients"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('clients.activeClients', { defaultValue: 'Active Clients' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.active_clients}
              </p>
              <TrendIndicator trend={data.trends?.active_clients} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="success" aria-label={`${data.active_clients} active clients`}>
                {t('clients.customers', { defaultValue: 'Customers' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* New Clients */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewNewClients?.() || navigate('/app/clients');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewNewClients?.() || navigate('/app/clients');
          }
        }}
        aria-label="View new clients"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('clients.newClients', { defaultValue: 'New Clients' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.new_clients}
              </p>
              <TrendIndicator trend={data.trends?.new_clients} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.new_clients} new clients this period`}>
                {t('clients.thisPeriod', { defaultValue: 'This Period' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Revenue */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          navigate('/app/projects?status=completed');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate('/app/projects?status=completed');
          }
        }}
        aria-label="View revenue"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('clients.revenue', { defaultValue: 'Revenue' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {formatCurrency(data.revenue)}
              </p>
              <TrendIndicator trend={data.trends?.revenue} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" aria-label="Total revenue">
                {t('clients.total', { defaultValue: 'Total' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

ClientsKpiStrip.displayName = 'ClientsKpiStrip';

export default ClientsKpiStrip;

