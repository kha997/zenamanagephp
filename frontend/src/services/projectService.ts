import apiClient from '../lib/api'
import { Project, ProjectFormData, ProjectFilters, PaginatedResponse } from '../types'

export const projectService = {
  // Get projects list
  async getProjects(filters: ProjectFilters = {}): Promise<PaginatedResponse<Project>> {
    const response = await apiClient.get<PaginatedResponse<Project>>('/projects', filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get projects')
  },

  // Get project by ID
  async getProjectById(id: string): Promise<Project> {
    const response = await apiClient.get<Project>(`/projects/${id}`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get project')
  },

  // Create project
  async createProject(data: ProjectFormData): Promise<Project> {
    const response = await apiClient.post<Project>('/projects', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to create project')
  },

  // Update project
  async updateProject(id: string, data: Partial<ProjectFormData>): Promise<Project> {
    const response = await apiClient.put<Project>(`/projects/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update project')
  },

  // Delete project
  async deleteProject(id: string): Promise<void> {
    const response = await apiClient.delete(`/projects/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete project')
    }
  },

  // Get project statistics
  async getProjectStats(id: string): Promise<any> {
    const response = await apiClient.get(`/projects/${id}/stats`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get project stats')
  },

  // Get project tasks
  async getProjectTasks(id: string, filters: any = {}): Promise<PaginatedResponse<any>> {
    const response = await apiClient.get<PaginatedResponse<any>>(`/projects/${id}/tasks`, filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get project tasks')
  },

  // Get project components
  async getProjectComponents(id: string, filters: any = {}): Promise<PaginatedResponse<any>> {
    const response = await apiClient.get<PaginatedResponse<any>>(`/projects/${id}/components`, filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get project components')
  },
}
