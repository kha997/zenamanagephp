import React, { memo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardContent } from '../../shared/ui/card';
import { Badge } from '../../shared/ui/badge';
import { MetricsSkeleton } from '../../shared/ui/skeleton';
import { useI18n } from '../../app/i18n-context';
import type { DocumentsMetrics, Trend } from '../../entities/app/documents/types';
import type { ApiResponse } from '../../entities/dashboard/types';

export interface DocumentsKpiStripProps {
  /** Metrics data from API */
  metrics?: ApiResponse<DocumentsMetrics> | null;
  /** Loading state */
  loading?: boolean;
  /** Error state */
  error?: Error | null;
  /** Optional refresh handler */
  onRefresh?: () => void;
  /** Optional action handlers */
  onViewAllDocuments?: () => void;
  onViewRecentUploads?: () => void;
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
    ? t('documents.vsLastWeek', { defaultValue: 'vs last week' })
    : t('documents.vsLastMonth', { defaultValue: 'vs last month' });

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
 * DocumentsKpiStrip - KPI strip component for Documents page
 */
export const DocumentsKpiStrip: React.FC<DocumentsKpiStripProps> = memo(({
  metrics,
  loading = false,
  error = null,
  onRefresh,
  onViewAllDocuments,
  onViewRecentUploads,
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
              {t('documents.metricsError', { defaultValue: 'Failed to load metrics' })}
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

  const formatStorage = (mb: number) => {
    if (mb < 1024) {
      return `${mb.toFixed(1)} MB`;
    }
    return `${(mb / 1024).toFixed(2)} GB`;
  };

  return (
    <div
      className={`grid grid-cols-2 gap-4 md:grid-cols-3 ${className || ''}`}
      role="region"
      aria-label="Documents metrics"
      style={{ contain: 'layout style' }}
    >
      {/* Total Documents */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewAllDocuments?.() || navigate('/app/documents');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewAllDocuments?.() || navigate('/app/documents');
          }
        }}
        aria-label="View all documents"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('documents.totalDocuments', { defaultValue: 'Total Documents' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.total_documents}
              </p>
              <TrendIndicator trend={data.trends?.total_documents} period={data.period} />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Recent Uploads */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          onViewRecentUploads?.() || navigate('/app/documents?sort_by=uploaded_at&sort_order=desc');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onViewRecentUploads?.() || navigate('/app/documents?sort_by=uploaded_at&sort_order=desc');
          }
        }}
        aria-label="View recent uploads"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('documents.recentUploads', { defaultValue: 'Recent Uploads' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {data.recent_uploads}
              </p>
              <TrendIndicator trend={data.trends?.recent_uploads} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="info" aria-label={`${data.recent_uploads} recent uploads`}>
                {t('documents.thisPeriod', { defaultValue: 'This period' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Storage Used */}
      <Card 
        className="hover:shadow-lg transition-all cursor-pointer hover:scale-[1.02] active:scale-[0.98]"
        onClick={() => {
          navigate('/app/documents');
        }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            navigate('/app/documents');
          }
        }}
        aria-label="View storage usage"
      >
        <CardContent className="p-4">
          <div className="space-y-2">
            <p className="text-sm text-[var(--color-text-muted)]">
              {t('documents.storageUsed', { defaultValue: 'Storage Used' })}
            </p>
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold text-[var(--color-text-primary)]">
                {formatStorage(data.storage_used)}
              </p>
              <TrendIndicator trend={data.trends?.storage_used} period={data.period} />
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral" aria-label="Storage usage">
                {t('documents.total', { defaultValue: 'Total' })}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
});

DocumentsKpiStrip.displayName = 'DocumentsKpiStrip';

export default DocumentsKpiStrip;

