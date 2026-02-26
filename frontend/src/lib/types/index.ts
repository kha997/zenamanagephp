/**
 * TypeScript types cho ứng dụng Z.E.N.A
 * Dựa trên database schema từ Laravel backend
 */

// Base Types
export interface BaseEntity {
  id: string
  created_at: string
  updated_at: string
}

// API Response Types
export interface ApiResponse<T = any> {
  status: 'success' | 'error'
  data?: T
  message?: string
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface ApiError {
  status: number
  message: string
  data: any
}

// Auth Types
export interface User extends BaseEntity {
  name: string
  email: string
  tenant_id: string
  avatar?: string
  roles: Role[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  user: User
  token: string
  expires_in: number
}

// RBAC Types
export interface Role extends BaseEntity {
  name: string
  scope: 'system' | 'custom' | 'project'
  description?: string
  permissions: Permission[]
}

export interface Permission extends BaseEntity {
  code: string
  module: string
  action: string
  description?: string
}

// Project Types
export interface Project extends BaseEntity {
  tenant_id: string
  name: string
  description?: string
  start_date: string
  end_date?: string
  status: 'draft' | 'active' | 'on_hold' | 'completed' | 'cancelled'
  progress: number
  actual_cost: number
  planned_cost?: number
  components: Component[]
  tasks: Task[]
  members: ProjectMember[]
}

export interface Component extends BaseEntity {
  project_id: string
  parent_component_id?: string
  name: string
  progress_percent: number
  planned_cost: number
  actual_cost: number
  children?: Component[]
}

export interface Task extends BaseEntity {
  project_id: string
  component_id?: string
  phase_id?: string
  name: string
  description?: string
  start_date: string
  end_date: string
  status: 'pending' | 'in_progress' | 'review' | 'completed' | 'cancelled'
  dependencies: string[] // Array of task IDs
  conditional_tag?: string
  is_hidden: boolean
  assignees: TaskAssignment[]
  progress: number
  priority: 'low' | 'medium' | 'high' | 'critical'
}

export interface TaskAssignment extends BaseEntity {
  task_id: string
  user_id: string
  split_percentage: number
  user: User
}

export interface ProjectMember {
  user_id: string
  project_id: string
  role_id: string
  user: User
  role: Role
}

// Notification Types
export interface Notification extends BaseEntity {
  user_id: string
  priority: 'critical' | 'normal' | 'low'
  title: string
  body: string
  link_url?: string
  channel: 'inapp' | 'email' | 'webhook'
  read_at?: string
  type: string
}

// Form Types
export interface CreateProjectForm {
  name: string
  description?: string
  start_date: string
  end_date?: string
  planned_cost?: number
}

export interface CreateTaskForm {
  name: string
  description?: string
  start_date: string
  end_date: string
  component_id?: string
  assignee_ids: string[]
  priority: 'low' | 'medium' | 'high' | 'critical'
  dependencies?: string[]
}

// UI State Types
export interface LoadingState {
  isLoading: boolean
  error?: string | null
}

export interface PaginationState {
  page: number
  pageSize: number
  total: number
  totalPages: number
}

export interface FilterState {
  search?: string
  status?: string
  dateRange?: {
    start: string
    end: string
  }
  assignee?: string
  priority?: string
}

// Theme Types
export type ThemeMode = 'light' | 'dark' | 'system'
export type Language = 'vi' | 'en'

// Utility Types
export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>
export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>

// WorkTemplate Types
export interface WorkTemplate extends BaseEntity {
  name: string
  description?: string
  category: 'design' | 'construction' | 'qc' | 'inspection'
  template_data: {
    tasks: TemplateTask[]
    conditional_tags?: string[]
    default_settings?: Record<string, any>
  }
  version: number
  is_active: boolean
  tags?: string[]
  tasks_count: number
  category_label: string
}

export interface TemplateTask {
  name: string
  description?: string
  estimated_hours: number
  priority: 'low' | 'medium' | 'high' | 'critical'
  tags?: string[]
  conditional_tag?: string
  dependencies?: string[]
  assignee_role?: string
}

export interface CreateWorkTemplateForm {
  name: string
  description?: string
  category: 'design' | 'construction' | 'qc' | 'inspection'
  template_data: {
    tasks: TemplateTask[]
    conditional_tags?: string[]
    default_settings?: Record<string, any>
  }
  tags?: string[]
}

export interface ApplyTemplateForm {
  project_id: string
  component_id?: string
  default_assignee_id?: string
  base_start_date?: string
  preview_only?: boolean
}

export interface TemplatePreview {
  template: {
    id: string
    name: string
    category: string
    version: number
  }
  target: {
    project_id: string
    project_name: string
    component_id?: string
    component_name?: string
  }
  active_tags: string[]
  tasks_preview: {
    name: string
    is_hidden: boolean
    conditional_tag?: string
    estimated_hours: number
    priority: string
  }[]
  summary: {
    total_tasks: number
    visible_tasks: number
    hidden_tasks: number
    total_hours: number
  }
}

// ChangeRequest Types
export interface ChangeRequest extends BaseEntity {
  project_id: string
  code: string
  title: string
  description: string
  status: 'draft' | 'awaiting_approval' | 'approved' | 'rejected'
  impact_days: number
  impact_cost: number
  impact_kpi: Record<string, any>
  priority: 'low' | 'medium' | 'high' | 'critical'
  tags?: string[]
  visibility: 'internal' | 'client'
  justification?: string
  
  // Relations
  project: Project
  created_by: User
  decided_by?: User
  decided_at?: string
  decision_note?: string
  
  // Computed properties
  can_be_edited: boolean
  can_be_submitted: boolean
  can_be_decided: boolean
  is_decided: boolean
  is_approved: boolean
  is_rejected: boolean
  days_since_created: number
  days_since_decided?: number
}

export type ChangeRequestStatus = ChangeRequest['status']

export interface CreateChangeRequestForm {
  title: string
  description: string
  impact_days: number
  impact_cost: number
  impact_kpi?: Record<string, any>
  priority: 'low' | 'medium' | 'high' | 'critical'
  tags?: string[]
  visibility: 'internal' | 'client'
  justification?: string
}

export type CreateChangeRequestData = CreateChangeRequestForm

export interface UpdateChangeRequestForm {
  title?: string
  description?: string
  impact_days?: number
  impact_cost?: number
  impact_kpi?: Record<string, any>
  priority?: 'low' | 'medium' | 'high' | 'critical'
  tags?: string[]
  visibility?: 'internal' | 'client'
  justification?: string
}

export type UpdateChangeRequestData = UpdateChangeRequestForm

export interface ChangeRequestDecision {
  decision: 'approve' | 'reject'
  decision_note?: string
}

export interface ChangeRequestFilters {
  status?: string
  priority?: string
  project_id?: string
  created_by?: string
  decided_by?: string
  date_range?: {
    start: string
    end: string
  }
  tags?: string[]
  visibility?: 'internal' | 'client'
}

export interface ChangeRequestStats {
  total: number
  by_status: {
    draft: number
    awaiting_approval: number
    approved: number
    rejected: number
  }
  by_priority: {
    low: number
    medium: number
    high: number
    critical: number
  }
  total_impact: {
    days: number
    cost: number
  }
  avg_approval_time: number // in days
}
