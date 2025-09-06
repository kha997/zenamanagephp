import { create } from 'zustand'
import { Task, CreateTaskForm, FilterState } from '../lib/types'
import { apiClient } from '../lib/api/client'
import { API_ENDPOINTS } from '../lib/constants'

/**
 * Interface cho Tasks Store State
 */
interface TasksState {
  // State
  tasks: Task[]
  currentTask: Task | null
  isLoading: boolean
  error: string | null
  filters: FilterState
  
  // Actions
  fetchTasks: (projectId: string, filters?: FilterState) => Promise<void>
  fetchTask: (projectId: string, taskId: string) => Promise<void>
  createTask: (projectId: string, data: CreateTaskForm) => Promise<Task>
  updateTask: (projectId: string, taskId: string, data: Partial<Task>) => Promise<void>
  deleteTask: (projectId: string, taskId: string) => Promise<void>
  updateTaskStatus: (projectId: string, taskId: string, status: string) => Promise<void>
  assignTask: (projectId: string, taskId: string, userIds: string[]) => Promise<void>
  setCurrentTask: (task: Task | null) => void
  setFilters: (filters: FilterState) => void
  clearError: () => void
}

/**
 * Zustand store cho tasks management
 */
export const useTasksStore = create<TasksState>((set, get) => ({
  // Initial state
  tasks: [],
  currentTask: null,
  isLoading: false,
  error: null,
  filters: {},

  /**
   * Lấy danh sách tasks của một project
   */
  fetchTasks: async (projectId: string, filters = {}) => {
    set({ isLoading: true, error: null })
    
    try {
      const params = { ...filters }
      
      const response = await apiClient.get<Task[]>(
        API_ENDPOINTS.TASKS.LIST(projectId),
        { params }
      )
      
      if (response.status === 'success' && response.data) {
        set({
          tasks: response.data,
          filters,
          isLoading: false,
        })
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tải danh sách công việc',
      })
    }
  },

  /**
   * Lấy chi tiết một task
   */
  fetchTask: async (projectId: string, taskId: string) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.get<Task>(
        API_ENDPOINTS.TASKS.DETAIL(projectId, taskId)
      )
      
      if (response.status === 'success' && response.data) {
        set({
          currentTask: response.data,
          isLoading: false,
        })
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tải thông tin công việc',
      })
    }
  },

  /**
   * Tạo task mới
   */
  createTask: async (projectId: string, data: CreateTaskForm) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.post<Task>(
        API_ENDPOINTS.TASKS.CREATE(projectId),
        data
      )
      
      if (response.status === 'success' && response.data) {
        const newTask = response.data
        
        set((state) => ({
          tasks: [...state.tasks, newTask],
          isLoading: false,
        }))
        
        return newTask
      }
      
      throw new Error('Không thể tạo công việc')
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể tạo công việc',
      })
      throw error
    }
  },

  /**
   * Cập nhật task
   */
  updateTask: async (projectId: string, taskId: string, data: Partial<Task>) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.put<Task>(
        API_ENDPOINTS.TASKS.UPDATE(projectId, taskId),
        data
      )
      
      if (response.status === 'success' && response.data) {
        const updatedTask = response.data
        
        set((state) => ({
          tasks: state.tasks.map(t => 
            t.id === taskId ? updatedTask : t
          ),
          currentTask: state.currentTask?.id === taskId 
            ? updatedTask 
            : state.currentTask,
          isLoading: false,
        }))
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể cập nhật công việc',
      })
      throw error
    }
  },

  /**
   * Xóa task
   */
  deleteTask: async (projectId: string, taskId: string) => {
    set({ isLoading: true, error: null })
    
    try {
      await apiClient.delete(API_ENDPOINTS.TASKS.DELETE(projectId, taskId))
      
      set((state) => ({
        tasks: state.tasks.filter(t => t.id !== taskId),
        currentTask: state.currentTask?.id === taskId 
          ? null 
          : state.currentTask,
        isLoading: false,
      }))
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể xóa công việc',
      })
      throw error
    }
  },

  /**
   * Cập nhật trạng thái task
   */
  updateTaskStatus: async (projectId: string, taskId: string, status: string) => {
    try {
      await get().updateTask(projectId, taskId, { status })
    } catch (error) {
      throw error
    }
  },

  /**
   * Assign task cho users
   */
  assignTask: async (projectId: string, taskId: string, userIds: string[]) => {
    set({ isLoading: true, error: null })
    
    try {
      const response = await apiClient.post(
        `${API_ENDPOINTS.TASKS.DETAIL(projectId, taskId)}/assign`,
        { user_ids: userIds }
      )
      
      if (response.status === 'success') {
        // Refresh task data
        await get().fetchTask(projectId, taskId)
      }
    } catch (error: any) {
      set({
        isLoading: false,
        error: error.message || 'Không thể phân công công việc',
      })
      throw error
    }
  },

  /**
   * Set current task
   */
  setCurrentTask: (task: Task | null) => {
    set({ currentTask: task })
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
  updateTaskDependencies: async (taskId: string, dependencies: string[]) => {
    try {
      const response = await fetch(`/api/v1/tasks/${taskId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify({ dependencies }),
      });

      if (!response.ok) {
        throw new Error('Failed to update task dependencies');
      }

      const result = await response.json();
      
      if (result.status === 'success') {
        set((state) => ({
          tasks: state.tasks.map((task) =>
            task.id === taskId ? { ...task, dependencies } : task
          ),
        }));
      }
    } catch (error) {
      console.error('Error updating task dependencies:', error);
      set({ error: 'Không thể cập nhật phụ thuộc công việc' });
    }
  },
}))