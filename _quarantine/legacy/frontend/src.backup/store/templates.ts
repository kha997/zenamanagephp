import { create } from 'zustand'
import { devtools } from 'zustand/middleware'
import apiClient from '../lib/api'
import type { 
  WorkTemplate, 
  CreateWorkTemplateForm, 
  ApplyTemplateForm,
  TemplatePreview,
  ApiResponse,
  PaginationState,
  LoadingState 
} from '../lib/types'

interface TemplatesState {
  // State
  templates: WorkTemplate[]
  currentTemplate: WorkTemplate | null
  templatePreview: TemplatePreview | null
  loading: LoadingState
  pagination: PaginationState
  filters: {
    search?: string
    category?: string
    is_active?: boolean
  }

  // Actions
  fetchTemplates: (params?: {
    page?: number
    per_page?: number
    search?: string
    category?: string
    is_active?: boolean
  }) => Promise<void>
  fetchTemplate: (id: string) => Promise<void>
  createTemplate: (data: CreateWorkTemplateForm) => Promise<WorkTemplate>
  updateTemplate: (id: string, data: Partial<CreateWorkTemplateForm>) => Promise<WorkTemplate>
  deleteTemplate: (id: string) => Promise<void>
  duplicateTemplate: (id: string, name: string) => Promise<WorkTemplate>
  previewTemplateApplication: (id: string, data: ApplyTemplateForm) => Promise<TemplatePreview>
  applyTemplate: (id: string, data: ApplyTemplateForm) => Promise<void>
  getCategories: () => Promise<{ value: string; label: string }[]>
  getConditionalTags: () => Promise<string[]>
  setFilters: (filters: Partial<TemplatesState['filters']>) => void
  clearCurrentTemplate: () => void
  clearPreview: () => void
}

export const useTemplatesStore = create<TemplatesState>()(devtools(
  (set, get) => ({
    // Initial state
    templates: [],
    currentTemplate: null,
    templatePreview: null,
    loading: { isLoading: false, error: null },
    pagination: { page: 1, pageSize: 15, total: 0, totalPages: 0 },
    filters: {},

    // Actions
    fetchTemplates: async (params = {}) => {
      set({ loading: { isLoading: true, error: null } })
      try {
        const response = await apiClient.get<ApiResponse<{
          templates: WorkTemplate[]
          pagination: {
            current_page: number
            last_page: number
            per_page: number
            total: number
          }
        }>>('/work-templates', { params })

        if (response.data.status === 'success' && response.data.data) {
          const { templates, pagination } = response.data.data
          set({
            templates,
            pagination: {
              page: pagination.current_page,
              pageSize: pagination.per_page,
              total: pagination.total,
              totalPages: pagination.last_page
            },
            loading: { isLoading: false, error: null }
          })
        }
      } catch (error: any) {
        set({ 
          loading: { 
            isLoading: false, 
            error: error.response?.data?.message || 'Không thể tải danh sách templates' 
          } 
        })
      }
    },

    fetchTemplate: async (id: string) => {
      set({ loading: { isLoading: true, error: null } })
      try {
        const response = await apiClient.get<ApiResponse<{ template: WorkTemplate }>>(`/work-templates/${id}`)
        
        if (response.data.status === 'success' && response.data.data) {
          set({ 
            currentTemplate: response.data.data.template,
            loading: { isLoading: false, error: null }
          })
        }
      } catch (error: any) {
        set({ 
          loading: { 
            isLoading: false, 
            error: error.response?.data?.message || 'Không thể tải template' 
          } 
        })
      }
    },

    createTemplate: async (data: CreateWorkTemplateForm) => {
      const response = await apiClient.post<ApiResponse<{ template: WorkTemplate }>>('/work-templates', data)
      
      if (response.data.status === 'success' && response.data.data) {
        const newTemplate = response.data.data.template
        set(state => ({ 
          templates: [newTemplate, ...state.templates] 
        }))
        return newTemplate
      }
      throw new Error(response.data.message || 'Không thể tạo template')
    },

    updateTemplate: async (id: string, data: Partial<CreateWorkTemplateForm>) => {
      const response = await apiClient.put<ApiResponse<{ template: WorkTemplate }>>(`/work-templates/${id}`, data)
      
      if (response.data.status === 'success' && response.data.data) {
        const updatedTemplate = response.data.data.template
        set(state => ({
          templates: state.templates.map(t => t.id === id ? updatedTemplate : t),
          currentTemplate: state.currentTemplate?.id === id ? updatedTemplate : state.currentTemplate
        }))
        return updatedTemplate
      }
      throw new Error(response.data.message || 'Không thể cập nhật template')
    },

    deleteTemplate: async (id: string) => {
      await apiClient.delete(`/work-templates/${id}`)
      set(state => ({
        templates: state.templates.filter(t => t.id !== id),
        currentTemplate: state.currentTemplate?.id === id ? null : state.currentTemplate
      }))
    },

    duplicateTemplate: async (id: string, name: string) => {
      const response = await apiClient.post<ApiResponse<{ template: WorkTemplate }>>(`/work-templates/${id}/duplicate`, { name })
      
      if (response.data.status === 'success' && response.data.data) {
        const newTemplate = response.data.data.template
        set(state => ({ 
          templates: [newTemplate, ...state.templates] 
        }))
        return newTemplate
      }
      throw new Error(response.data.message || 'Không thể nhân bản template')
    },

    previewTemplateApplication: async (id: string, data: ApplyTemplateForm) => {
      const response = await apiClient.post<ApiResponse<{ preview: TemplatePreview }>>(
        `/work-templates/${id}/apply`, 
        { ...data, preview_only: true }
      )
      
      if (response.data.status === 'success' && response.data.data) {
        const preview = response.data.data.preview
        set({ templatePreview: preview })
        return preview
      }
      throw new Error(response.data.message || 'Không thể tạo preview')
    },

    applyTemplate: async (id: string, data: ApplyTemplateForm) => {
      await apiClient.post(`/work-templates/${id}/apply`, { ...data, preview_only: false })
    },

    getCategories: async () => {
      const response = await apiClient.get<ApiResponse<{ categories: { value: string; label: string }[] }>>('/work-templates/meta/categories')
      
      if (response.data.status === 'success' && response.data.data) {
        return response.data.data.categories
      }
      return []
    },

    getConditionalTags: async () => {
      const response = await apiClient.get<ApiResponse<{ tags: string[] }>>('/work-templates/meta/conditional-tags')
      
      if (response.data.status === 'success' && response.data.data) {
        return response.data.data.tags
      }
      return []
    },

    setFilters: (filters) => {
      set(state => ({ filters: { ...state.filters, ...filters } }))
    },

    clearCurrentTemplate: () => {
      set({ currentTemplate: null })
    },

    clearPreview: () => {
      set({ templatePreview: null })
    }
  }),
  { name: 'templates-store' }
))