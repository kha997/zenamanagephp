export interface Task {
  id: string | number;
  title: string;
  description?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled' | 'backlog' | 'blocked' | 'done' | 'canceled';
  priority?: 'low' | 'medium' | 'high' | 'urgent';
  project_id?: string | number;
  project?: {
    id: string | number;
    name: string;
    status: string;
  };
  assignee_id?: string | number;
  due_date?: string;
  created_at: string;
  updated_at: string;
  version?: number;
  order?: number;
  dependencies?: (string | number)[];
}

export interface TaskFilters {
  project_id?: string | number;
  status?: string;
  priority?: string;
  assignee_id?: string | number;
  search?: string;
}

export interface TaskMetrics {
  total: number;
  pending: number;
  in_progress: number;
  completed: number;
}

export interface TaskAlert {
  id: string | number;
  type: string;
  message: string;
  task_id: string | number;
}

export interface TaskActivity {
  id: string | number;
  type: string;
  description: string;
  user_id?: string | number;
  created_at: string;
}

