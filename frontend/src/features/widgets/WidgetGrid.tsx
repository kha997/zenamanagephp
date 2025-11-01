import React, { useCallback } from 'react';
import { Card, CardContent } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { WidgetRenderer, createWidget, widgetRegistry } from './index';
import { useWidgetData, useAddWidget, useRemoveWidget } from '../../entities/dashboard/hooks';
import { useQueryClient } from '@tanstack/react-query';
import { useI18n } from '../../app/i18n-context';
import type { DashboardWidget, WidgetType } from '../../entities/dashboard/types';

interface WidgetGridProps {
  widgets: DashboardWidget[];
  editable?: boolean;
  className?: string;
}

export const WidgetGrid: React.FC<WidgetGridProps> = ({
  widgets,
  editable = false,
  className,
}) => {
  const { t } = useI18n();
  const queryClient = useQueryClient();
  const addWidgetMutation = useAddWidget();
  const removeWidgetMutation = useRemoveWidget();

  const handleAddWidget = useCallback(async (type: WidgetType) => {
    try {
      const newWidgetData = createWidget(type, {
        position: { x: 0, y: 0, w: 1, h: 1 },
      });
      await addWidgetMutation.mutateAsync(newWidgetData);
    } catch (error) {
      console.error('Failed to add widget:', error);
    }
  }, [addWidgetMutation]);

  const handleRemoveWidget = useCallback(async (widgetId: string) => {
    try {
      await removeWidgetMutation.mutateAsync(widgetId);
    } catch (error) {
      console.error('Failed to remove widget:', error);
    }
  }, [removeWidgetMutation]);

  const handleRefreshWidget = useCallback((widgetId: string) => {
    // Invalidate specific widget data to trigger refetch
    queryClient.invalidateQueries({ 
      queryKey: ['dashboard', 'widgets', widgetId] 
    });
  }, [queryClient]);

  const handleConfigureWidget = useCallback((widgetId: string) => {
    // This would open a configuration modal
    // For now, we'll just log it
    console.log('Configuring widget:', widgetId);
  }, []);

  const getGridCols = (size: DashboardWidget['size']) => {
    switch (size) {
      case 'small':
        return 'md:col-span-1';
      case 'medium':
        return 'md:col-span-2';
      case 'large':
        return 'md:col-span-3';
      case 'xlarge':
        return 'md:col-span-4';
      default:
        return 'md:col-span-2';
    }
  };

  const getGridRows = (size: DashboardWidget['size']) => {
    switch (size) {
      case 'small':
        return 'md:row-span-1';
      case 'medium':
        return 'md:row-span-2';
      case 'large':
        return 'md:row-span-3';
      case 'xlarge':
        return 'md:row-span-4';
      default:
        return 'md:row-span-2';
    }
  };

  if (widgets.length === 0) {
    return (
      <div className={`space-y-4 ${className || ''}`}>
        <Card>
          <CardContent className="p-8">
            <div className="text-center">
              <div className="text-4xl mb-4">📊</div>
              <h3 className="text-lg font-semibold text-[var(--color-text-primary)] mb-2">
                {t('dashboard.noWidgets', { defaultValue: 'No widgets yet' })}
              </h3>
              <p className="text-[var(--color-text-muted)] mb-4">
                {t('dashboard.noWidgetsDescription', { defaultValue: 'Add widgets to customize your dashboard' })}
              </p>
              {editable && (
                <div className="flex flex-wrap gap-2 justify-center">
                  {widgetRegistry.getAll().map((entry) => (
                    <Button
                      key={entry.type}
                      variant="outline"
                      size="sm"
                      onClick={() => handleAddWidget(entry.type)}
                    >
                      {entry.title}
                    </Button>
                  ))}
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className={`space-y-4 ${className || ''}`}>
      {editable && (
        <div className="flex flex-wrap gap-2">
          <span className="text-sm text-[var(--color-text-muted)] mr-2">
            {t('dashboard.addWidget', { defaultValue: 'Add widget:' })}
          </span>
          {widgetRegistry.getAll().map((entry) => (
            <Button
              key={entry.type}
              variant="outline"
              size="sm"
              onClick={() => handleAddWidget(entry.type)}
              disabled={addWidgetMutation.isPending}
            >
              {entry.title}
            </Button>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 auto-rows-min">
        {widgets.map((widget) => (
          <WidgetItem
            key={widget.id}
            widget={widget}
            className={`${getGridCols(widget.size)} ${getGridRows(widget.size)}`}
            onRefresh={() => handleRefreshWidget(widget.id)}
            onConfigure={() => handleConfigureWidget(widget.id)}
            onRemove={editable ? () => handleRemoveWidget(widget.id) : undefined}
            editable={editable}
          />
        ))}
      </div>
    </div>
  );
};

interface WidgetItemProps {
  widget: DashboardWidget;
  className?: string;
  onRefresh?: () => void;
  onConfigure?: () => void;
  onRemove?: () => void;
  editable?: boolean;
}

const WidgetItem: React.FC<WidgetItemProps> = ({
  widget,
  className,
  onRefresh,
  onConfigure,
  onRemove,
  editable = false,
}) => {
  const { data, isLoading, error } = useWidgetData(widget.id, true);

  return (
    <div className={`${className || ''} ${editable ? 'group' : ''}`}>
      <WidgetRenderer
        widget={widget}
        data={data?.data}
        loading={isLoading}
        error={error?.message}
        onRefresh={onRefresh}
        onConfigure={onConfigure}
        onRemove={onRemove}
      />
    </div>
  );
};
