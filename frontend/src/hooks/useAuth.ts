/**
 * Hook quản lý xác thực người dùng
 * Tích hợp với Zustand auth store và JWT token
 */
import { useCallback } from 'react';
import { useAuthStore } from '../store/auth';
import { LoginCredentials, RegisterData, User } from '../lib/types';
import { AuthService } from '../lib/api/auth.service';
import { useToast } from './useToast';

export const useAuth = () => {
  const {
    user,
    isAuthenticated,
    isLoading,
    login: storeLogin,
    logout: storeLogout,
    updateProfile: storeUpdateProfile
  } = useAuthStore();
  
  const { showToast } = useToast();

  const login = useCallback(async (credentials: LoginCredentials) => {
    try {
      await storeLogin(credentials);
      showToast('success', 'Đăng nhập thành công!');
      return { success: true };
    } catch (error: any) {
      const message = error.response?.data?.message || 'Đăng nhập thất bại';
      showToast('error', message);
      return { success: false, error: message };
    }
  }, [storeLogin, showToast]);

  const register = useCallback(async (data: RegisterData) => {
    try {
      await AuthService.register(data);
      showToast('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
      return { success: true };
    } catch (error: any) {
      const message = error.response?.data?.message || 'Đăng ký thất bại';
      showToast('error', message);
      return { success: false, error: message };
    }
  }, [showToast]);

  const logout = useCallback(async () => {
    try {
      await storeLogout();
    } catch (error) {
      console.warn('Logout API failed:', error);
    }
    showToast('info', 'Đã đăng xuất thành công');
  }, [storeLogout, showToast]);

  const refreshToken = useCallback(async () => {
    try {
      await useAuthStore.getState().refreshToken();
      return true;
    } catch (error) {
      logout();
      throw error;
    }
  }, [logout]);

  const updateProfile = useCallback(async (userData: Partial<User>) => {
    try {
      await storeUpdateProfile(userData);
      showToast('success', 'Cập nhật thông tin thành công!');
      return { success: true };
    } catch (error: any) {
      const message = error.response?.data?.message || 'Cập nhật thất bại';
      showToast('error', message);
      return { success: false, error: message };
    }
  }, [storeUpdateProfile, showToast]);

  return {
    // State
    user,
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
