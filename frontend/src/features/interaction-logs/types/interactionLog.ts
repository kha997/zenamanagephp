/**
 * TypeScript types cho Interaction Logs Module
 * Khớp với JSend response format từ Laravel backend
 */

// Base types
export type InteractionLogType = 'call' | 'email' | 'meeting' | 'note' | 'feedback'
export type InteractionLogVisibility = 'internal' | 'client'

// Main InteractionLog interface khớp với backend resource
export interface InteractionLog {
  id: string
  ulid: string
  project_id: string
  linked_task_id?: string
  type: InteractionLogType
  type_label: string
  description: string
  tag_path: string
  visibility: InteractionLogVisibility
  client_approved: boolean
  is_visible_to_client: boolean
  created_by: string
  created_at: string
  updated_at: string
  
  // Relationships
  project?: {
    id: string
    name: string
  }
  linked_task?: {
    id: string
    name: string
  }
  creator?: {
    id: string
    name: string
    email: string
  }
}

// Form types
export interface CreateInteractionLogForm {
  project_id: string
  linked_task_id?: string
  type: InteractionLogType
  description: string
  tag_path: string
  visibility: InteractionLogVisibility
  client_approved?: boolean
}

export interface UpdateInteractionLogForm {
  type?: InteractionLogType
  description?: string
  tag_path?: string
  visibility?: InteractionLogVisibility
  client_approved?: boolean
}

// Filter types
export interface InteractionLogFilters {
  search?: string
  type?: InteractionLogType
  visibility?: InteractionLogVisibility
  project_id?: string
  linked_task_id?: string
  tag_path?: string
  client_approved?: boolean
  created_by?: string
  date_range?: {
    start: string
    end: string
  }
}

// Stats types
export interface InteractionLogStats {
  total: number
  by_type: Record<InteractionLogType, number>
  by_visibility: Record<InteractionLogVisibility, number>
  client_approved_count: number
  pending_approval_count: number
}

// JSend API response types
export interface JSendResponse<T = any> {
  status: 'success' | 'error' | 'fail'
  data?: T
  message?: string
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
  }
  links?: {
    first: string
    last: string
    prev?: string
    next?: string
  }
}

export interface InteractionLogListResponse extends JSendResponse {
  data: InteractionLog[]
}

export interface InteractionLogDetailResponse extends JSendResponse {
  data: InteractionLog
}

export interface InteractionLogStatsResponse extends JSendResponse {
  data: InteractionLogStats
}

// Pagination state
export interface PaginationState {
  page: number
  per_page: number
  total: number
  last_page: number
}

// Loading state
export interface LoadingState {
  isLoading: boolean
  error?: string | null
}

// Selection state for bulk operations
export interface SelectionState {
  selectedIds: string[]
  isAllSelected: boolean
  isIndeterminate: boolean
}