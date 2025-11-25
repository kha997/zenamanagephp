import { apiClient } from './client'
import { 
  WorkTemplate, 
  CreateTemplateForm, 
  UpdateTemplateForm,
  ApplyTemplateForm,
  TemplatePreview,
  ApiResponse 
} from '../types'

/**
 * Templates API service
 */
export class TemplatesService {
  /**
   * Lấy danh sách templates
   */
  static async getTemplates(params?: {
    page?: number
    per_page?: number
    category?: string
    search?: string
  }): Promise<ApiResponse<WorkTemplate[]>> {
    return await apiClient.get('/work-templates', { params })
  }

  /**
   * Lấy chi tiết template
   */
  static async getTemplate(id: string): Promise<WorkTemplate> {
    const response = await apiClient.get<WorkTemplate>(`/work-templates/${id}`)
    return response.data!
  }

  /**
   * Tạo template mới
   */
  static async createTemplate(data: CreateTemplateForm): Promise<WorkTemplate> {
    const response = await apiClient.post<WorkTemplate>('/work-templates', data)
    return response.data!
  }

  /**
   * Cập nhật template
   */
  static async updateTemplate(
    id: string, 
    data: UpdateTemplateForm
  ): Promise<WorkTemplate> {
    const response = await apiClient.put<WorkTemplate>(`/work-templates/${id}`, data)
    return response.data!
  }

  /**
   * Xóa template
   */
  static async deleteTemplate(id: string): Promise<void> {
    await apiClient.delete(`/work-templates/${id}`)
  }

  /**
   * Preview template trước khi apply
   */
  static async previewTemplate(
    id: string, 
    data: ApplyTemplateForm
  ): Promise<TemplatePreview> {
    const response = await apiClient.post<TemplatePreview>(
      `/work-templates/${id}/preview`, 
      data
    )
    return response.data!
  }

  /**
   * Apply template vào project
   */
  static async applyTemplate(
    id: string, 
    data: ApplyTemplateForm
  ): Promise<{ tasks_created: number; message: string }> {
    const response = await apiClient.post<{ tasks_created: number; message: string }>(
      `/work-templates/${id}/apply`, 
      data
    )
    return response.data!
  }

  /**
   * Duplicate template
   */
  static async duplicateTemplate(id: string, name: string): Promise<WorkTemplate> {
    const response = await apiClient.post<WorkTemplate>(
      `/work-templates/${id}/duplicate`, 
      { name }
    )
    return response.data!
  }

  /**
   * Lấy template versions
   */
  static async getTemplateVersions(id: string): Promise<any[]> {
    const response = await apiClient.get<any[]>(`/work-templates/${id}/versions`)
    return response.data!
  }

  /**
   * Tìm kiếm templates
   */
  static async searchTemplates(query: string): Promise<WorkTemplate[]> {
    const response = await apiClient.get<WorkTemplate[]>('/search/templates', {
      params: { q: query }
    })
    return response.data!
  }
}