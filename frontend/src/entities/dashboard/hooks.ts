import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { dashboardApi } from './api';
import type {
  DashboardLayout,
  DashboardWidget,
  DashboardPreferences,
} from './types';

// Query Keys
export const dashboardKeys = {
  all: ['dashboard'] as const,
  layout: () => [...dashboardKeys.all, 'layout'] as const,
  widgets: () => [...dashboardKeys.all, 'widgets'] as const,
  widget: (id: string) => [...dashboardKeys.widgets(), id] as const,
  alerts: () => [...dashboardKeys.all, 'alerts'] as const,
  metrics: () => [...dashboardKeys.all, 'metrics'] as const,
  preferences: () => [...dashboardKeys.all, 'preferences'] as const,
};

// Dashboard Layout Hook
export const useDashboardLayout = () => {
  return useQuery({
    queryKey: dashboardKeys.layout(),
    queryFn: () => dashboardApi.getUserDashboard(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Available Widgets Hook
export const useAvailableWidgets = () => {
  return useQuery({
    queryKey: dashboardKeys.widgets(),
    queryFn: () => dashboardApi.getAvailableWidgets(),
    staleTime: 300_000, // 5 minutes
    retry: 1,
  });
};

// Widget Data Hook
export const useWidgetData = (widgetId: string, enabled: boolean = true) => {
  return useQuery({
    queryKey: dashboardKeys.widget(widgetId),
    queryFn: () => dashboardApi.getWidgetData(widgetId),
    enabled: enabled && !!widgetId,
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Dashboard Alerts Hook
export const useDashboardAlerts = () => {
  return useQuery({
    queryKey: dashboardKeys.alerts(),
    queryFn: () => dashboardApi.getUserAlerts(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Dashboard Metrics Hook
export const useDashboardMetrics = () => {
  return useQuery({
    queryKey: dashboardKeys.metrics(),
    queryFn: () => dashboardApi.getMetrics(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Dashboard Preferences Hook - Get from dashboard layout data
export const useDashboardPreferences = () => {
  const { data: dashboard } = useDashboardLayout();
  
  return {
    data: dashboard?.data?.preferences ? { data: dashboard.data.preferences } : null,
    isLoading: false,
    error: null,
  };
};

// Mutations
export const useAddWidget = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (widget: Partial<DashboardWidget>) => dashboardApi.addWidget(widget),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.widgets() });
      queryClient.invalidateQueries({ queryKey: dashboardKeys.layout() });
    },
  });
};

export const useRemoveWidget = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (widgetId: string) => dashboardApi.removeWidget(widgetId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.widgets() });
      queryClient.invalidateQueries({ queryKey: dashboardKeys.layout() });
    },
  });
};

export const useUpdateWidgetConfig = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ widgetId, config }: { widgetId: string; config: Record<string, any> }) =>
      dashboardApi.updateWidgetConfig(widgetId, config),
    onSuccess: (_, { widgetId }) => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.widget(widgetId) });
      queryClient.invalidateQueries({ queryKey: dashboardKeys.layout() });
    },
  });
};

export const useUpdateLayout = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (layout: Partial<DashboardLayout>) => dashboardApi.updateLayout(layout),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.layout() });
    },
  });
};

export const useMarkAlertAsRead = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (alertId: string) => dashboardApi.markAlertAsRead(alertId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.alerts() });
    },
  });
};

export const useMarkAllAlertsAsRead = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: () => dashboardApi.markAllAlertsAsRead(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.alerts() });
    },
  });
};

export const useSavePreferences = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (preferences: Partial<DashboardPreferences>) => dashboardApi.saveUserPreferences(preferences),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.preferences() });
    },
  });
};

export const useResetDashboard = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: () => dashboardApi.resetToDefault(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all });
    },
  });
};

// New hooks for dashboard data
export const useRecentProjects = (limit: number = 5) => {
  return useQuery({
    queryKey: [...dashboardKeys.all, 'recent-projects', limit],
    queryFn: () => dashboardApi.getRecentProjects({ limit }),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

export const useRecentActivity = (limit: number = 10) => {
  return useQuery({
    queryKey: [...dashboardKeys.all, 'recent-activity', limit],
    queryFn: () => dashboardApi.getRecentActivity({ limit }),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

export const useTeamStatus = () => {
  return useQuery({
    queryKey: [...dashboardKeys.all, 'team-status'],
    queryFn: () => dashboardApi.getTeamStatus(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

export const useDashboardChart = (type: 'project-progress' | 'task-completion', period?: string) => {
  return useQuery({
    queryKey: [...dashboardKeys.all, 'chart', type, period],
    queryFn: () => dashboardApi.getChartData(type, period),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};
