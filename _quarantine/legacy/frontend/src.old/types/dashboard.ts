// Dashboard Types for Customization System

export interface DashboardWidget {
  id: string;
  name: string;
  type: string;
  category: string;
  description: string;
  icon: string;
  default_size: string;
  is_customizable: boolean;
  permissions: string[];
}

export interface WidgetInstance {
  id: string;
  widget_id: string;
  type: string;
  title: string;
  size: 'small' | 'medium' | 'large' | 'extra-large';
  position: {
    x: number;
    y: number;
  };
  config: Record<string, any>;
  is_customizable: boolean;
  created_at: string;
  updated_at?: string;
}

export interface LayoutTemplate {
  id: string;
  name: string;
  description: string;
  role: string;
  widgets: string[];
  recommended?: boolean;
}

export interface CustomizationOptions {
  widget_sizes: string[];
  layout_grid: {
    columns: number;
    row_height: number;
    margin: number[];
  };
  themes: Record<string, string>;
  refresh_intervals: Record<number, string>;
  permissions: CustomizationPermissions;
  default_preferences?: Record<string, any>;
}

export interface CustomizationPermissions {
  can_add_widgets: boolean;
  can_remove_widgets: boolean;
  can_resize_widgets: boolean;
  can_move_widgets: boolean;
  can_configure_widgets: boolean;
  can_apply_templates: boolean;
  can_reset_dashboard: boolean;
}

export interface DashboardLayout {
  id: string;
  user_id: string;
  tenant_id: string;
  name: string;
  layout: WidgetInstance[];
  is_default: boolean;
  preferences: Record<string, any>;
  created_at: string;
  updated_at: string;
}

export interface WidgetCategory {
  id: string;
  name: string;
  description: string;
  icon: string;
  color: string;
}

export interface DashboardPreferences {
  theme: 'light' | 'dark' | 'auto';
  refresh_interval: number;
  compact_mode: boolean;
  show_widget_borders: boolean;
  enable_animations: boolean;
  auto_refresh: boolean;
  grid_density: 'compact' | 'medium' | 'comfortable';
  sidebar_collapsed: boolean;
  notifications_enabled: boolean;
  sound_enabled: boolean;
  notification_position: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'center';
  notification_duration: number;
  cache_duration: number;
  max_concurrent_requests: number;
  debug_mode: boolean;
  performance_monitoring: boolean;
}

export interface DashboardExport {
  version: string;
  exported_at: string;
  user_role: string;
  dashboard: {
    name: string;
    layout: WidgetInstance[];
    preferences: Record<string, any>;
  };
  widgets: DashboardWidget[];
}

export interface DashboardAlert {
  id: string;
  user_id: string;
  tenant_id: string;
  widget_id?: string;
  metric_id?: string;
  message: string;
  type: 'info' | 'warning' | 'error' | 'success';
  severity: 'low' | 'medium' | 'high' | 'critical';
  is_read: boolean;
  triggered_at: string;
  resolved_at?: string;
  context: Record<string, any>;
}

export interface DashboardMetric {
  id: string;
  name: string;
  code: string;
  description: string;
  unit: string;
  type: 'counter' | 'gauge' | 'histogram' | 'summary';
  is_active: boolean;
  permissions: string[];
}

export interface DashboardMetricValue {
  id: string;
  metric_id: string;
  tenant_id: string;
  project_id?: string;
  value: number;
  timestamp: string;
  context: Record<string, any>;
}

export interface RealTimeEvent {
  type: 'dashboard_update' | 'widget_update' | 'new_alert' | 'metric_update' | 'project_update' | 'system_notification';
  data: any;
  timestamp: string;
  user_id?: string;
  tenant_id?: string;
  project_id?: string;
}

export interface ConnectionStats {
  isConnected: boolean;
  connectionType: 'websocket' | 'sse' | 'none';
  messagesReceived: number;
  messagesSent: number;
  connectionUptime: number;
  lastEvent?: RealTimeEvent;
  reconnectAttempts: number;
  lastReconnectAt?: string;
}
