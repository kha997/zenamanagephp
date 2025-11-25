import { apiClient } from './client'
import { 
  ChangeRequest, 
  CreateChangeRequestForm, 
  UpdateChangeRequestForm,
  ChangeRequestDecision,
  ChangeRequestStats,
  ApiResponse 
} from '../types'

/**
 * Change Requests API service
 */
export class ChangeRequestsService {
  /**
   * Lấy danh sách change requests
   */
  static async getChangeRequests(params?: {
    page?: number
    per_page?: number
    project_id?: string
    status?: string
    priority?: string
  }): Promise<ApiResponse<ChangeRequest[]>> {
    return await apiClient.get('/change-requests', { params })
  }

  /**
   * Lấy change requests theo project
   */
  static async getChangeRequestsByProject(
    projectId: string,
    params?: { page?: number; per_page?: number }
  ): Promise<ApiResponse<ChangeRequest[]>> {
    return await apiClient.get(`/change-requests/project/${projectId}`, { params })
  }

  /**
   * Lấy chi tiết change request
   */
  static async getChangeRequest(id: string): Promise<ChangeRequest> {
    const response = await apiClient.get<ChangeRequest>(`/change-requests/${id}`)
    return response.data!
  }

  /**
   * Tạo change request mới
   */
  static async createChangeRequest(data: CreateChangeRequestForm): Promise<ChangeRequest> {
    const response = await apiClient.post<ChangeRequest>('/change-requests', data)
    return response.data!
  }

  /**
   * Cập nhật change request
   */
  static async updateChangeRequest(
    id: string, 
    data: UpdateChangeRequestForm
  ): Promise<ChangeRequest> {
    const response = await apiClient.put<ChangeRequest>(`/change-requests/${id}`, data)
    return response.data!
  }

  /**
   * Xóa change request
   */
  static async deleteChangeRequest(id: string): Promise<void> {
    await apiClient.delete(`/change-requests/${id}`)
  }

  /**
   * Submit change request để chờ phê duyệt
   */
  static async submitChangeRequest(id: string): Promise<ChangeRequest> {
    const response = await apiClient.post<ChangeRequest>(`/change-requests/${id}/submit`)
    return response.data!
  }

  /**
   * Phê duyệt change request
   */
  static async approveChangeRequest(
    id: string, 
    decision: ChangeRequestDecision
  ): Promise<ChangeRequest> {
    const response = await apiClient.post<ChangeRequest>(
      `/change-requests/${id}/approve`, 
      decision
    )
    return response.data!
  }

  /**
   * Từ chối change request
   */
  static async rejectChangeRequest(
    id: string, 
    decision: ChangeRequestDecision
  ): Promise<ChangeRequest> {
    const response = await apiClient.post<ChangeRequest>(
      `/change-requests/${id}/reject`, 
      decision
    )
    return response.data!
  }

  /**
   * Lấy thống kê change requests
   */
  static async getStatistics(projectId?: string): Promise<ChangeRequestStats> {
    const endpoint = projectId 
      ? `/change-requests/statistics/${projectId}`
      : '/change-requests/statistics'
    const response = await apiClient.get<ChangeRequestStats>(endpoint)
    return response.data!
  }

  /**
   * Lấy change requests đang chờ phê duyệt
   */
  static async getPendingApproval(): Promise<ChangeRequest[]> {
    const response = await apiClient.get<ChangeRequest[]>('/change-requests/pending-approval')
    return response.data!
  }
}