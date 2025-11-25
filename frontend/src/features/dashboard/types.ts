/**
 * Dashboard Types
 */

export interface DashboardStats {
  projects: {
    total: number;
    active: number;
    completed: number;
  };
  tasks: {
    total: number;
    completed: number;
    in_progress: number;
    overdue: number;
  };
  users: {
    total: number;
    active: number;
  };
}

export interface RecentProject {
  id: string | number;
  name: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  progress: number;
  updated_at: string;
  owner?: {
    id: string | number;
    name: string;
  };
  created_by_name?: string;
}

export interface RecentTask {
  id: string | number;
  name: string;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  project_name?: string;
  updated_at: string;
}

export interface ActivityItem {
  id: string;
  type: 'project' | 'task' | 'user' | 'system';
  action: string;
  description: string;
  timestamp: string;
  user?: {
    id: string | number;
    name: string;
  };
}

export interface DashboardAlert {
  id: string | number;
  type: 'warning' | 'error' | 'info' | 'success';
  message: string;
  created_at: string;
}

export interface DashboardMetrics {
  project_progress?: any;
  task_completion?: any;
  team_performance?: any;
}

export interface TeamStatus {
  total_members: number;
  active_members: number;
  members_by_role?: Record<string, number>;
}

export interface DashboardData {
  stats: DashboardStats;
  recent_projects: RecentProject[];
  recent_tasks: RecentTask[];
  recent_activity: ActivityItem[];
}

export interface AdminDashboardData {
  stats: {
    total_users: number;
    total_projects: number;
    total_tasks: number;
    active_sessions: number;
  };
  recent_activities: ActivityItem[];
  system_health: string;
}

