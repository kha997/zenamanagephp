import { api, ApiResponse, PaginatedResponse } from './api'

// Project types
export interface Project {
  id: string
  name: string
  description?: string
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled'
  priority: 'low' | 'medium' | 'high' | 'critical'
  start_date?: string
  end_date?: string
  budget?: number
  actual_cost?: number
  progress: number
  tenant_id: string
  created_by: string
  created_at: string
  updated_at: string
  tenant?: {
    id: string
    name: string
  }
  createdBy?: {
    id: string
    name: string
  }
  tasks_count?: number
  components_count?: number
}

export interface ProjectFilters {
  search?: string
  status?: string
  priority?: string
  tenant_id?: string
  created_by?: string
  start_date_from?: string
  start_date_to?: string
  end_date_from?: string
  end_date_to?: string
}

export interface CreateProjectData {
  name: string
  description?: string
  status?: string
  priority?: string
  start_date?: string
  end_date?: string
  budget?: number
  tenant_id: string
}

export interface UpdateProjectData extends Partial<CreateProjectData> {
  id: string
}

// Task types
export interface Task {
  id: string
  name: string
  description?: string
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
  priority: 'low' | 'medium' | 'high' | 'critical'
  start_date?: string
  end_date?: string
  due_date?: string
  completed_at?: string
  progress: number
  weight: number
  estimated_hours?: number
  actual_hours?: number
  actual_cost?: number
  dependencies?: string[]
  metadata?: Record<string, any>
  is_hidden: boolean
  project_id: string
  component_id?: string
  user_id?: string
  created_by: string
  tenant_id: string
  created_at: string
  updated_at: string
  project?: Project
  component?: {
    id: string
    name: string
  }
  assignedTo?: {
    id: string
    name: string
    avatar?: string
  }
  createdBy?: {
    id: string
    name: string
  }
}

export interface TaskFilters {
  search?: string
  status?: string
  priority?: string
  project_id?: string
  component_id?: string
  user_id?: string
  tenant_id?: string
  start_date_from?: string
  start_date_to?: string
  due_date_from?: string
  due_date_to?: string
}

export interface CreateTaskData {
  name: string
  description?: string
  status?: string
  priority?: string
  start_date?: string
  end_date?: string
  due_date?: string
  weight?: number
  estimated_hours?: number
  dependencies?: string[]
  project_id: string
  component_id?: string
  user_id?: string
}

export interface UpdateTaskData extends Partial<CreateTaskData> {
  id: string
}

// User types
export interface User {
  id: string
  name: string
  email: string
  role: string
  avatar?: string
  phone?: string
  is_active: boolean
  last_login_at?: string
  tenant_id: string
  created_at: string
  updated_at: string
  tenant?: {
    id: string
    name: string
  }
}

export interface UserFilters {
  search?: string
  role?: string
  is_active?: boolean
  tenant_id?: string
}

export interface CreateUserData {
  name: string
  email: string
  password: string
  password_confirmation: string
  role: string
  phone?: string
  tenant_id: string
}

export interface UpdateUserData extends Partial<CreateUserData> {
  id: string
}

class DataService {
  // Projects
  async getProjects(filters?: ProjectFilters, page = 1, perPage = 15): Promise<PaginatedResponse<Project>> {
    const params = new URLSearchParams()
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params.append(key, value.toString())
        }
      })
    }
    
    params.append('page', page.toString())
    params.append('per_page', perPage.toString())
    
    const response = await api.get<PaginatedResponse<Project>>(`/projects?${params.toString()}`)
    return response.data
  }

  async getProject(id: string): Promise<Project> {
    const response = await api.get<Project>(`/projects/${id}`)
    return response.data
  }

  async createProject(data: CreateProjectData): Promise<Project> {
    const response = await api.post<Project>('/projects', data)
    return response.data
  }

  async updateProject(data: UpdateProjectData): Promise<Project> {
    const { id, ...updateData } = data
    const response = await api.put<Project>(`/projects/${id}`, updateData)
    return response.data
  }

  async deleteProject(id: string): Promise<void> {
    await api.delete(`/projects/${id}`)
  }

  // Tasks
  async getTasks(filters?: TaskFilters, page = 1, perPage = 15): Promise<PaginatedResponse<Task>> {
    const params = new URLSearchParams()
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params.append(key, value.toString())
        }
      })
    }
    
    params.append('page', page.toString())
    params.append('per_page', perPage.toString())
    
    const response = await api.get<PaginatedResponse<Task>>(`/tasks?${params.toString()}`)
    return response.data
  }

  async getTask(id: string): Promise<Task> {
    const response = await api.get<Task>(`/tasks/${id}`)
    return response.data
  }

  async createTask(data: CreateTaskData): Promise<Task> {
    const response = await api.post<Task>('/tasks', data)
    return response.data
  }

  async updateTask(data: UpdateTaskData): Promise<Task> {
    const { id, ...updateData } = data
    const response = await api.put<Task>(`/tasks/${id}`, updateData)
    return response.data
  }

  async deleteTask(id: string): Promise<void> {
    await api.delete(`/tasks/${id}`)
  }

  async updateTaskStatus(id: string, status: string): Promise<Task> {
    const response = await api.patch<Task>(`/tasks/${id}/status`, { status })
    return response.data
  }

  async updateTaskProgress(id: string, progress: number): Promise<Task> {
    const response = await api.patch<Task>(`/tasks/${id}/progress`, { progress })
    return response.data
  }

  // Users
  async getUsers(filters?: UserFilters, page = 1, perPage = 15): Promise<PaginatedResponse<User>> {
    const params = new URLSearchParams()
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params.append(key, value.toString())
        }
      })
    }
    
    params.append('page', page.toString())
    params.append('per_page', perPage.toString())
    
    const response = await api.get<PaginatedResponse<User>>(`/users?${params.toString()}`)
    return response.data
  }

  async getUser(id: string): Promise<User> {
    const response = await api.get<User>(`/users/${id}`)
    return response.data
  }

  async createUser(data: CreateUserData): Promise<User> {
    const response = await api.post<User>('/users', data)
    return response.data
  }

  async updateUser(data: UpdateUserData): Promise<User> {
    const { id, ...updateData } = data
    const response = await api.put<User>(`/users/${id}`, updateData)
    return response.data
  }

  async deleteUser(id: string): Promise<void> {
    await api.delete(`/users/${id}`)
  }

  async toggleUserStatus(id: string): Promise<User> {
    const response = await api.patch<User>(`/users/${id}/toggle-status`)
    return response.data
  }

  // Dashboard stats
  async getDashboardStats(): Promise<{
    projects: {
      total: number
      active: number
      completed: number
      overdue: number
    }
    tasks: {
      total: number
      pending: number
      in_progress: number
      completed: number
      overdue: number
    }
    users: {
      total: number
      active: number
      inactive: number
    }
  }> {
    const response = await api.get('/dashboard/stats')
    return response.data
  }
}

export const dataService = new DataService()
export default dataService
