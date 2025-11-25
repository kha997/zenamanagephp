import { api, ApiResponse } from './api'

// User types
export interface User {
  id: string
  name: string
  email: string
  avatar?: string
  phone?: string
  preferences?: any
  last_login_at?: string
  roles: string[]
  permissions: string[]
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  role?: string
}

export interface AuthResponse {
  token: string
  token_type: string
  expires_in: number
  user: User
}

export interface PasswordResetRequest {
  email: string
}

export interface PasswordReset {
  token: string
  email: string
  password: string
  password_confirmation: string
}

class AuthService {
  private tokenKey = 'auth_token'
  private userKey = 'user'

  // Login
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const response = await api.post<AuthResponse>('/auth/login', credentials)
      
      if (response.status === 'success' && response.data) {
        const { user, token, token_type, expires_in } = response.data
        
        // Store token and user data
        localStorage.setItem(this.tokenKey, token)
        localStorage.setItem(this.userKey, JSON.stringify(user))
        
        return response.data
      }
      
      throw new Error('Login failed')
    } catch (error) {
      console.error('Login error:', error)
      throw error
    }
  }

  // Register
  async register(data: RegisterData): Promise<AuthResponse> {
    try {
      const response = await api.post<AuthResponse>('/auth/register', data)
      
      if (response.status === 'success' && response.data) {
        const { user, token, expires_at } = response.data
        
        // Store token and user data
        localStorage.setItem(this.tokenKey, token)
        localStorage.setItem(this.userKey, JSON.stringify(user))
        
        return response.data
      }
      
      throw new Error('Registration failed')
    } catch (error) {
      console.error('Registration error:', error)
      throw error
    }
  }

  // Logout
  async logout(): Promise<void> {
    try {
      await api.post('/auth/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      // Clear local storage regardless of API call success
      localStorage.removeItem(this.tokenKey)
      localStorage.removeItem(this.userKey)
    }
  }

  // Get current user
  async getCurrentUser(): Promise<User> {
    try {
      const response = await api.get<User>('/auth/me')
      
      if (response.status === 'success' && response.data) {
        // Update stored user data
        localStorage.setItem(this.userKey, JSON.stringify(response.data))
        return response.data
      }
      
      throw new Error('Failed to get current user')
    } catch (error) {
      console.error('Get current user error:', error)
      throw error
    }
  }

  // Refresh token
  async refreshToken(): Promise<AuthResponse> {
    try {
      const response = await api.post<AuthResponse>('/auth/refresh')
      
      if (response.status === 'success' && response.data) {
        const { user, token, expires_at } = response.data
        
        // Update stored token and user data
        localStorage.setItem(this.tokenKey, token)
        localStorage.setItem(this.userKey, JSON.stringify(user))
        
        return response.data
      }
      
      throw new Error('Token refresh failed')
    } catch (error) {
      console.error('Token refresh error:', error)
      throw error
    }
  }

  // Request password reset
  async requestPasswordReset(data: PasswordResetRequest): Promise<void> {
    try {
      await api.post('/auth/password/reset', data)
    } catch (error) {
      console.error('Password reset request error:', error)
      throw error
    }
  }

  // Reset password
  async resetPassword(data: PasswordReset): Promise<void> {
    try {
      await api.post('/auth/password/reset/confirm', data)
    } catch (error) {
      console.error('Password reset error:', error)
      throw error
    }
  }

  // Change password
  async changePassword(data: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    try {
      await api.post('/auth/password/change', data)
    } catch (error) {
      console.error('Change password error:', error)
      throw error
    }
  }

  // Update profile
  async updateProfile(data: Partial<User>): Promise<User> {
    try {
      const response = await api.put<User>('/auth/profile', data)
      
      if (response.status === 'success' && response.data) {
        // Update stored user data
        localStorage.setItem(this.userKey, JSON.stringify(response.data))
        return response.data
      }
      
      throw new Error('Profile update failed')
    } catch (error) {
      console.error('Profile update error:', error)
      throw error
    }
  }

  // Check if user is authenticated
  isAuthenticated(): boolean {
    const token = localStorage.getItem(this.tokenKey)
    return !!token
  }

  // Get stored token
  getToken(): string | null {
    return localStorage.getItem(this.tokenKey)
  }

  // Get stored user
  getStoredUser(): User | null {
    const userStr = localStorage.getItem(this.userKey)
    if (userStr) {
      try {
        return JSON.parse(userStr)
      } catch (error) {
        console.error('Error parsing stored user:', error)
        return null
      }
    }
    return null
  }

  // Clear stored data
  clearStoredData(): void {
    localStorage.removeItem(this.tokenKey)
    localStorage.removeItem(this.userKey)
  }
}

export const authService = new AuthService()
export default authService