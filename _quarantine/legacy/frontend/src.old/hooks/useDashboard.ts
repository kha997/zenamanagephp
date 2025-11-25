import { useState, useEffect, useCallback } from 'react';
import { useAuth } from './useAuth';
import { apiClient } from '../lib/api';

interface Dashboard {
  id: string;
  name: string;
  layout_config: Record<string, any>;
  widgets: Widget[];
  preferences: Record<string, any>;
  is_default: boolean;
}

interface Widget {
  id: string;
  name: string;
  type: string;
  category: string;
  position: {
    x: number;
    y: number;
    w: number;
    h: number;
  };
  config: Record<string, any>;
}

interface Alert {
  id: string;
  type: 'info' | 'warning' | 'error' | 'success';
  category: string;
  title: string;
  message: string;
  is_read: boolean;
  created_at: string;
  expires_at?: string;
}

interface Metric {
  id: string;
  code: string;
  name: string;
  category: string;
  unit: string;
  value: number;
  display_config: Record<string, any>;
  recorded_at: string;
}

export const useDashboard = () => {
  const { user } = useAuth();
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [widgets, setWidgets] = useState<Widget[]>([]);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [metrics, setMetrics] = useState<Metric[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Fetch user dashboard
  const fetchDashboard = useCallback(async (userId: string, projectId?: string) => {
    try {
      setLoading(true);
      setError(null);
      
      const params = projectId ? { project_id: projectId } : {};
      const response = await apiClient.get('/dashboard', { params });
      
      setDashboard(response.data.data);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to fetch dashboard');
      console.error('Error fetching dashboard:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  // Fetch available widgets
  const fetchAvailableWidgets = useCallback(async () => {
    try {
      const response = await apiClient.get('/dashboard/widgets');
      setWidgets(response.data.data);
    } catch (err: any) {
      console.error('Error fetching widgets:', err);
    }
  }, []);

  // Fetch widget data
  const getWidgetData = useCallback(async (
    widgetId: string,
    projectId?: string,
    params: Record<string, any> = {}
  ) => {
    try {
      const queryParams = {
        ...params,
        ...(projectId && { project_id: projectId })
      };
      
      const response = await apiClient.get(`/dashboard/widgets/${widgetId}/data`, {
        params: queryParams
      });
      
      return response.data.data;
    } catch (err: any) {
      console.error('Error fetching widget data:', err);
      throw err;
    }
  }, []);

  // Update dashboard layout
  const updateDashboardLayout = useCallback(async (layout: any[]) => {
    try {
      const response = await apiClient.put('/dashboard/customization/layout', {
        layout: layout
      });
      
      return response.data;
    } catch (err: any) {
      console.error('Error updating layout:', err);
      throw err;
    }
  }, []);

  // Add widget to dashboard
  const addWidget = useCallback(async (
    widgetId: string,
    config: Record<string, any>
  ) => {
    try {
      const response = await apiClient.post('/dashboard/customization/widgets', {
        widget_id: widgetId,
        config: config
      });
      
      return response.data;
    } catch (err: any) {
      console.error('Error adding widget:', err);
      throw err;
    }
  }, []);

  // Remove widget from dashboard
  const removeWidget = useCallback(async (widgetInstanceId: string) => {
    try {
      const response = await apiClient.delete(`/dashboard/customization/widgets/${widgetInstanceId}`);
      return response.data;
    } catch (err: any) {
      console.error('Error removing widget:', err);
      throw err;
    }
  }, []);

  // Update widget configuration
  const updateWidgetConfig = useCallback(async (
    widgetInstanceId: string,
    config: Record<string, any>
  ) => {
    try {
      const response = await apiClient.put(`/dashboard/customization/widgets/${widgetInstanceId}/config`, {
        config: config
      });
      
      return response.data;
    } catch (err: any) {
      console.error('Error updating widget config:', err);
      throw err;
    }
  }, []);

  // Refresh widget data
  const refreshWidgetData = useCallback(async (widgetId: string) => {
    try {
      // This would typically trigger a cache refresh on the backend
      await apiClient.post(`/dashboard/widgets/${widgetId}/refresh`);
    } catch (err: any) {
      console.error('Error refreshing widget data:', err);
    }
  }, []);

  // Fetch user alerts
  const fetchAlerts = useCallback(async (
    projectId?: string,
    type?: string,
    category?: string,
    unreadOnly: boolean = false
  ) => {
    try {
      const params = {
        ...(projectId && { project_id: projectId }),
        ...(type && { type }),
        ...(category && { category }),
        unread_only: unreadOnly
      };
      
      const response = await apiClient.get('/dashboard/alerts', { params });
      setAlerts(response.data.data);
    } catch (err: any) {
      console.error('Error fetching alerts:', err);
    }
  }, []);

  // Mark alert as read
  const markAlertAsRead = useCallback(async (alertId: string) => {
    try {
      await apiClient.put(`/dashboard/alerts/${alertId}/read`);
      
      setAlerts(prev => 
        prev.map(alert => 
          alert.id === alertId 
            ? { ...alert, is_read: true }
            : alert
        )
      );
    } catch (err: any) {
      console.error('Error marking alert as read:', err);
    }
  }, []);

  // Mark all alerts as read
  const markAllAlertsAsRead = useCallback(async (projectId?: string) => {
    try {
      const params = projectId ? { project_id: projectId } : {};
      await apiClient.put('/dashboard/alerts/read-all', params);
      
      setAlerts(prev => 
        prev.map(alert => ({ ...alert, is_read: true }))
      );
    } catch (err: any) {
      console.error('Error marking all alerts as read:', err);
    }
  }, []);

  // Fetch dashboard metrics
  const fetchMetrics = useCallback(async (
    projectId?: string,
    category?: string,
    timeRange: string = '7d'
  ) => {
    try {
      const params = {
        ...(projectId && { project_id: projectId }),
        ...(category && { category }),
        time_range: timeRange
      };
      
      const response = await apiClient.get('/dashboard/metrics', { params });
      setMetrics(response.data.data);
    } catch (err: any) {
      console.error('Error fetching metrics:', err);
    }
  }, []);

  // Get dashboard template
  const getDashboardTemplate = useCallback(async () => {
    try {
      const response = await apiClient.get('/dashboard/template');
      return response.data.data;
    } catch (err: any) {
      console.error('Error fetching dashboard template:', err);
      throw err;
    }
  }, []);

  // Reset dashboard to default
  const resetDashboard = useCallback(async () => {
    try {
      const response = await apiClient.post('/dashboard/reset');
      setDashboard(response.data.data);
    } catch (err: any) {
      console.error('Error resetting dashboard:', err);
      throw err;
    }
  }, []);

  // Save user preferences
  const saveUserPreferences = useCallback(async (preferences: Record<string, any>) => {
    try {
      const response = await apiClient.post('/dashboard/preferences', {
        preferences: preferences
      });
      
      setDashboard(response.data.data);
    } catch (err: any) {
      console.error('Error saving preferences:', err);
      throw err;
    }
  }, []);

  // Initialize dashboard data
  useEffect(() => {
    if (user?.id) {
      fetchDashboard(user.id);
      fetchAvailableWidgets();
      fetchAlerts();
      fetchMetrics();
    }
  }, [user?.id, fetchDashboard, fetchAvailableWidgets, fetchAlerts, fetchMetrics]);

  return {
    // State
    dashboard,
    widgets,
    alerts,
    metrics,
    loading,
    error,
    
    // Actions
    fetchDashboard,
    fetchAvailableWidgets,
    getWidgetData,
    updateDashboardLayout,
    addWidget,
    removeWidget,
    updateWidgetConfig,
    refreshWidgetData,
    fetchAlerts,
    markAlertAsRead,
    markAllAlertsAsRead,
    fetchMetrics,
    getDashboardTemplate,
    resetDashboard,
    saveUserPreferences
  };
};
