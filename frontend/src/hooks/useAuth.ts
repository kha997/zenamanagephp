/**
 * Hook quản lý xác thực người dùng
 * Tích hợp với Zustand auth store và JWT token
 */
import { useCallback } from 'react';
import { useAuthStore } from '../store/auth';
import { apiClient } from '../lib/api/client';
import { LoginCredentials, RegisterData, User } from '../lib/types';
import { removeToken, removeUser } from '../lib/utils/auth';
import { useToast } from './useToast';

export const useAuth = () => {
  const {
    user,
    token,
    isAuthenticated,
    isLoading,
    setUser,
    setToken,
    setLoading,
    clearAuth
  } = useAuthStore();
  
  const { showToast } = useToast();

  const login = useCallback(async (credentials: LoginCredentials) => {
    try {
      setLoading(true);
      const response = await apiClient.post('/auth/login', credentials);
      
      if (response.data.status === 'success') {
        const { user, token } = response.data.data;
        setUser(user);
        setToken(token);
        showToast('Đăng nhập thành công!', 'success');
        return { success: true, user, token };
      }
      
      throw new Error(response.data.message || 'Đăng nhập thất bại');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Đăng nhập thất bại';
      showToast(message, 'error');
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [setUser, setToken, setLoading, showToast]);

  const register = useCallback(async (data: RegisterData) => {
    try {
      setLoading(true);
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
    } finally {
      setLoading(false);
    }
  }, [setLoading, showToast]);

  const logout = useCallback(async () => {
    try {
      // Gọi API logout để invalidate token trên server
      await apiClient.post('/auth/logout');
    } catch (error) {
      // Ignore logout API errors, still clear local auth
      console.warn('Logout API failed:', error);
    } finally {
      // Clear local auth state
      clearAuth();
      removeToken();
      removeUser();
      showToast('Đã đăng xuất thành công', 'info');
    }
  }, [clearAuth, showToast]);

  const refreshToken = useCallback(async () => {
    try {
      const response = await apiClient.post('/auth/refresh');
      
      if (response.data.status === 'success') {
        const { token } = response.data.data;
        setToken(token);
        return token;
      }
      
      throw new Error('Token refresh failed');
    } catch (error) {
      // Token refresh failed, logout user
      logout();
      throw error;
    }
  }, [setToken, logout]);

  const updateProfile = useCallback(async (userData: Partial<User>) => {
    try {
      setLoading(true);
      const response = await apiClient.put('/auth/profile', userData);
      
      if (response.data.status === 'success') {
        const updatedUser = response.data.data;
        setUser(updatedUser);
        showToast('Cập nhật thông tin thành công!', 'success');
        return { success: true, user: updatedUser };
      }
      
      throw new Error(response.data.message || 'Cập nhật thất bại');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Cập nhật thất bại';
      showToast(message, 'error');
      return { success: false, error: message };
    } finally {
      setLoading(false);
    }
  }, [setUser, setLoading, showToast]);

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