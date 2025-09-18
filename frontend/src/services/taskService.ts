import apiClient from '../lib/api'
import { Task, TaskFormData, TaskFilters, PaginatedResponse, TaskAssignment } from '../types'

export const taskService = {
  // Get tasks list
  async getTasks(filters: TaskFilters = {}): Promise<PaginatedResponse<Task>> {
    const response = await apiClient.get<PaginatedResponse<Task>>('/tasks', filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get tasks')
  },

  // Get task by ID
  async getTaskById(id: string): Promise<Task> {
    const response = await apiClient.get<Task>(`/tasks/${id}`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get task')
  },

  // Create task
  async createTask(data: TaskFormData): Promise<Task> {
    const response = await apiClient.post<Task>('/tasks', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to create task')
  },

  // Update task
  async updateTask(id: string, data: Partial<TaskFormData>): Promise<Task> {
    const response = await apiClient.put<Task>(`/tasks/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update task')
  },

  // Delete task
  async deleteTask(id: string): Promise<void> {
    const response = await apiClient.delete(`/tasks/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete task')
    }
  },

  // Get task assignments
  async getTaskAssignments(taskId: string): Promise<TaskAssignment[]> {
    const response = await apiClient.get<TaskAssignment[]>(`/tasks/${taskId}/assignments`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get task assignments')
  },

  // Assign user to task
  async assignUserToTask(taskId: string, userId: string, data: any): Promise<TaskAssignment> {
    const response = await apiClient.post<TaskAssignment>(`/tasks/${taskId}/assignments`, {
      user_id: userId,
      ...data
    })
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to assign user to task')
  },

  // Update task assignment
  async updateTaskAssignment(assignmentId: string, data: any): Promise<TaskAssignment> {
    const response = await apiClient.put<TaskAssignment>(`/assignments/${assignmentId}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update task assignment')
  },

  // Remove user from task
  async removeUserFromTask(assignmentId: string): Promise<void> {
    const response = await apiClient.delete(`/assignments/${assignmentId}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to remove user from task')
    }
  },

  // Get user tasks
  async getUserTasks(userId: string, filters: any = {}): Promise<PaginatedResponse<Task>> {
    const response = await apiClient.get<PaginatedResponse<Task>>(`/users/${userId}/assignments`, filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get user tasks')
  },

  // Get user task stats
  async getUserTaskStats(userId: string): Promise<any> {
    const response = await apiClient.get(`/users/${userId}/assignments/stats`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get user task stats')
  },
}
