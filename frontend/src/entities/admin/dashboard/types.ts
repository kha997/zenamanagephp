// Admin Dashboard API types and interfaces
export interface AdminDashboardSummary {
  total_users: number;
  total_projects: number;
  total_tasks: number;
  active_sessions: number;
  total_tenants: number;
  active_tenants: number;
  suspended_tenants: number;
  total_storage_used: number;
  total_storage_limit: number;
}

export interface AdminDashboardCharts {
  chart_data: {
    users_growth: Array<{ month: string; count: number }>;
    projects_status: Array<{ status: string; count: number }>;
    tenants_plan: Array<{ plan: string; count: number }>;
    storage_usage: Array<{ tenant: string; used: number; limit: number }>;
  };
}

export interface AdminDashboardActivity {
  activities: Array<{
    id: number;
    type: 'user_created' | 'project_created' | 'tenant_created' | 'system_event';
    description: string;
    user_name?: string;
    tenant_name?: string;
    created_at: string;
  }>;
}

export interface AdminDashboardExport {
  export_url: string;
}

export interface AdminDashboardStats {
  summary: AdminDashboardSummary;
  charts: AdminDashboardCharts;
  activity: AdminDashboardActivity;
}
