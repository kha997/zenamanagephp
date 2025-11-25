// Tasks API types and interfaces

export interface Task {
  id: string;
  name: string;
  description?: string;
  status: 'backlog' | 'in_progress' | 'blocked' | 'done' | 'canceled';
  priority: 'low' | 'normal' | 'high' | 'urgent';
  project_id: string;
  assignee_id?: string;
  start_date?: string;
  end_date?: string;
  estimated_hours?: number;
  actual_hours?: number;
  progress_percent?: number;
  created_at: string;
  updated_at: string;
}

export interface TasksResponse {
  data: Task[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev?: string;
    next?: string;
  };
}

export interface TasksFilters {
  search?: string;
  status?: string;
  priority?: string;
  project_id?: string;
  assignee_id?: string;
  page?: number;
  per_page?: number;
}

// Tasks KPI Types
export interface TasksMetrics {
  total_tasks: number;
  pending_tasks: number;
  in_progress_tasks: number;
  completed_tasks: number;
  overdue_tasks: number;
  trends?: {
    total_tasks?: Trend;
    pending_tasks?: Trend;
    in_progress_tasks?: Trend;
    completed_tasks?: Trend;
    overdue_tasks?: Trend;
  };
  period?: string;
}

export interface Trend {
  value: number; // Percentage change
  direction: 'up' | 'down' | 'neutral';
}

// Tasks Alert Types
export interface TaskAlert {
  id: string;
  title: string;
  message: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  status: 'unread' | 'read' | 'archived';
  type: string;
  source: string;
  createdAt: string;
  readAt?: string;
  metadata?: Record<string, any>;
}

// Tasks Activity Types
export interface TaskActivity {
  id: string;
  type: string;
  action: string;
  description: string;
  timestamp: string;
  user?: {
    id: string;
    name: string;
    avatar?: string;
  };
}

