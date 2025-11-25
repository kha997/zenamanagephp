// Projects API types and interfaces
export interface Project {
  id: number;
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget?: number;
  spent?: number;
  progress: number;
  tenant_id: number;
  tenant_name: string;
  created_by: number;
  created_by_name: string;
  team_members: ProjectTeamMember[];
  created_at: string;
  updated_at: string;
}

export interface ProjectTeamMember {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  role: string;
  joined_at: string;
}

export interface ProjectsResponse {
  data: Project[];
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

export interface ProjectsFilters {
  search?: string;
  status?: string;
  priority?: string;
  tenant_id?: number;
  created_by?: number;
  page?: number;
  per_page?: number;
}

export interface CreateProjectRequest {
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget?: number;
  team_member_ids?: number[];
}

// Projects API types and interfaces
export interface Project {
  id: number;
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget?: number;
  spent?: number;
  progress: number;
  tenant_id: number;
  tenant_name: string;
  created_by: number;
  created_by_name: string;
  team_members: ProjectTeamMember[];
  created_at: string;
  updated_at: string;
}

export interface ProjectTeamMember {
  id: number;
  user_id: number;
  user_name: string;
  user_email: string;
  role: string;
  joined_at: string;
}

export interface ProjectsResponse {
  data: Project[];
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

export interface ProjectsFilters {
  search?: string;
  status?: string;
  priority?: string;
  tenant_id?: number;
  created_by?: number;
  page?: number;
  per_page?: number;
}

export interface CreateProjectRequest {
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget?: number;
  team_member_ids?: number[];
}

export interface UpdateProjectRequest {
  name?: string;
  description?: string;
  status?: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority?: 'low' | 'medium' | 'high' | 'urgent';
  start_date?: string;
  end_date?: string;
  budget?: number;
  team_member_ids?: number[];
}

// Projects KPI Types
export interface ProjectsMetrics {
  total_projects: number;
  active_projects: number;
  completed_projects: number;
  overdue_projects: number;
  total_budget?: number;
  average_progress?: number;
  projects_by_status?: Record<string, number>;
  projects_by_priority?: Record<string, number>;
  trends?: {
    total_projects?: Trend;
    active_projects?: Trend;
    completed_projects?: Trend;
    overdue_projects?: Trend;
  };
  period?: string;
}

export interface Trend {
  value: number; // Percentage change
  direction: 'up' | 'down' | 'neutral';
}

// Projects Alert Types
export interface ProjectAlert {
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

// Projects Activity Types
export interface ProjectActivity {
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
