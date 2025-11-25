// API Response Types
export interface ApiResponse<T = any> {
  status: 'success' | 'error' | 'fail'
  data?: T
  message?: string
  errors?: Record<string, string[]>
}

export interface PaginatedResponse<T> {
  data: T[]
  pagination: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// User Types
export interface User {
  id: string
  name: string
  email: string
  is_active: boolean
  tenant_id: string
  profile_data?: Record<string, any>
  created_at: string
  updated_at: string
  tenant?: Tenant
}

export interface Tenant {
  id: string
  name: string
  domain: string
  phone?: string
  address?: string
  is_active: boolean
  status: string
  created_at: string
  updated_at: string
}

// Project Types
export interface Project {
  id: string
  name: string
  description?: string
  start_date?: string
  end_date?: string
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled'
  progress: number
  actual_cost: number
  tenant_id: string
  created_at: string
  updated_at: string
  tenant?: Tenant
}

// Task Types
export interface Task {
  id: string
  name: string
  description?: string
  start_date?: string
  end_date?: string
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
  priority: 'low' | 'medium' | 'high' | 'urgent'
  estimated_hours?: number
  actual_hours?: number
  project_id: string
  component_id?: string
  created_at: string
  updated_at: string
  project?: Project
}

// Component Types
export interface Component {
  id: string
  name: string
  description?: string
  planned_cost: number
  actual_cost: number
  progress_percent: number
  project_id: string
  parent_component_id?: string
  created_at: string
  updated_at: string
  project?: Project
  parent?: Component
  children?: Component[]
}

// Task Assignment Types
export interface TaskAssignment {
  id: string
  task_id: string
  user_id: string
  split_percent: number
  role: string
  created_at: string
  updated_at: string
  task?: Task
  user?: User
}

// Auth Types
export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  company_name: string
  company_domain?: string
  company_phone?: string
  company_address?: string
}

export interface AuthResponse {
  user: User
  token: string
  token_type: string
  expires_in: number
}

// Form Types
export interface UserFormData {
  name: string
  email: string
  password?: string
  password_confirmation?: string
  is_active: boolean
  tenant_id: string
}

export interface ProjectFormData {
  name: string
  description?: string
  start_date?: string
  end_date?: string
  status: string
}

export interface TaskFormData {
  name: string
  description?: string
  start_date?: string
  end_date?: string
  status: string
  priority: string
  estimated_hours?: number
  project_id: string
  component_id?: string
}

// Filter Types
export interface UserFilters {
  search?: string
  status?: string
  tenant_id?: string
  page?: number
  per_page?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
}

export interface ProjectFilters {
  search?: string
  status?: string
  tenant_id?: string
  page?: number
  per_page?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
}

export interface TaskFilters {
  search?: string
  status?: string
  priority?: string
  project_id?: string
  component_id?: string
  page?: number
  per_page?: number
  sort_by?: string
  sort_order?: 'asc' | 'desc'
}
