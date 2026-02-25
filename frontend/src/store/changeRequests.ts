import { create } from 'zustand'
import { devtools } from 'zustand/middleware'
import type {
  ChangeRequest,
  CreateChangeRequestForm,
  UpdateChangeRequestForm,
  ChangeRequestDecision,
  ChangeRequestFilters,
  ChangeRequestStats,
  PaginationState,
  LoadingState,
  ApiResponse
} from '../lib/types'
import api from '../lib/api/client'

interface ChangeRequestsState {
  // State
  changeRequests: ChangeRequest[]
  currentChangeRequest: ChangeRequest | null
  stats: ChangeRequestStats | null
  loading: LoadingState
  pagination: PaginationState
  filters: ChangeRequestFilters
  
  // Actions
  fetchChangeRequests: (projectId?: string) => Promise<void>
  fetchChangeRequest: (id: string) => Promise<void>
  createChangeRequest: (projectId: string, data: CreateChangeRequestForm) => Promise<ChangeRequest>
  updateChangeRequest: (id: string, data: UpdateChangeRequestForm) => Promise<ChangeRequest>
  deleteChangeRequest: (id: string) => Promise<void>
  submitChangeRequest: (id: string) => Promise<ChangeRequest>
  decideChangeRequest: (id: string, decision: ChangeRequestDecision) => Promise<ChangeRequest>
  fetchStats: (projectId?: string) => Promise<void>
  fetchPendingApproval: () => Promise<void>
  setFilters: (filters: Partial<ChangeRequestFilters>) => void
  clearFilters: () => void
  clearCurrentChangeRequest: () => void
  setPage: (page: number) => void
}

export const useChangeRequestsStore = create<ChangeRequestsState>()(devtools(
  (set, get) => ({
    // Initial state
    changeRequests: [],
    currentChangeRequest: null,
    stats: null,
    loading: { isLoading: false, error: null },
    pagination: { page: 1, pageSize: 15, total: 0, totalPages: 0 },
    filters: {},
    
    // Actions
    fetchChangeRequests: async (projectId?: string) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const { filters, pagination } = get()
        const params = new URLSearchParams({
          page: pagination.page.toString(),
          per_page: pagination.pageSize.toString(),
          ...filters,
          ...(projectId && { project_id: projectId })
        })
        
        const endpoint = projectId 
          ? `/projects/${projectId}/change-requests?${params}`
          : `/change-requests?${params}`
          
        const response: ApiResponse<{
          change_requests: ChangeRequest[]
          pagination: PaginationState
        }> = await api.get(endpoint)
        
        if (response.status === 'success' && response.data) {
          set({
            changeRequests: response.data.change_requests,
            pagination: response.data.pagination,
            loading: { isLoading: false, error: null }
          })
        }
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể tải danh sách change requests'
          }
        })
      }
    },
    
    fetchChangeRequest: async (id: string) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const response: ApiResponse<ChangeRequest> = await api.get(`/change-requests/${id}`)
        
        if (response.status === 'success' && response.data) {
          set({
            currentChangeRequest: response.data,
            loading: { isLoading: false, error: null }
          })
        }
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể tải thông tin change request'
          }
        })
      }
    },
    
    createChangeRequest: async (projectId: string, data: CreateChangeRequestForm) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const response: ApiResponse<ChangeRequest> = await api.post(
          `/projects/${projectId}/change-requests`,
          data
        )
        
        if (response.status === 'success' && response.data) {
          const newCR = response.data
          set(state => ({
            changeRequests: [newCR, ...state.changeRequests],
            currentChangeRequest: newCR,
            loading: { isLoading: false, error: null }
          }))
          return newCR
        }
        throw new Error('Không thể tạo change request')
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể tạo change request'
          }
        })
        throw error
      }
    },
    
    updateChangeRequest: async (id: string, data: UpdateChangeRequestForm) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const response: ApiResponse<ChangeRequest> = await api.put(
          `/change-requests/${id}`,
          data
        )
        
        if (response.status === 'success' && response.data) {
          const updatedCR = response.data
          set(state => ({
            changeRequests: state.changeRequests.map(cr => 
              cr.id === id ? updatedCR : cr
            ),
            currentChangeRequest: state.currentChangeRequest?.id === id 
              ? updatedCR 
              : state.currentChangeRequest,
            loading: { isLoading: false, error: null }
          }))
          return updatedCR
        }
        throw new Error('Không thể cập nhật change request')
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể cập nhật change request'
          }
        })
        throw error
      }
    },
    
    deleteChangeRequest: async (id: string) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        await api.delete(`/change-requests/${id}`)
        
        set(state => ({
          changeRequests: state.changeRequests.filter(cr => cr.id !== id),
          currentChangeRequest: state.currentChangeRequest?.id === id 
            ? null 
            : state.currentChangeRequest,
          loading: { isLoading: false, error: null }
        }))
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể xóa change request'
          }
        })
        throw error
      }
    },
    
    submitChangeRequest: async (id: string) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const response: ApiResponse<ChangeRequest> = await api.post(
          `/change-requests/${id}/submit`
        )
        
        if (response.status === 'success' && response.data) {
          const updatedCR = response.data
          set(state => ({
            changeRequests: state.changeRequests.map(cr => 
              cr.id === id ? updatedCR : cr
            ),
            currentChangeRequest: state.currentChangeRequest?.id === id 
              ? updatedCR 
              : state.currentChangeRequest,
            loading: { isLoading: false, error: null }
          }))
          return updatedCR
        }
        throw new Error('Không thể submit change request')
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể submit change request'
          }
        })
        throw error
      }
    },
    
    decideChangeRequest: async (id: string, decision: ChangeRequestDecision) => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const endpoint = decision.decision === 'approve' 
          ? `/change-requests/${id}/approve`
          : `/change-requests/${id}/reject`
          
        const response: ApiResponse<ChangeRequest> = await api.post(endpoint, {
          decision_note: decision.decision_note
        })
        
        if (response.status === 'success' && response.data) {
          const updatedCR = response.data
          set(state => ({
            changeRequests: state.changeRequests.map(cr => 
              cr.id === id ? updatedCR : cr
            ),
            currentChangeRequest: state.currentChangeRequest?.id === id 
              ? updatedCR 
              : state.currentChangeRequest,
            loading: { isLoading: false, error: null }
          }))
          return updatedCR
        }
        throw new Error(`Không thể ${decision.decision === 'approve' ? 'approve' : 'reject'} change request`)
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || `Không thể ${decision.decision === 'approve' ? 'approve' : 'reject'} change request`
          }
        })
        throw error
      }
    },
    
    fetchStats: async (projectId?: string) => {
      try {
        const endpoint = projectId 
          ? `/change-requests/statistics/${projectId}`
          : '/change-requests/statistics'
          
        const response: ApiResponse<ChangeRequestStats> = await api.get(endpoint)
        
        if (response.status === 'success' && response.data) {
          set({ stats: response.data })
        }
      } catch (error: any) {
        console.error('Không thể tải thống kê change requests:', error)
      }
    },
    
    fetchPendingApproval: async () => {
      set({ loading: { isLoading: true, error: null } })
      
      try {
        const response: ApiResponse<ChangeRequest[]> = await api.get('/change-requests/pending-approval')
        
        if (response.status === 'success' && response.data) {
          set({
            changeRequests: response.data,
            loading: { isLoading: false, error: null }
          })
        }
      } catch (error: any) {
        set({
          loading: {
            isLoading: false,
            error: error.message || 'Không thể tải danh sách pending approval'
          }
        })
      }
    },
    
    setFilters: (newFilters: Partial<ChangeRequestFilters>) => {
      set(state => ({
        filters: { ...state.filters, ...newFilters },
        pagination: { ...state.pagination, page: 1 }
      }))
    },
    
    clearFilters: () => {
      set({
        filters: {},
        pagination: { page: 1, pageSize: 15, total: 0, totalPages: 0 }
      })
    },
    
    clearCurrentChangeRequest: () => {
      set({ currentChangeRequest: null })
    },
    
    setPage: (page: number) => {
      set(state => ({
        pagination: { ...state.pagination, page }
      }))
    }
  }),
  { name: 'change-requests-store' }
))
