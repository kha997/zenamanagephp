export interface Project {
  id: string | number;
  name: string;
  description?: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  order?: number;
  priority?: string;
  owner_id?: string | number;
  start_date?: string;
  end_date?: string;
  budget_total?: number;
  created_at: string;
  updated_at: string;
  users?: Array<{
    id: string | number;
    name: string;
    email: string;
    role?: string;
    pivot?: {
      role_id?: string | number;
    };
  }>;
}

export interface ProjectFilters {
  search?: string;
  status?: string;
  priority?: string;
  owner_id?: string | number;
}

export interface ProjectMetrics {
  total: number;
  active: number;
  completed: number;
  on_hold: number;
}

export interface ProjectAlert {
  id: string | number;
  type: string;
  message: string;
  project_id: string | number;
}

export interface ProjectActivity {
  id: string | number;
  type: string;
  description: string;
  user_id?: string | number;
  created_at: string;
}

