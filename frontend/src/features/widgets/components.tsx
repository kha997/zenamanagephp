import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { Badge } from '../../shared/ui/badge';
import { Skeleton } from '../../shared/ui/skeleton';
import type { WidgetComponentProps } from './registry';

// Base Widget Wrapper
export const BaseWidget: React.FC<{
  title: string;
  description?: string;
  loading?: boolean;
  error?: string;
  onRefresh?: () => void;
  onConfigure?: () => void;
  onRemove?: () => void;
  children: React.ReactNode;
  className?: string;
}> = ({
  title,
  description,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
  children,
  className,
}) => {
  return (
    <Card className={`h-full ${className || ''}`}>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div className="flex-1 min-w-0">
            <CardTitle className="text-sm font-medium truncate">{title}</CardTitle>
            {description && (
              <CardDescription className="text-xs truncate">{description}</CardDescription>
            )}
          </div>
          <div className="flex items-center gap-1 ml-2">
            {onRefresh && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onRefresh}
                disabled={loading}
                className="h-6 w-6 p-0"
                aria-label="Refresh widget"
              >
                {loading ? (
                  <div className="h-3 w-3 animate-spin rounded-full border border-[var(--color-border-default)] border-r-transparent" />
                ) : (
                  <span className="text-xs">↻</span>
                )}
              </Button>
            )}
            {onConfigure && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onConfigure}
                className="h-6 w-6 p-0"
                aria-label="Configure widget"
              >
                <span className="text-xs">⚙</span>
              </Button>
            )}
            {onRemove && (
              <Button
                variant="ghost"
                size="sm"
                onClick={onRemove}
                className="h-6 w-6 p-0 text-[var(--color-text-muted)] hover:text-[var(--color-semantic-danger-500)]"
                aria-label="Remove widget"
              >
                <span className="text-xs">×</span>
              </Button>
            )}
          </div>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {loading ? (
          <div className="space-y-3">
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-8 w-1/2" />
            <Skeleton className="h-3 w-full" />
          </div>
        ) : error ? (
          <div className="text-center py-4">
            <p className="text-sm text-[var(--color-text-muted)] mb-2">{error}</p>
            {onRefresh && (
              <Button variant="outline" size="sm" onClick={onRefresh}>
                Retry
              </Button>
            )}
          </div>
        ) : (
          children
        )}
      </CardContent>
    </Card>
  );
};

// KPI Widget Component
export const KpiWidget: React.FC<WidgetComponentProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  const value = data?.value || 0;
  const change = data?.change || 0;
  const changeType = data?.changeType || 'neutral';
  const unit = data?.unit || '';
  const trend = data?.trend || 'stable';

  const getChangeColor = () => {
    switch (changeType) {
      case 'positive':
        return 'success';
      case 'negative':
        return 'danger';
      default:
        return 'neutral';
    }
  };

  const getTrendIcon = () => {
    switch (trend) {
      case 'up':
        return '↗';
      case 'down':
        return '↘';
      default:
        return '→';
    }
  };

  return (
    <BaseWidget
      title={widget.title}
      description={widget.description}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    >
      <div className="space-y-3">
        <div className="flex items-baseline gap-2">
          <span className="text-2xl font-bold text-[var(--color-text-primary)]">
            {typeof value === 'number' ? value.toLocaleString() : value}
          </span>
          {unit && (
            <span className="text-sm text-[var(--color-text-muted)]">{unit}</span>
          )}
        </div>
        
        {change !== 0 && (
          <div className="flex items-center gap-2">
            <Badge tone={getChangeColor()}>
              {change > 0 ? '+' : ''}{change}%
            </Badge>
            <span className="text-xs text-[var(--color-text-muted)]">
              {getTrendIcon()} vs last period
            </span>
          </div>
        )}
        
        {data?.description && (
          <p className="text-xs text-[var(--color-text-muted)]">{data.description}</p>
        )}
      </div>
    </BaseWidget>
  );
};

// Chart Widget Component (Stub)
export const ChartWidget: React.FC<WidgetComponentProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  return (
    <BaseWidget
      title={widget.title}
      description={widget.description}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    >
      <div className="h-32 flex items-center justify-center">
        <div className="text-center">
          <div className="text-2xl mb-2">📊</div>
          <p className="text-sm text-[var(--color-text-muted)]">Chart Widget</p>
          <p className="text-xs text-[var(--color-text-muted)] mt-1">
            {data ? `${data.length || 0} data points` : 'No data'}
          </p>
        </div>
      </div>
    </BaseWidget>
  );
};

// Table Widget Component (Stub)
export const TableWidget: React.FC<WidgetComponentProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  const rows = data?.rows || [];
  const columns = data?.columns || [];

  return (
    <BaseWidget
      title={widget.title}
      description={widget.description}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    >
      <div className="space-y-2">
        {rows.length > 0 ? (
          <>
            <div className="text-xs text-[var(--color-text-muted)]">
              {rows.length} rows, {columns.length} columns
            </div>
            <div className="max-h-32 overflow-y-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b border-[var(--color-border-subtle)]">
                    {columns.slice(0, 3).map((col: string, index: number) => (
                      <th key={index} className="text-left py-1 pr-2 font-medium">
                        {col}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {rows.slice(0, 3).map((row: any[], index: number) => (
                    <tr key={index} className="border-b border-[var(--color-border-subtle)]">
                      {row.slice(0, 3).map((cell: any, cellIndex: number) => (
                        <td key={cellIndex} className="py-1 pr-2 truncate">
                          {String(cell)}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        ) : (
          <div className="text-center py-4">
            <div className="text-2xl mb-2">📋</div>
            <p className="text-sm text-[var(--color-text-muted)]">No data to display</p>
          </div>
        )}
      </div>
    </BaseWidget>
  );
};

// List Widget Component (Stub)
export const ListWidget: React.FC<WidgetComponentProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  const items = data?.items || [];

  return (
    <BaseWidget
      title={widget.title}
      description={widget.description}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    >
      <div className="space-y-2">
        {items.length > 0 ? (
          <div className="max-h-32 overflow-y-auto space-y-1">
            {items.slice(0, 5).map((item: any, index: number) => (
              <div
                key={index}
                className="flex items-center gap-2 p-2 rounded-[var(--radius-sm)] bg-[var(--color-surface-muted)]"
              >
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-medium truncate">{item.title || item.name}</p>
                  {item.description && (
                    <p className="text-xs text-[var(--color-text-muted)] truncate">
                      {item.description}
                    </p>
                  )}
                </div>
                {item.badge && (
                  <Badge tone={item.badge.tone || 'neutral'} className="text-xs">
                    {item.badge.text}
                  </Badge>
                )}
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-4">
            <div className="text-2xl mb-2">📝</div>
            <p className="text-sm text-[var(--color-text-muted)]">No items to display</p>
          </div>
        )}
      </div>
    </BaseWidget>
  );
};

// Progress Widget Component
export const ProgressWidget: React.FC<WidgetComponentProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  const progress = data?.progress || 0;
  const total = data?.total || 100;
  const label = data?.label || 'Progress';
  const percentage = Math.round((progress / total) * 100);

  return (
    <BaseWidget
      title={widget.title}
      description={widget.description}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    >
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium">{label}</span>
          <span className="text-sm text-[var(--color-text-muted)]">{percentage}%</span>
        </div>
        
        <div className="w-full bg-[var(--color-surface-muted)] rounded-full h-2">
          <div
            className="bg-[var(--color-semantic-primary-500)] h-2 rounded-full transition-all duration-300"
            style={{ width: `${percentage}%` }}
          />
        </div>
        
        <div className="flex items-center justify-between text-xs text-[var(--color-text-muted)]">
          <span>{progress} of {total}</span>
          {data?.eta && <span>ETA: {data.eta}</span>}
        </div>
      </div>
    </BaseWidget>
  );
};
