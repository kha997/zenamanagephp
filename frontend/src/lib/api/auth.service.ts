import { apiClient } from './client'
import { AuthResponse, LoginCredentials, User } from '../types'
import { API_ENDPOINTS } from '../constants'

/**
 * Authentication API service
 */
export class AuthService {
  /**
   * Đăng nhập user
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>(
      API_ENDPOINTS.AUTH.LOGIN,
      credentials
    )
    return response.data!
  }

  /**
   * Đăng ký user mới
   */
  static async register(userData: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>(
      '/auth/register',
      userData
    )
    return response.data!
  }

  /**
   * Lấy thông tin user hiện tại
   */
  static async getProfile(): Promise<User> {
    const response = await apiClient.get<User>('/auth/me')
    return response.data!
  }

  /**
   * Đăng xuất
   */
  static async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  }

  /**
   * Refresh JWT token
   */
  static async refreshToken(): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/refresh')
    return response.data!
  }

  /**
   * Cập nhật profile user
   */
  static async updateProfile(userData: Partial<User>): Promise<User> {
    const response = await apiClient.put<User>('/auth/profile', userData)
    return response.data!
  }

  /**
   * Kiểm tra quyền của user
   */
  static async checkPermission(permission: string): Promise<boolean> {
    const response = await apiClient.get<{ has_permission: boolean }>(
      `/auth/check-permission?permission=${permission}`
    )
    return response.data!.has_permission
  }

  /**
   * Test JWT authentication
   */
  static async testJWT(): Promise<any> {
    const response = await apiClient.get('/auth/jwt-test')
    return response.data
  }
}