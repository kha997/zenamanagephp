import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'
import { ApiResponse, ApiError } from '../types/api'
import { getToken, removeToken } from '../utils/auth'

/**
 * API Client class để xử lý tất cả các request đến backend
 * Tự động thêm JWT token và xử lý lỗi
 */
class ApiClient {
  private instance: AxiosInstance

  constructor() {
    this.instance = axios.create({
      baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    })

    this.setupInterceptors()
  }

  /**
   * Thiết lập interceptors cho request và response
   */
  private setupInterceptors(): void {
    // Request interceptor - thêm JWT token
    this.instance.interceptors.request.use(
      (config) => {
        const token = getToken()
        if (token) {
          config.headers.Authorization = `Bearer ${token}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Response interceptor - xử lý lỗi chung
    this.instance.interceptors.response.use(
      (response: AxiosResponse<ApiResponse>) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Token hết hạn hoặc không hợp lệ
          removeToken()
          window.location.href = '/auth/login'
        }
        return Promise.reject(this.handleError(error))
      }
    )
  }

  /**
   * Xử lý và format lỗi từ API
   */
  private handleError(error: any): ApiError {
    if (error.response) {
      return {
        status: error.response.status,
        message: error.response.data?.message || 'Có lỗi xảy ra',
        data: error.response.data,
      }
    }
    
    if (error.request) {
      return {
        status: 0,
        message: 'Không thể kết nối đến server',
        data: null,
      }
    }
    
    return {
      status: 0,
      message: error.message || 'Lỗi không xác định',
      data: null,
    }
  }

  /**
   * GET request
   */
  async get<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.instance.get<ApiResponse<T>>(url, config)
    return response.data
  }

  /**
   * POST request
   */
  async post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.instance.post<ApiResponse<T>>(url, data, config)
    return response.data
  }

  async postBlob(url: string, data?: any, config?: AxiosRequestConfig): Promise<AxiosResponse<Blob>> {
    return this.instance.post<Blob>(url, data, {
      ...config,
      responseType: 'blob',
    })
  }

  /**
   * PUT request
   */
  async put<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.instance.put<ApiResponse<T>>(url, data, config)
    return response.data
  }

  /**
   * DELETE request
   */
  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.instance.delete<ApiResponse<T>>(url, config)
    return response.data
  }

  /**
   * PATCH request
   */
  async patch<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    const response = await this.instance.patch<ApiResponse<T>>(url, data, config)
    return response.data
  }
}

// Export singleton instance
export const apiClient = new ApiClient()
export default apiClient
