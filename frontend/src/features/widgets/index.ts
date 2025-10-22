import { widgetRegistry } from './registry';
import {
  KpiWidget,
  ChartWidget,
  TableWidget,
  ListWidget,
  ProgressWidget,
} from './components';

// Register all widget components
export const registerWidgets = () => {
  // KPI Widget
  widgetRegistry.register({
    type: 'kpi',
    component: KpiWidget,
    defaultConfig: {
      showTrend: true,
      showChange: true,
      format: 'number',
    },
    defaultSize: 'medium',
    title: 'KPI Card',
    description: 'Display key performance indicators with trends',
    category: 'metrics',
  });

  // Chart Widget
  widgetRegistry.register({
    type: 'chart',
    component: ChartWidget,
    defaultConfig: {
      chartType: 'line',
      showLegend: true,
      showGrid: true,
    },
    defaultSize: 'large',
    title: 'Chart',
    description: 'Visualize data with interactive charts',
    category: 'charts',
  });

  // Table Widget
  widgetRegistry.register({
    type: 'table',
    component: TableWidget,
    defaultConfig: {
      pageSize: 10,
      sortable: true,
      filterable: false,
    },
    defaultSize: 'large',
    title: 'Data Table',
    description: 'Display tabular data with sorting and filtering',
    category: 'tables',
  });

  // List Widget
  widgetRegistry.register({
    type: 'list',
    component: ListWidget,
    defaultConfig: {
      maxItems: 10,
      showBadges: true,
      clickable: true,
    },
    defaultSize: 'medium',
    title: 'Item List',
    description: 'Display a list of items with badges and actions',
    category: 'lists',
  });

  // Progress Widget
  widgetRegistry.register({
    type: 'progress',
    component: ProgressWidget,
    defaultConfig: {
      showPercentage: true,
      showETA: false,
      animated: true,
    },
    defaultSize: 'small',
    title: 'Progress Bar',
    description: 'Show progress towards a goal or completion status',
    category: 'metrics',
  });

  // Alert Widget (Simple stub for now)
  widgetRegistry.register({
    type: 'alert',
    component: ListWidget, // Reuse ListWidget for now
    defaultConfig: {
      maxItems: 5,
      showBadges: true,
      severity: 'all',
    },
    defaultSize: 'medium',
    title: 'Alerts',
    description: 'Display recent alerts and notifications',
    category: 'lists',
  });

  // Activity Widget (Simple stub for now)
  widgetRegistry.register({
    type: 'activity',
    component: ListWidget, // Reuse ListWidget for now
    defaultConfig: {
      maxItems: 8,
      showTimestamps: true,
      showAvatars: false,
    },
    defaultSize: 'medium',
    title: 'Recent Activity',
    description: 'Show recent user activities and events',
    category: 'lists',
  });

  // Calendar Widget (Simple stub for now)
  widgetRegistry.register({
    type: 'calendar',
    component: ChartWidget, // Reuse ChartWidget for now
    defaultConfig: {
      view: 'month',
      showEvents: true,
      showHolidays: false,
    },
    defaultSize: 'large',
    title: 'Calendar',
    description: 'Display calendar with events and milestones',
    category: 'charts',
  });
};

// Auto-register widgets when this module is imported
registerWidgets();

// Export registry for external use
export { widgetRegistry } from './registry';
export { WidgetRenderer } from './registry';
export { createWidget } from './registry';
export type { WidgetType, DashboardWidget, WidgetComponentProps } from './registry';
