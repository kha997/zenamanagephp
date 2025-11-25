import { apiClient } from './client'
import { Project, CreateProjectForm, ApiResponse } from '../types'
import { API_ENDPOINTS } from '../constants'

/**
 * Projects API service
 */
export class ProjectsService {
  /**
   * Lấy danh sách projects
   */
  static async getProjects(params?: {
    page?: number
    per_page?: number
    search?: string
    status?: string
  }): Promise<ApiResponse<Project[]>> {
    return await apiClient.get(API_ENDPOINTS.PROJECTS.LIST, { params })
  }

  /**
   * Lấy chi tiết project
   */
  static async getProject(id: string): Promise<Project> {
    const response = await apiClient.get<Project>(API_ENDPOINTS.PROJECTS.DETAIL(id))
    return response.data!
  }

  /**
   * Tạo project mới
   */
  static async createProject(data: CreateProjectForm): Promise<Project> {
    const response = await apiClient.post<Project>(API_ENDPOINTS.PROJECTS.CREATE, data)
    return response.data!
  }

  /**
   * Cập nhật project
   */
  static async updateProject(id: string, data: Partial<CreateProjectForm>): Promise<Project> {
    const response = await apiClient.put<Project>(API_ENDPOINTS.PROJECTS.UPDATE(id), data)
    return response.data!
  }

  /**
   * Xóa project
   */
  static async deleteProject(id: string): Promise<void> {
    await apiClient.delete(API_ENDPOINTS.PROJECTS.DELETE(id))
  }
}