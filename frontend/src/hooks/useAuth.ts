/**
 * Hook quản lý xác thực người dùng
 * Tích hợp với Zustand auth store và JWT token
 * 
 * Round 135: Updated to use canonical auth store from features/auth/store.ts
 */
import { useCallback } from 'react';
import { useAuthStore } from '@/features/auth/store';
import { apiClient } from '../lib/api/client';
import { LoginCredentials, RegisterData, User } from '../lib/types';
import { removeToken, removeUser } from '../lib/utils/auth';
import { useToast } from './useToast';

export const useAuth = () => {
  const {
    user,
    isAuthenticated,
    isLoading,
    login: canonicalLogin,
    logout: canonicalLogout,
    setUser: canonicalSetUser,
    checkAuth
  } = useAuthStore();
  
  // Get token from localStorage (canonical store manages it there)
  const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
  
  const { showToast } = useToast();

  const login = useCallback(async (credentials: LoginCredentials) => {
    try {
      // Use canonical store's login method
      const redirectPath = await canonicalLogin(credentials);
      showToast('Đăng nhập thành công!', 'success');
      return { success: true, user, token: localStorage.getItem('auth_token'), redirectPath };
    } catch (error: any) {
      const message = error.response?.data?.message || error.message || 'Đăng nhập thất bại';
      showToast(message, 'error');
      return { success: false, error: message };
    }
  }, [canonicalLogin, user, showToast]);

  const register = useCallback(async (data: RegisterData) => {
    try {
      const response = await apiClient.post('/auth/register', data);
      
      if (response.data.status === 'success') {
        showToast('Đăng ký thành công! Vui lòng đăng nhập.', 'success');
        return { success: true };
      }
      
      throw new Error(response.data.message || 'Đăng ký thất bại');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Đăng ký thất bại';
      showToast(message, 'error');
      return { success: false, error: message };
    }
  }, [showToast]);

  const logout = useCallback(async () => {
    try {
      // Use canonical store's logout method (handles API call and state clearing)
      await canonicalLogout();
      // Also clear legacy storage if any
      removeToken();
      removeUser();
      showToast('Đã đăng xuất thành công', 'info');
    } catch (error) {
      // Ignore logout API errors, still clear local auth
      console.warn('Logout API failed:', error);
      removeToken();
      removeUser();
      showToast('Đã đăng xuất thành công', 'info');
    }
  }, [canonicalLogout, showToast]);

  const refreshToken = useCallback(async () => {
    try {
      const response = await apiClient.post('/auth/refresh');
      
      if (response.data.status === 'success') {
        const { token } = response.data.data;
        // Store token and trigger checkAuth to sync state
        if (typeof window !== 'undefined') {
          localStorage.setItem('auth_token', token);
          apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
        await checkAuth();
        return token;
      }
      
      throw new Error('Token refresh failed');
    } catch (error) {
      // Token refresh failed, logout user
      await logout();
      throw error;
    }
  }, [checkAuth, logout]);

  const updateProfile = useCallback(async (userData: Partial<User>) => {
    try {
      const response = await apiClient.put('/auth/profile', userData);
      
      if (response.data.status === 'success') {
        const updatedUser = response.data.data;
        canonicalSetUser(updatedUser);
        showToast('Cập nhật thông tin thành công!', 'success');
        return { success: true, user: updatedUser };
      }
      
      throw new Error(response.data.message || 'Cập nhật thất bại');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Cập nhật thất bại';
      showToast(message, 'error');
      return { success: false, error: message };
    }
  }, [canonicalSetUser, showToast]);

  return {
    // State
    user,
    token,
    isAuthenticated,
    isLoading,
    
    // Actions
    login,
    register,
    logout,
    refreshToken,
    updateProfile
  };
};