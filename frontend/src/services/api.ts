import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'

// API Configuration
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

// Create axios instance
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  },
  withCredentials: true
});

// Add token interceptor
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    return response
  },
  (error) => {
    if (error.response?.status === 401) {
      // Unauthorized - clear token and redirect to login
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

// Generic API response type
export interface ApiResponse<T = any> {
  data: T
  message?: string
  status: 'success' | 'error'
  errors?: Record<string, string[]>
}

// Pagination response type
export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

// API Error type
export class ApiError extends Error {
  public status: number
  public errors?: Record<string, string[]>

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

// Generic API methods
export const api = {
  // GET request
  get: async <T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.get(url, config)
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Request failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },

  // POST request
  post: async <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.post(url, data, config)
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Request failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },

  // PUT request
  put: async <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.put(url, data, config)
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Request failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },

  // PATCH request
  patch: async <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.patch(url, data, config)
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Request failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },

  // DELETE request
  delete: async <T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.delete(url, config)
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Request failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },

  // Upload file
  upload: async <T = any>(url: string, formData: FormData, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    try {
      const response = await apiClient.post(url, formData, {
        ...config,
        headers: {
          'Content-Type': 'multipart/form-data',
          ...config?.headers,
        },
      })
      return response.data
    } catch (error: any) {
      throw new ApiError(
        error.response?.data?.message || error.message || 'Upload failed',
        error.response?.status || 500,
        error.response?.data?.errors
      )
    }
  },
}

export default api
