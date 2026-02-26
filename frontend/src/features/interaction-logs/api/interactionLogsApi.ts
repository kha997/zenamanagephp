import api from '@/lib/api'
import type {
  CreateInteractionLogForm,
  InteractionLogDetailResponse,
  InteractionLogFilters,
  InteractionLogListResponse,
  InteractionLogStatsResponse,
  JSendResponse,
  UpdateInteractionLogForm,
} from '../types/interactionLog'

const ENDPOINTS = {
  LIST: '/interaction-logs',
  DETAIL: (id: string) => `/interaction-logs/${id}`,
  CREATE: '/interaction-logs',
  UPDATE: (id: string) => `/interaction-logs/${id}`,
  DELETE: (id: string) => `/interaction-logs/${id}`,
  APPROVE: (id: string) => `/interaction-logs/${id}/approve-for-client`,
  BY_TAG_PATH: '/interaction-logs/by-tag-path',
  PROJECT_LIST: (projectId: string) => `/projects/${projectId}/interaction-logs`,
  PROJECT_STATS: (projectId: string) => `/projects/${projectId}/interaction-logs/stats`,
  PROJECT_AUTOCOMPLETE: (projectId: string) => `/projects/${projectId}/interaction-logs/autocomplete-tag-path`,
}

export class InteractionLogsApi {
  static async list(
    filters: InteractionLogFilters & { page?: number; per_page?: number } = {}
  ): Promise<InteractionLogListResponse> {
    return (await api.get<InteractionLogListResponse>(ENDPOINTS.LIST, filters)) as unknown as InteractionLogListResponse
  }

  static async detail(id: string): Promise<InteractionLogDetailResponse> {
    return (await api.get<InteractionLogDetailResponse>(ENDPOINTS.DETAIL(id))) as unknown as InteractionLogDetailResponse
  }

  static async getById(id: string): Promise<InteractionLogDetailResponse> {
    return this.detail(id)
  }

  static async create(data: CreateInteractionLogForm): Promise<InteractionLogDetailResponse> {
    return (await api.post<InteractionLogDetailResponse>(ENDPOINTS.CREATE, data)) as unknown as InteractionLogDetailResponse
  }

  static async update(id: string, data: UpdateInteractionLogForm): Promise<InteractionLogDetailResponse> {
    return (await api.put<InteractionLogDetailResponse>(ENDPOINTS.UPDATE(id), data)) as unknown as InteractionLogDetailResponse
  }

  static async delete(id: string): Promise<JSendResponse> {
    return (await api.delete<JSendResponse>(ENDPOINTS.DELETE(id))) as unknown as JSendResponse
  }

  static async approveForClient(id: string): Promise<InteractionLogDetailResponse> {
    return (await api.post<InteractionLogDetailResponse>(ENDPOINTS.APPROVE(id))) as unknown as InteractionLogDetailResponse
  }

  static async approve(id: string): Promise<InteractionLogDetailResponse> {
    return this.approveForClient(id)
  }

  static async getByTagPath(
    tagPath: string,
    filters: InteractionLogFilters = {}
  ): Promise<InteractionLogListResponse> {
    return (await api.get<InteractionLogListResponse>(ENDPOINTS.BY_TAG_PATH, {
      tag_path: tagPath,
      ...filters,
    })) as unknown as InteractionLogListResponse
  }

  static async listByProject(
    projectId: string,
    filters: InteractionLogFilters & { page?: number; per_page?: number } = {}
  ): Promise<InteractionLogListResponse> {
    return (await api.get<InteractionLogListResponse>(ENDPOINTS.PROJECT_LIST(projectId), filters)) as unknown as InteractionLogListResponse
  }

  static async getProjectStats(projectId: string): Promise<InteractionLogStatsResponse> {
    return (await api.get<InteractionLogStatsResponse>(ENDPOINTS.PROJECT_STATS(projectId))) as unknown as InteractionLogStatsResponse
  }

  static async autocompleteTagPath(projectId: string, query: string): Promise<JSendResponse<string[]>> {
    return (await api.get<JSendResponse<string[]>>(ENDPOINTS.PROJECT_AUTOCOMPLETE(projectId), { q: query })) as unknown as JSendResponse<string[]>
  }
}

export default InteractionLogsApi
