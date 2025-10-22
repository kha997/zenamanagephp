import { http } from '../../shared/api/client';
import type {
  ApiResponse,
  DashboardLayout,
  DashboardWidget,
  DashboardAlert,
  DashboardMetrics,
  DashboardPreferences,
  DashboardApiEndpoints,
} from './types';

export class DashboardApiService implements DashboardApiEndpoints {
  private baseUrl = '/dashboard';

  async getUserDashboard(): Promise<ApiResponse<DashboardLayout>> {
    return http.get<ApiResponse<DashboardLayout>>(`${this.baseUrl}/`);
  }

  async getAvailableWidgets(): Promise<ApiResponse<DashboardWidget[]>> {
    return http.get<ApiResponse<DashboardWidget[]>>(`${this.baseUrl}/widgets`);
  }

  async getWidgetData(widgetId: string): Promise<ApiResponse<any>> {
    return http.get<ApiResponse<any>>(`${this.baseUrl}/widgets/${widgetId}/data`);
  }

  async addWidget(widget: Partial<DashboardWidget>): Promise<ApiResponse<DashboardWidget>> {
    return http.post<ApiResponse<DashboardWidget>>(`${this.baseUrl}/widgets`, widget);
  }

  async removeWidget(widgetId: string): Promise<ApiResponse<void>> {
    return http.delete<ApiResponse<void>>(`${this.baseUrl}/widgets/${widgetId}`);
  }

  async updateWidgetConfig(widgetId: string, config: Record<string, any>): Promise<ApiResponse<DashboardWidget>> {
    return http.put<ApiResponse<DashboardWidget>>(`${this.baseUrl}/widgets/${widgetId}`, { config });
  }

  async updateLayout(layout: Partial<DashboardLayout>): Promise<ApiResponse<DashboardLayout>> {
    return http.put<ApiResponse<DashboardLayout>>(`${this.baseUrl}/layout`, layout);
  }

  async getUserAlerts(): Promise<ApiResponse<DashboardAlert[]>> {
    return http.get<ApiResponse<DashboardAlert[]>>(`${this.baseUrl}/alerts`);
  }

  async markAlertAsRead(alertId: string): Promise<ApiResponse<void>> {
    return http.put<ApiResponse<void>>(`${this.baseUrl}/alerts/${alertId}/read`);
  }

  async markAllAlertsAsRead(): Promise<ApiResponse<void>> {
    return http.put<ApiResponse<void>>(`${this.baseUrl}/alerts/read-all`);
  }

  async getMetrics(): Promise<ApiResponse<DashboardMetrics>> {
    return http.get<ApiResponse<DashboardMetrics>>(`${this.baseUrl}/metrics`);
  }

  async saveUserPreferences(preferences: Partial<DashboardPreferences>): Promise<ApiResponse<DashboardPreferences>> {
    return http.post<ApiResponse<DashboardPreferences>>(`${this.baseUrl}/preferences`, preferences);
  }

  async resetToDefault(): Promise<ApiResponse<void>> {
    return http.post<ApiResponse<void>>(`${this.baseUrl}/reset`);
  }
}

// Export singleton instance
export const dashboardApi = new DashboardApiService();
