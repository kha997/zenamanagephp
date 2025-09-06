/**
 * Toaster Component
 * Quản lý và hiển thị toast notifications toàn cục
 */
import React from 'react';
import { ToastContainer } from './Toast';
import { useNotificationStore } from '../../store/notifications';

/**
 * Toaster component để hiển thị toast notifications
 * Tích hợp với notification store để quản lý trạng thái toàn cục
 * Component này được render trong App.tsx để hiển thị toast trên toàn bộ ứng dụng
 */
export const Toaster: React.FC = () => {
  const { toasts, removeToast } = useNotificationStore();

  return (
    <ToastContainer
      toasts={toasts}
      onRemove={removeToast}
    />
  );
};

export default Toaster;