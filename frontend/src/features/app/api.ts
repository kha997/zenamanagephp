import { createApiClient } from '../../shared/api/client';

const apiClient = createApiClient();

/**
 * ActivityItem interface
 * 
 * Round 248: Global Activity / My Work Feed
 */
export interface ActivityItem {
  id: string;
  timestamp: string;
  module: 'tasks' | 'documents' | 'cost' | 'rbac';
  type: string;
  title: string;
  summary: string;
  project_id: string | null;
  project_name: string | null;
  entity_type: string;
  entity_id: string;
  actor_id: string | null;
  actor_name: string | null;
  is_directly_related: boolean;
}

/**
 * ActivityFeedResponse interface
 * 
 * Round 248: Global Activity / My Work Feed
 */
export interface ActivityFeedResponse {
  items: ActivityItem[];
  meta: {
    page: number;
    per_page: number;
    total: number;
    last_page?: number;
  };
}

/**
 * ActivityFeedParams interface
 * 
 * Round 248: Global Activity / My Work Feed
 */
export interface ActivityFeedParams {
  page?: number;
  per_page?: number;
  module?: 'all' | 'tasks' | 'documents' | 'cost' | 'rbac';
  from?: string;
  to?: string;
  search?: string;
}

/**
 * Activity Feed API Client
 * 
 * Round 248: Global Activity / My Work Feed
 * 
 * Endpoint: GET /api/v1/app/activity-feed
 */
export const activityFeedApi = {
  /**
   * Get activity feed for current user
   */
  async getActivityFeed(params: ActivityFeedParams = {}): Promise<ActivityFeedResponse> {
    try {
      const queryParams = new URLSearchParams();
      
      if (params.page) {
        queryParams.append('page', params.page.toString());
      }
      if (params.per_page) {
        queryParams.append('per_page', params.per_page.toString());
      }
      if (params.module && params.module !== 'all') {
        queryParams.append('module', params.module);
      }
      if (params.from) {
        queryParams.append('from', params.from);
      }
      if (params.to) {
        queryParams.append('to', params.to);
      }
      if (params.search) {
        queryParams.append('search', params.search);
      }

      const response = await apiClient.get<{
        data: {
          items: ActivityItem[];
        };
        meta: {
          page: number;
          per_page: number;
          total: number;
          last_page?: number;
        };
      }>(`/api/v1/app/activity-feed?${queryParams.toString()}`);

      return {
        items: response.data.data.items,
        meta: response.data.meta,
      };
    } catch (error) {
      console.error('Failed to fetch activity feed:', error);
      throw error;
    }
  },
};

/**
 * Notification interface
 * 
 * Round 251: Notifications Center Phase 1
 */
export interface Notification {
  id: string;
  tenant_id: string;
  user_id: string;
  module: 'tasks' | 'documents' | 'cost' | 'rbac' | 'system' | null;
  type: string;
  title: string;
  message: string | null;
  entity_type: string | null;
  entity_id: string | null;
  is_read: boolean;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

/**
 * NotificationsResponse interface
 * 
 * Round 251: Notifications Center Phase 1
 */
export interface NotificationsResponse {
  data: Notification[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    unread_count: number;
  };
  links?: {
    first?: string;
    last?: string;
    prev?: string | null;
    next?: string | null;
  };
}

/**
 * NotificationsParams interface
 * 
 * Round 251: Notifications Center Phase 1
 */
export interface NotificationsParams {
  page?: number;
  per_page?: number;
  is_read?: boolean;
  module?: 'tasks' | 'documents' | 'cost' | 'rbac' | 'system';
  search?: string;
}

/**
 * Notifications API Client
 * 
 * Round 251: Notifications Center Phase 1
 * 
 * Endpoints:
 * - GET /api/v1/app/notifications
 * - PUT /api/v1/app/notifications/{id}/read
 * - PUT /api/v1/app/notifications/read-all
 */
export const notificationsApi = {
  /**
   * Get notifications for current user
   */
  async getNotifications(params: NotificationsParams = {}): Promise<NotificationsResponse> {
    try {
      const queryParams = new URLSearchParams();
      
      if (params.page) {
        queryParams.append('page', params.page.toString());
      }
      if (params.per_page) {
        queryParams.append('per_page', params.per_page.toString());
      }
      if (params.is_read !== undefined) {
        queryParams.append('is_read', params.is_read.toString());
      }
      if (params.module) {
        queryParams.append('module', params.module);
      }
      if (params.search) {
        queryParams.append('search', params.search);
      }

      const response = await apiClient.get<{
        data: Notification[];
        meta: {
          current_page: number;
          per_page: number;
          total: number;
          last_page: number;
          from: number | null;
          to: number | null;
          unread_count: number;
        };
        links?: {
          first?: string;
          last?: string;
          prev?: string | null;
          next?: string | null;
        };
      }>(`/api/v1/app/notifications?${queryParams.toString()}`);

      return {
        data: response.data.data,
        meta: response.data.meta,
        links: response.data.links,
      };
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
      throw error;
    }
  },

  /**
   * Mark a notification as read
   */
  async markNotificationRead(notificationId: string): Promise<{ id: string; is_read: boolean }> {
    try {
      const response = await apiClient.put<{
        data: {
          id: string;
          is_read: boolean;
        };
      }>(`/api/v1/app/notifications/${notificationId}/read`);

      return response.data.data;
    } catch (error) {
      console.error('Failed to mark notification as read:', error);
      throw error;
    }
  },

  /**
   * Mark all notifications as read
   */
  async markAllNotificationsRead(): Promise<{ count: number }> {
    try {
      const response = await apiClient.put<{
        data: {
          count: number;
        };
      }>('/api/v1/app/notifications/read-all');

      return response.data.data;
    } catch (error) {
      console.error('Failed to mark all notifications as read:', error);
      throw error;
    }
  },
};
