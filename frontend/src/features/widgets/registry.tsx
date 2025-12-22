import React from 'react';
import type { WidgetType, DashboardWidget } from '../../entities/dashboard/types';

// Widget Component Props
export interface WidgetComponentProps {
  widget: DashboardWidget;
  data?: any;
  loading?: boolean;
  error?: string;
  onRefresh?: () => void;
  onConfigure?: () => void;
  onRemove?: () => void;
}

// Widget Registry Entry
export interface WidgetRegistryEntry {
  type: WidgetType;
  component: React.ComponentType<WidgetComponentProps>;
  defaultConfig: Record<string, any>;
  defaultSize: 'small' | 'medium' | 'large' | 'xlarge';
  title: string;
  description: string;
  category: 'metrics' | 'charts' | 'tables' | 'lists' | 'actions';
  permissions?: string[];
}

// Widget Registry
export class WidgetRegistry {
  private widgets = new Map<WidgetType, WidgetRegistryEntry>();

  register(entry: WidgetRegistryEntry): void {
    this.widgets.set(entry.type, entry);
  }

  get(type: WidgetType): WidgetRegistryEntry | undefined {
    return this.widgets.get(type);
  }

  getAll(): WidgetRegistryEntry[] {
    return Array.from(this.widgets.values());
  }

  getByCategory(category: WidgetRegistryEntry['category']): WidgetRegistryEntry[] {
    return this.getAll().filter(widget => widget.category === category);
  }

  has(type: WidgetType): boolean {
    return this.widgets.has(type);
  }

  unregister(type: WidgetType): void {
    this.widgets.delete(type);
  }

  clear(): void {
    this.widgets.clear();
  }
}

// Singleton instance
export const widgetRegistry = new WidgetRegistry();

// Widget Factory
export const createWidget = (type: WidgetType, overrides: Partial<DashboardWidget> = {}): Omit<DashboardWidget, 'id'> => {
  const entry = widgetRegistry.get(type);
  if (!entry) {
    throw new Error(`Widget type "${type}" is not registered`);
  }

  return {
    type,
    title: entry.title,
    description: entry.description,
    size: entry.defaultSize,
    position: { x: 0, y: 0, w: 1, h: 1 },
    config: { ...entry.defaultConfig },
    permissions: entry.permissions,
    ...overrides,
  };
};

// Widget Renderer Component
export interface WidgetRendererProps {
  widget: DashboardWidget;
  data?: any;
  loading?: boolean;
  error?: string;
  onRefresh?: () => void;
  onConfigure?: () => void;
  onRemove?: () => void;
}

export const WidgetRenderer: React.FC<WidgetRendererProps> = ({
  widget,
  data,
  loading,
  error,
  onRefresh,
  onConfigure,
  onRemove,
}) => {
  const entry = widgetRegistry.get(widget.type);
  
  if (!entry) {
    return (
      <div className="p-4 text-center text-[var(--color-text-muted)]">
        <p>Widget type "{widget.type}" not found</p>
      </div>
    );
  }

  const WidgetComponent = entry.component;

  return (
    <WidgetComponent
      widget={widget}
      data={data}
      loading={loading}
      error={error}
      onRefresh={onRefresh}
      onConfigure={onConfigure}
      onRemove={onRemove}
    />
  );
};

// Export types
export type { WidgetType, DashboardWidget };
