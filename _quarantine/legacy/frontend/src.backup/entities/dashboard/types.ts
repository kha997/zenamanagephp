// Dashboard Widget Types
export type WidgetType = 'kpi' | 'chart' | 'table' | 'list' | 'progress' | 'alert' | 'calendar' | 'activity';

export type WidgetSize = 'small' | 'medium' | 'large' | 'xlarge';

export type WidgetPosition = {
  x: number;
  y: number;
  w: number;
  h: number;
};

export interface DashboardWidget {
  id: string;
  type: WidgetType;
  title: string;
  description?: string;
  size: WidgetSize;
  position: WidgetPosition;
  config: Record<string, any>;
  data?: any;
  loading?: boolean;
  error?: string;
  permissions?: string[];
  refreshInterval?: number;
  lastUpdated?: string;
}

// Dashboard Layout
export interface DashboardLayout {
  id: string;
  name: string;
  description?: string;
  widgets: DashboardWidget[];
  columns: number;
  rows: number;
  isDefault: boolean;
  preferences?: DashboardPreferences;
  createdAt: string;
  updatedAt: string;
}

// Dashboard Metrics
export interface Trend {
  value: number; // Percentage change
  direction: 'up' | 'down' | 'neutral';
}

export interface DashboardMetrics {
  totalProjects: number;
  activeProjects: number;
  completedProjects: number;
  totalTasks: number;
  completedTasks: number;
  pendingTasks: number;
  overdueTasks: number;
  teamMembers: number;
  trends?: {
    totalProjects?: Trend;
    activeProjects?: Trend;
    completedProjects?: Trend;
    totalTasks?: Trend;
    completedTasks?: Trend;
    pendingTasks?: Trend;
    overdueTasks?: Trend;
    teamMembers?: Trend;
  };
  period?: string;
  lastUpdated: string;
}

// Dashboard Alerts
export type AlertSeverity = 'low' | 'medium' | 'high' | 'critical';
export type AlertStatus = 'unread' | 'read' | 'archived';

export interface DashboardAlert {
  id: string;
  title: string;
  message: string;
  severity: AlertSeverity;
  status: AlertStatus;
  type: string;
  source: string;
  createdAt: string;
  readAt?: string;
  metadata?: Record<string, any>;
}

// Dashboard Preferences
export interface DashboardPreferences {
  theme: 'light' | 'dark' | 'auto';
  layout: 'grid' | 'list' | 'compact';
  density: 'comfortable' | 'compact' | 'spacious';
  refreshInterval: number;
  notifications: {
    enabled: boolean;
    sound: boolean;
    desktop: boolean;
  };
  widgets: {
    defaultSize: WidgetSize;
    autoRefresh: boolean;
    showTitles: boolean;
  };
}

// API Response Types
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  error?: {
    code: string;
    message: string;
    details?: any;
  };
}

// Dashboard API Endpoints
export interface DashboardApiEndpoints {
  getUserDashboard: () => Promise<ApiResponse<DashboardLayout>>;
  getAvailableWidgets: () => Promise<ApiResponse<DashboardWidget[]>>;
  getWidgetData: (widgetId: string) => Promise<ApiResponse<any>>;
  addWidget: (widget: Partial<DashboardWidget>) => Promise<ApiResponse<DashboardWidget>>;
  removeWidget: (widgetId: string) => Promise<ApiResponse<void>>;
  updateWidgetConfig: (widgetId: string, config: Record<string, any>) => Promise<ApiResponse<DashboardWidget>>;
  updateLayout: (layout: Partial<DashboardLayout>) => Promise<ApiResponse<DashboardLayout>>;
  getUserAlerts: () => Promise<ApiResponse<DashboardAlert[]>>;
  markAlertAsRead: (alertId: string) => Promise<ApiResponse<void>>;
  markAllAlertsAsRead: () => Promise<ApiResponse<void>>;
  getMetrics: () => Promise<ApiResponse<DashboardMetrics>>;
  saveUserPreferences: (preferences: Partial<DashboardPreferences>) => Promise<ApiResponse<DashboardPreferences>>;
  resetToDefault: () => Promise<ApiResponse<void>>;
}
