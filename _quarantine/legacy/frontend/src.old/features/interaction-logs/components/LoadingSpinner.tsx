import React from 'react';
import { Loader2 } from 'lucide-react';

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg' | 'xl';
  variant?: 'primary' | 'secondary' | 'white';
  text?: string;
  className?: string;
  fullScreen?: boolean;
}

/**
 * LoadingSpinner component để hiển thị trạng thái loading
 * Hỗ trợ nhiều kích thước, màu sắc và layout khác nhau
 */
export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  variant = 'primary',
  text,
  className = '',
  fullScreen = false
}) => {
  /**
   * Lấy class cho kích thước spinner
   */
  const getSizeClass = () => {
    switch (size) {
      case 'sm':
        return 'h-4 w-4';
      case 'lg':
        return 'h-8 w-8';
      case 'xl':
        return 'h-12 w-12';
      default:
        return 'h-6 w-6';
    }
  };

  /**
   * Lấy class cho màu sắc spinner
   */
  const getVariantClass = () => {
    switch (variant) {
      case 'secondary':
        return 'text-gray-500';
      case 'white':
        return 'text-white';
      default:
        return 'text-blue-600';
    }
  };

  /**
   * Lấy class cho text size
   */
  const getTextSizeClass = () => {
    switch (size) {
      case 'sm':
        return 'text-xs';
      case 'lg':
        return 'text-base';
      case 'xl':
        return 'text-lg';
      default:
        return 'text-sm';
    }
  };

  const spinnerContent = (
    <div className={`flex flex-col items-center justify-center gap-3 ${className}`}>
      <Loader2 className={`animate-spin ${getSizeClass()} ${getVariantClass()}`} />
      {text && (
        <p className={`${getTextSizeClass()} ${getVariantClass()} font-medium`}>
          {text}
        </p>
      )}
    </div>
  );

  if (fullScreen) {
    return (
      <div className="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50">
        {spinnerContent}
      </div>
    );
  }

  return spinnerContent;
};