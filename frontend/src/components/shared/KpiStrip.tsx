import React, { memo } from 'react';
import { Card } from '../ui/primitives/Card';
import { spacing } from '../../shared/tokens/spacing';
import { radius } from '../../shared/tokens/radius';
import { shadows } from '../../shared/tokens/shadows';

export interface KpiItem {
  /** KPI label */
  label: string;
  /** KPI value (number or string) */
  value: number | string;
  /** Optional change indicator (e.g., "+5%", "-10") */
  change?: string;
  /** Optional trend direction */
  trend?: 'up' | 'down' | 'neutral';
  /** Optional color variant */
  variant?: 'default' | 'success' | 'warning' | 'danger' | 'info';
  /** Optional click handler */
  onClick?: () => void;
  /** Optional action label */
  actionLabel?: string;
  /** Optional period label for trend comparison (e.g., "vs previous week") */
  periodLabel?: string;
}

export interface KpiStripProps {
  /** Array of KPI items to display */
  kpis: KpiItem[];
  /** Loading state */
  loading?: boolean;
  /** Optional className */
  className?: string;
  /** Optional columns configuration (default: responsive) */
  columns?: 1 | 2 | 3 | 4 | 5;
  /** Optional period selector (week/month) */
  period?: 'week' | 'month';
  /** Optional period change handler */
  onPeriodChange?: (period: 'week' | 'month') => void;
  /** Show period selector */
  showPeriodSelector?: boolean;
}

/**
 * KpiStrip - Reusable KPI strip component for all pages
 * 
 * Displays key performance indicators in a responsive grid layout.
 * Follows Apple-style design spec with tokens, spacing, and cards.
 * 
 * @example
 * ```tsx
 * <KpiStrip
 *   kpis={[
 *     { label: 'Total Projects', value: 42, change: '+5', trend: 'up' },
 *     { label: 'Active Tasks', value: 128, change: '-3', trend: 'down' },
 *   ]}
 *   loading={false}
 * />
 * ```
 */
export const KpiStrip: React.FC<KpiStripProps> = memo(({
  kpis,
  loading = false,
  className = '',
  columns,
  period = 'week',
  onPeriodChange,
  showPeriodSelector = false,
}) => {
  if (loading) {
    const gridColsClass = columns
      ? `grid-cols-${columns}`
      : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';
    return (
      <div className={`grid ${gridColsClass} gap-4 ${className}`}>
        {[1, 2, 3, 4].map((i) => (
          <Card key={i} style={{ padding: spacing.xl }}>
            <div className="animate-pulse">
              <div className="h-4 bg-[var(--muted-surface)] rounded mb-2 w-24"></div>
              <div className="h-8 bg-[var(--muted-surface)] rounded w-16"></div>
            </div>
          </Card>
        ))}
      </div>
    );
  }

  // Normalize kpis to always be an array
  const safeKpis = Array.isArray(kpis) ? kpis : [];
  
  if (safeKpis.length === 0) {
    return null;
  }

  // Determine grid columns based on props or responsive defaults
  const gridColsClass = columns
    ? columns === 1
      ? 'grid-cols-1'
      : columns === 2
      ? 'grid-cols-1 md:grid-cols-2'
      : columns === 3
      ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3'
      : columns === 4
      ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
      : 'grid-cols-1 md:grid-cols-3 lg:grid-cols-5'
    : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4';

  const getVariantColorClass = (variant?: string) => {
    switch (variant) {
      case 'success':
        return 'text-[var(--color-semantic-success-600)]';
      case 'warning':
        return 'text-[var(--color-semantic-warning-600)]';
      case 'danger':
        return 'text-[var(--color-semantic-danger-600)]';
      case 'info':
        return 'text-[var(--color-semantic-info-600)]'; // Use info color (blue) for In Progress
      default:
        return 'text-[var(--text)]';
    }
  };

  const getTrendIcon = (trend?: string) => {
    switch (trend) {
      case 'up':
        return (
          <svg className="w-3 h-3 inline-block" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clipRule="evenodd" />
          </svg>
        );
      case 'down':
        return (
          <svg className="w-3 h-3 inline-block" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fillRule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clipRule="evenodd" />
          </svg>
        );
      default:
        return null;
    }
  };

  return (
    <div className={`space-y-3 ${className}`} data-testid="kpi-strip">
      {/* Period Selector */}
      {showPeriodSelector && onPeriodChange && (
        <div className="flex items-center justify-end gap-2">
          <span className="text-xs text-[var(--muted)]">Compare:</span>
          <div className="inline-flex rounded-md shadow-sm" role="group">
            <button
              type="button"
              onClick={() => onPeriodChange('week')}
              className={`px-3 py-1.5 text-xs font-medium rounded-l-md border transition-colors ${
                period === 'week'
                  ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)] border-[var(--primary-button-bg)]'
                  : 'bg-[var(--surface)] text-[var(--text)] border-[var(--border)] hover:bg-[var(--muted-surface)]'
              }`}
              aria-pressed={period === 'week'}
              aria-label="Compare with previous week"
            >
              Week
            </button>
            <button
              type="button"
              onClick={() => onPeriodChange('month')}
              className={`px-3 py-1.5 text-xs font-medium rounded-r-md border border-l-0 transition-colors ${
                period === 'month'
                  ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)] border-[var(--primary-button-bg)]'
                  : 'bg-[var(--surface)] text-[var(--text)] border-[var(--border)] hover:bg-[var(--muted-surface)]'
              }`}
              aria-pressed={period === 'month'}
              aria-label="Compare with previous month"
            >
              Month
            </button>
          </div>
        </div>
      )}
      <div className={`grid ${gridColsClass} gap-4`}>
      {safeKpis.map((kpi, index) => {
        if (!kpi) return null;
        return (
        <Card
          key={index}
          style={{
            padding: spacing.xl,
            borderRadius: radius.lg,
            boxShadow: shadows.xs,
            cursor: kpi.onClick ? 'pointer' : 'default',
            transition: 'all 0.2s ease-in-out',
          }}
          onClick={kpi.onClick}
          data-testid={`kpi-item-${index}`}
          className={kpi.onClick ? 'hover:shadow-md hover:scale-[1.02] active:scale-[0.98]' : ''}
          role={kpi.onClick ? 'button' : undefined}
          tabIndex={kpi.onClick ? 0 : undefined}
          onKeyDown={kpi.onClick ? (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              kpi.onClick();
            }
          } : undefined}
        >
          <div className="flex flex-col gap-2">
            {/* Label */}
            <div className="flex items-center justify-between">
              <span
                className="text-sm font-medium"
                style={{ color: 'var(--muted)' }}
              >
                {kpi.label}
              </span>
            </div>

            {/* Value */}
            <div className="flex items-baseline gap-2 flex-wrap">
              <span
                className={`text-2xl font-bold ${kpi.variant ? getVariantColorClass(kpi.variant) : 'text-[var(--text)]'}`}
                aria-label={`${kpi.label}: ${typeof kpi.value === 'number' ? kpi.value.toLocaleString() : kpi.value}`}
              >
                {typeof kpi.value === 'number' ? kpi.value.toLocaleString() : kpi.value}
              </span>
              {kpi.change && (
                <span
                  className="text-sm font-medium flex items-center gap-1"
                  style={{
                    color:
                      kpi.trend === 'up'
                        ? 'var(--color-semantic-success-600)'
                        : kpi.trend === 'down'
                        ? 'var(--color-semantic-danger-600)'
                        : 'var(--muted)',
                  }}
                  title={kpi.periodLabel || `Compared to previous ${period}`}
                  aria-label={`Trend: ${kpi.trend === 'up' ? 'increased' : kpi.trend === 'down' ? 'decreased' : 'unchanged'} by ${kpi.change}${kpi.periodLabel ? ` (${kpi.periodLabel})` : ''}`}
                >
                  {getTrendIcon(kpi.trend)}
                  <span>{kpi.change}</span>
                </span>
              )}
            </div>

            {/* Action */}
            {kpi.actionLabel && (
              <div className="flex items-center gap-2 mt-1">
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    kpi.onClick?.();
                  }}
                  className="text-xs underline"
                  style={{ color: 'var(--color-semantic-primary-600)' }}
                  aria-label={kpi.actionLabel}
                >
                  {kpi.actionLabel}
                </button>
              </div>
            )}
          </div>
        </Card>
        );
      })}
      </div>
    </div>
  );
});

KpiStrip.displayName = 'KpiStrip';

