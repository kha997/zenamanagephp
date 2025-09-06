/**
 * Component Types & DTOs
 * Định nghĩa các type cho module Components
 */

export interface Component {
  id: string;
  project_id: string;
  parent_component_id?: string;
  name: string;
  progress_percent: number;
  planned_cost: number;
  actual_cost: number;
  created_at: string;
  updated_at: string;
  // Relations
  project?: Project;
  parent_component?: Component;
  children?: Component[];
  tasks?: Task[];
}

export interface ComponentWithChildren extends Component {
  children: ComponentWithChildren[];
  level?: number;
  expanded?: boolean;
}

export interface ComponentFilters {
  search?: string;
  parent_component_id?: string;
  min_cost?: number;
  max_cost?: number;
  min_progress?: number;
  max_progress?: number;
  sort_by?: 'name' | 'progress_percent' | 'planned_cost' | 'actual_cost' | 'created_at';
  sort_order?: 'asc' | 'desc';
}

export interface ComponentListResponse {
  data: Component[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
  links: {
    first: string;
    last: string;
    prev?: string;
    next?: string;
  };
}

export interface ComponentDetailResponse {
  data: Component;
}

export interface CreateComponentRequest {
  name: string;
  parent_component_id?: string;
  planned_cost: number;
  progress_percent?: number;
}

export interface UpdateComponentRequest extends Partial<CreateComponentRequest> {
  actual_cost?: number;
}

export interface ComponentCostSummary {
  total_planned: number;
  total_actual: number;
  variance: number;
  variance_percent: number;
  components_count: number;
}

export interface ComponentProgressUpdate {
  component_id: string;
  progress_percent: number;
  actual_cost?: number;
}

// API Error types
export interface ComponentError {
  message: string;
  errors?: Record<string, string[]>;
}