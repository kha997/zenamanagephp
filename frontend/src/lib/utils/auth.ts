import { STORAGE_KEYS } from '../constants'
import { User } from '../types'

/**
 * Utilities cho authentication và authorization
 */

/**
 * Lưu JWT token vào localStorage
 */
export const setToken = (token: string): void => {
  localStorage.setItem(STORAGE_KEYS.AUTH_TOKEN, token)
}

/**
 * Lấy JWT token từ localStorage
 */
export const getToken = (): string | null => {
  return localStorage.getItem(STORAGE_KEYS.AUTH_TOKEN)
}

/**
 * Xóa JWT token khỏi localStorage
 */
export const removeToken = (): void => {
  localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN)
  localStorage.removeItem(STORAGE_KEYS.USER_PROFILE)
}

/**
 * Kiểm tra xem user đã đăng nhập chưa
 */
export const isAuthenticated = (): boolean => {
  const token = getToken()
  return !!token && !isTokenExpired(token)
}

/**
 * Kiểm tra token có hết hạn không
 */
export const isTokenExpired = (token: string): boolean => {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]))
    const currentTime = Date.now() / 1000
    return payload.exp < currentTime
  } catch {
    return true
  }
}

/**
 * Lưu thông tin user vào localStorage
 */
export const setUserProfile = (user: User): void => {
  localStorage.setItem(STORAGE_KEYS.USER_PROFILE, JSON.stringify(user))
}

/**
 * Lấy thông tin user từ localStorage
 */
export const getUserProfile = (): User | null => {
  const userStr = localStorage.getItem(STORAGE_KEYS.USER_PROFILE)
  if (!userStr) return null
  
  try {
    return JSON.parse(userStr)
  } catch {
    return null
  }
}

/**
 * Kiểm tra user có permission không
 */
export const hasPermission = (permission: string, user?: User): boolean => {
  if (!user) {
    user = getUserProfile()
  }
  
  if (!user) return false
  
  return user.permissions.includes(permission)
}

/**
 * Kiểm tra user có role không
 */
export const hasRole = (roleName: string, user?: User): boolean => {
  if (!user) {
    user = getUserProfile()
  }
  
  if (!user) return false
  
  return user.roles.some(role => role.name === roleName)
}

/**
 * Kiểm tra user có phải admin không
 */
export const isAdmin = (user?: User): boolean => {
  return hasRole('admin', user) || hasRole('super_admin', user)
}

/**
 * Kiểm tra user có quyền truy cập project không
 */
export const canAccessProject = (projectId: string, user?: User): boolean => {
  if (!user) {
    user = getUserProfile()
  }
  
  if (!user) return false
  
  // Admin có thể truy cập tất cả projects
  if (isAdmin(user)) return true
  
  // Kiểm tra permission cụ thể cho project
  return hasPermission('project.read', user)
}