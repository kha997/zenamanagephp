/**
 * API layer cho Interaction Logs
 * Sử dụng Axios instance với JWT authentication
 */
import { api } from '@/lib/api'
import type {
  InteractionLog,
  CreateInteractionLogForm,
  UpdateInteractionLogForm,
  InteractionLogFilters,
  InteractionLogListResponse,
  InteractionLogDetailResponse,
  InteractionLogStatsResponse,
  JSendResponse
} from '../types/interactionLog'

// Base API endpoints
const ENDPOINTS = {
  LIST: '/api/v1/interaction-logs',
  DETAIL: (id: string) => `/api/v1/interaction-logs/${id}`,
  CREATE: '/api/v1/interaction-logs',
  UPDATE: (id: string) => `/api/v1/interaction-logs/${id}`,
  DELETE: (id: string) => `/api/v1/interaction-logs/${id}`,
  APPROVE: (id: string) => `/api/v1/interaction-logs/${id}/approve-for-client`,
  BY_TAG_PATH: '/api/v1/interaction-logs/by-tag-path',
  
  // Project-specific endpoints
  PROJECT_LIST: (projectId: string) => `/api/v1/projects/${projectId}/interaction-logs`,
  PROJECT_STATS: (projectId: string) => `/api/v1/projects/${projectId}/interaction-logs/stats`,
  PROJECT_AUTOCOMPLETE: (projectId: string) => `/api/v1/projects/${projectId}/interaction-logs/autocomplete-tag-path`
}

export class InteractionLogsApi {
  /**
   * Lấy danh sách interaction logs với phân trang và filter
   */
  static async list(filters: InteractionLogFilters & { page?: number; per_page?: number } = {}): Promise<InteractionLogListResponse> {
    const response = await api.get(ENDPOINTS.LIST, { params: filters })
    return response.data
  }

  /**
   * Lấy chi tiết một interaction log
   */
  static async detail(id: string): Promise<InteractionLogDetailResponse> {
    const response = await api.get(ENDPOINTS.DETAIL(id))
    return response.data
  }

  /**
   * Tạo interaction log mới
   */
  static async create(data: CreateInteractionLogForm): Promise<InteractionLogDetailResponse> {
    const response = await api.post(ENDPOINTS.CREATE, data)
    return response.data
  }

  /**
   * Cập nhật interaction log
   */
  static async update(id: string, data: UpdateInteractionLogForm): Promise<InteractionLogDetailResponse> {
    const response = await api.put(ENDPOINTS.UPDATE(id), data)
    return response.data
  }

  /**
   * Xóa interaction log
   */
  static async delete(id: string): Promise<JSendResponse> {
    const response = await api.delete(ENDPOINTS.DELETE(id))
    return response.data
  }

  /**
   * Duyệt interaction log cho client
   */
  static async approveForClient(id: string): Promise<InteractionLogDetailResponse> {
    const response = await api.post(ENDPOINTS.APPROVE(id))
    return response.data
  }

  /**
   * Lấy interaction logs theo tag path
   */
  static async getByTagPath(tagPath: string, filters: InteractionLogFilters = {}): Promise<InteractionLogListResponse> {
    const response = await api.get(ENDPOINTS.BY_TAG_PATH, {
      params: { tag_path: tagPath, ...filters }
    })
    return response.data
  }

  /**
   * Lấy danh sách interaction logs của một project
   */
  static async listByProject(
    projectId: string, 
    filters: InteractionLogFilters & { page?: number; per_page?: number } = {}
  ): Promise<InteractionLogListResponse> {
    const response = await api.get(ENDPOINTS.PROJECT_LIST(projectId), { params: filters })
    return response.data
  }

  /**
   * Lấy thống kê interaction logs của project
   */
  static async getProjectStats(projectId: string): Promise<InteractionLogStatsResponse> {
    const response = await api.get(ENDPOINTS.PROJECT_STATS(projectId))
    return response.data
  }

  /**
   * Autocomplete tag path cho project
   */
  static async autocompleteTagPath(projectId: string, query: string): Promise<JSendResponse<string[]>> {
    const response = await api.get(ENDPOINTS.PROJECT_AUTOCOMPLETE(projectId), {
      params: { q: query }
    })
    return response.data
  }
}

export default InteractionLogsApi