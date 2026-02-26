export type {
  ChangeRequest,
  ChangeRequestStatus,
  ChangeRequestFilters,
  CreateChangeRequestData,
  UpdateChangeRequestData,
  ChangeRequestDecision,
  ChangeRequestStats,
} from '@/lib/types'

export interface ChangeRequestsResponse {
  data: import('@/lib/types').ChangeRequest[]
  pagination?: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export interface ChangeRequestResponse {
  data: import('@/lib/types').ChangeRequest
}
