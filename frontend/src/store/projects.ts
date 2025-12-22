import { create } from 'zustand'
import { Project, CreateProjectForm, FilterState, PaginationState } from '../lib/types'
import { apiClient } from '../lib/api/client'
import { API_ENDPOINTS } from '../lib/constants'

/**
 * Interface cho Projects Store State
 */
interface ProjectsState {
  // State
  projects: Project[]
  currentProject: Project | null
  isLoading: boolean
  error: string | null
  pagination: PaginationState
  filters: FilterState
  
  // Actions
  fetchProjects: (page?: number, filters?: FilterState) => Promise<void>
  fetchProject: (id: string) => Promise<void>
  createProject: (data: CreateProjectForm) => Promise<Project>
  updateProject: (id: string, data: Partial<Project>) => Promise<void>
  deleteProject: (id: string) => Promise<void>
  setCurrentProject: (project: Project | null) => void
  setFilters: (filters: FilterState) => void
  clearError: () => void
}

/**
 * Zustand store cho projects management
 */
export const useProjectsStore = create<ProjectsState>((set, get) => ({
  // Initial state
  projects: [],
  currentProject: null,
  isLoading: false,
  error: null,
  pagination: {
    page: 1,
    pageSize: 20,
    total: 0,
    totalPages: 0,
  },
  filters: {},

  /**
   * Lấy danh sách projects với pagination và filters
   */
  fetchProjects: async (page = 1, filters = {}) => {
    set({ isLoading: true, error: null })
    
    try {
      const params = {
        page,
        per_page: get().pagination.pageSize,
        ...filters,
      }
      
      const response = await apiClient.get(API_ENDPOINTS.PROJECTS.LIST, { params })
      const isSuccess = response?.status === 'success' || response?.success === true
      const payload = response?.data ?? response
      
      if (isSuccess && payload?.data) {
        set({
          projects: payload.data,
          pagination: {
            page: payload.meta?.current_page ?? page,
            pageSize: payload.meta?.per_page ?? get().pagination.pageSize,
            total: payload.meta?.total ?? payload.data.length,
            totalPages: payload.meta?.last_page ?? 1,
          },
          filters,
          isLoading: false,
        })
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tải danh sách dự án',
      })
    }
  },

  /**
   * Lấy chi tiết một project
   */
  fetchProject: async (id: string) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.get<Project>(
        API_ENDPOINTS.PROJECTS.DETAIL(id)
      )
      
      if ((response?.status === 'success' || response?.success === true) && response?.data) {
        set({
          currentProject: response.data,
          isLoading: false,
        })
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tải thông tin dự án',
      })
    }
  },

  /**
   * Tạo project mới
   */
  createProject: async (data: CreateProjectForm) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.post<Project>(
        API_ENDPOINTS.PROJECTS.CREATE,
        data
      )
      
      if ((response?.status === 'success' || response?.success === true) && response?.data) {
        const newProject = response.data
        
        set((state) => ({
          projects: [newProject, ...state.projects],
          isLoading: false,
        }))
        
        return newProject
      }
      
      throw new Error('Không thể tạo dự án')
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tạo dự án',
      })
      throw error
    }
  },

  /**
   * Cập nhật project
   */
  updateProject: async (id: string, data: Partial<Project>) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.put<Project>(
        API_ENDPOINTS.PROJECTS.UPDATE(id),
        data
      )
      
      if ((response?.status === 'success' || response?.success === true) && response?.data) {
        const updatedProject = response.data
        
        set((state) => ({
          projects: state.projects.map(p => 
            p.id === id ? updatedProject : p
          ),
          currentProject: state.currentProject?.id === id 
            ? updatedProject 
            : state.currentProject,
          isLoading: false,
        }))
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể cập nhật dự án',
      })
      throw error
    }
  },

  /**
   * Xóa project
   */
  deleteProject: async (id: string) => {
    set({ isLoading: true, error: null })
    
    try {
      await apiClient.delete(API_ENDPOINTS.PROJECTS.DELETE(id))
      
      set((state) => ({
        projects: state.projects.filter(p => p.id !== id),
        currentProject: state.currentProject?.id === id 
          ? null 
          : state.currentProject,
        isLoading: false,
      }))
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể xóa dự án',
      })
      throw error
    }
  },

  /**
   * Set current project
   */
  setCurrentProject: (project: Project | null) => {
    set({ currentProject: project })
  },

  /**
   * Set filters
   */
  setFilters: (filters: FilterState) => {
    set({ filters })
  },

  /**
   * Clear error
   */
  clearError: () => {
    set({ error: null })
  },
}))