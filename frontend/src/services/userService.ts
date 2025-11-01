import apiClient from '../lib/api'
import { User, UserFormData, UserFilters, PaginatedResponse } from '../types'

export const userService = {
  // Get users list
  async getUsers(filters: UserFilters = {}): Promise<PaginatedResponse<User>> {
    const response = await apiClient.get<{ users: User[] }>('/simple/users', filters)
    if (response.status === 'success' && response.data) {
      // Transform the response to match PaginatedResponse format
      return {
        data: response.data.users,
        pagination: {
          current_page: 1,
          last_page: 1,
          per_page: response.data.users.length,
          total: response.data.users.length
        }
      }
    }
    throw new Error(response.message || 'Failed to get users')
  },

  // Get user by ID
  async getUserById(id: string): Promise<User> {
    const response = await apiClient.get<User>(`/simple/users/${id}`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get user')
  },

  // Create user
  async createUser(data: UserFormData): Promise<User> {
    const response = await apiClient.post<User>('/simple/users', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to create user')
  },

  // Update user
  async updateUser(id: string, data: Partial<UserFormData>): Promise<User> {
    const response = await apiClient.put<User>(`/simple/users/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update user')
  },

  // Delete user
  async deleteUser(id: string): Promise<void> {
    const response = await apiClient.delete(`/simple/users/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete user')
    }
  },

  // Get current user profile
  async getProfile(): Promise<User> {
    const response = await apiClient.get<User>('/users/profile')
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get profile')
  },

  // Update profile
  async updateProfile(data: Partial<UserFormData>): Promise<User> {
    const response = await apiClient.put<User>('/users/profile', data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update profile')
  },
}
