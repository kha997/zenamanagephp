import { createApiClient, mapAxiosError } from '../../shared/api/client';
import axios from 'axios';
import type {
  DashboardData,
  DashboardStats,
  RecentProject,
  RecentTask,
  ActivityItem,
  DashboardAlert,
  DashboardMetrics,
  TeamStatus,
  AdminDashboardData,
} from './types';

const apiClient = createApiClient();

/**
 * Dashboard API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/app/dashboard/*
 * Admin endpoints from routes/api.php: /api/admin/dashboard/*
 */
export const dashboardApi = {
  /**
   * Get main dashboard data (combined view)
   * GET /api/v1/app/dashboard
   */
  async getDashboard(): Promise<DashboardData> {
    try {
      const response = await apiClient.get<{ data: DashboardData }>('/v1/app/dashboard');
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get dashboard stats/KPIs
   * GET /api/v1/app/dashboard/stats
   */
  async getStats(): Promise<DashboardStats> {
    try {
      const response = await apiClient.get<{ data: DashboardStats }>('/v1/app/dashboard/stats');
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get recent projects
   * GET /api/v1/app/dashboard/recent-projects?limit=5
   */
  async getRecentProjects(limit: number = 5): Promise<RecentProject[]> {
    try {
      const response = await apiClient.get<{ data: RecentProject[] }>(
        `/v1/app/dashboard/recent-projects?limit=${limit}`
      );
      return response.data?.data || response.data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get recent tasks
   * GET /api/v1/app/dashboard/recent-tasks?limit=5
   */
  async getRecentTasks(limit: number = 5): Promise<RecentTask[]> {
    try {
      const response = await apiClient.get<{ data: RecentTask[] }>(
        `/v1/app/dashboard/recent-tasks?limit=${limit}`
      );
      return response.data?.data || response.data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get recent activity
   * GET /api/v1/app/dashboard/recent-activity?limit=10
   */
  async getRecentActivity(limit: number = 10): Promise<ActivityItem[]> {
    try {
      const response = await apiClient.get<{ data: ActivityItem[] }>(
        `/v1/app/dashboard/recent-activity?limit=${limit}`
      );
      return response.data?.data || response.data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get dashboard alerts
   * GET /api/v1/app/dashboard/alerts
   */
  async getAlerts(): Promise<DashboardAlert[]> {
    try {
      const response = await apiClient.get<{ data: DashboardAlert[] }>('/v1/app/dashboard/alerts');
      return response.data?.data || response.data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get dashboard metrics
   * GET /api/v1/app/dashboard/metrics
   */
  async getMetrics(): Promise<DashboardMetrics> {
    try {
      const response = await apiClient.get<{ data: DashboardMetrics }>('/v1/app/dashboard/metrics');
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get team status
   * GET /api/v1/app/dashboard/team-status
   */
  async getTeamStatus(): Promise<TeamStatus> {
    try {
      const response = await apiClient.get<{ data: TeamStatus }>('/v1/app/dashboard/team-status');
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Mark alert as read
   * PUT /api/v1/app/dashboard/alerts/{id}/read
   */
  async markAlertAsRead(id: string | number): Promise<void> {
    try {
      await apiClient.put(`/v1/app/dashboard/alerts/${id}/read`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Mark all alerts as read
   * PUT /api/v1/app/dashboard/alerts/read-all
   */
  async markAllAlertsAsRead(): Promise<void> {
    try {
      await apiClient.put('/v1/app/dashboard/alerts/read-all');
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  // Admin Dashboard APIs

  /**
   * Get admin dashboard data
   * GET /api/admin/dashboard/summary
   */
  async getAdminDashboard(): Promise<AdminDashboardData> {
    try {
      // Admin routes are /api/admin/dashboard/* (not /api/v1)
      // Use apiClient to ensure authentication headers are included
      
      // Debug: Check if token exists
      if (typeof window !== 'undefined') {
        const token = window.localStorage.getItem('auth_token');
        if (!token) {
          console.warn('[dashboardApi.getAdminDashboard] No auth token found in localStorage');
          console.warn('[dashboardApi.getAdminDashboard] User may need to log in again');
          // Don't throw here - let the API return 401 so the error handler can deal with it
        } else {
          console.log('[dashboardApi.getAdminDashboard] Token found, length:', token.length);
        }
      }
      
      const response = await apiClient.get<{ data: AdminDashboardData }>('/admin/dashboard/summary');
      return response.data?.data || response.data;
    } catch (error: any) {
      console.error('[dashboardApi.getAdminDashboard] Error:', error);
      
      // If 401, provide helpful message
      if (error?.status === 401) {
        console.error('[dashboardApi.getAdminDashboard] 401 Unauthorized - User may need to log in');
        if (typeof window !== 'undefined') {
          const token = window.localStorage.getItem('auth_token');
          console.error('[dashboardApi.getAdminDashboard] Token in localStorage:', token ? 'exists' : 'missing');
        }
      }
      
      throw mapAxiosError(error);
    }
  },

  /**
   * Get admin dashboard stats
   * GET /api/admin/dashboard/stats (if exists)
   */
  async getAdminStats(): Promise<any> {
    try {
      const response = await axios.get('/api/admin/dashboard/stats', {
        withCredentials: true,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': typeof window !== 'undefined' 
            ? (window.Laravel?.csrfToken ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content)
            : undefined,
          ...(typeof window !== 'undefined' && window.localStorage.getItem('auth_token') 
            ? { 'Authorization': `Bearer ${window.localStorage.getItem('auth_token')}` }
            : {}),
        },
      });
      return response.data?.data || response.data;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

