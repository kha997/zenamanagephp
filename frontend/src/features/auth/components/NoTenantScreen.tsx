import React from 'react';
import { Button } from '../../../shared/ui/button';
import { useAuthStore } from '../store';

/**
 * NoTenantScreen - Component displayed when user has no tenants
 * 
 * Shows a clear message that the account is not assigned to any tenant
 * and provides a logout action.
 */
export const NoTenantScreen: React.FC = () => {
  const { logout } = useAuthStore();

  const handleLogout = async () => {
    try {
      await logout();
      // Redirect to login page after logout
      window.location.href = '/login';
    } catch (error) {
      console.error('Logout failed:', error);
      // Still redirect even if logout fails
      window.location.href = '/login';
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="rounded-lg bg-white p-6 shadow-xl max-w-md w-full mx-4">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          Tài khoản chưa được gán vào bất kỳ đơn vị (tenant) nào.
        </h3>
        <p className="text-gray-600 mb-4">
          Vui lòng liên hệ quản trị viên hệ thống để được cấp quyền truy cập.
        </p>
        <Button onClick={handleLogout} className="w-full">
          Đăng xuất
        </Button>
      </div>
    </div>
  );
};

