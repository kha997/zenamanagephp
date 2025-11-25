import { apiClient } from './client'
import { Task, CreateTaskForm, ApiResponse } from '../types'
import { API_ENDPOINTS } from '../constants'

/**
 * Tasks API service
 */
export class TasksService {
  /**
   * Lấy danh sách tasks của project
   */
  static async getTasks(projectId: string, params?: {
    page?: number
    per_page?: number
    status?: string
    assignee?: string
  }): Promise<ApiResponse<Task[]>> {
    return await apiClient.get(API_ENDPOINTS.TASKS.LIST(projectId), { params })
  }

  /**
   * Lấy chi tiết task
   */
  static async getTask(projectId: string, taskId: string): Promise<Task> {
    const response = await apiClient.get<Task>(
      API_ENDPOINTS.TASKS.DETAIL(projectId, taskId)
    )
    return response.data!
  }

  /**
   * Tạo task mới
   */
  static async createTask(projectId: string, data: CreateTaskForm): Promise<Task> {
    const response = await apiClient.post<Task>(
      API_ENDPOINTS.TASKS.CREATE(projectId),
      data
    )
    return response.data!
  }

  /**
   * Cập nhật task
   */
  static async updateTask(
    projectId: string,
    taskId: string,
    data: Partial<CreateTaskForm>
  ): Promise<Task> {
    const response = await apiClient.put<Task>(
      API_ENDPOINTS.TASKS.UPDATE(projectId, taskId),
      data
    )
    return response.data!
  }

  /**
   * Xóa task
   */
  static async deleteTask(projectId: string, taskId: string): Promise<void> {
    await apiClient.delete(API_ENDPOINTS.TASKS.DELETE(projectId, taskId))
  }

  /**
   * Cập nhật trạng thái task
   */
  static async updateTaskStatus(
    projectId: string,
    taskId: string,
    status: string
  ): Promise<Task> {
    const response = await apiClient.patch<Task>(
      API_ENDPOINTS.TASKS.UPDATE(projectId, taskId),
      { status }
    )
    return response.data!
  }
}