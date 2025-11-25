import React from 'react';
import { Loader2 } from 'lucide-react';

interface LoadingButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  loading?: boolean;
  loadingText?: string;
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  icon?: React.ReactNode;
  children: React.ReactNode;
}

/**
 * LoadingButton component với loading state
 * Hiển thị spinner và disable button khi đang loading
 */
export const LoadingButton: React.FC<LoadingButtonProps> = ({
  loading = false,
  loadingText,
  variant = 'primary',
  size = 'md',
  icon,
  children,
  disabled,
  className = '',
  ...props
}) => {
  /**
   * Lấy class cho variant
   */
  const getVariantClasses = () => {
    switch (variant) {
      case 'secondary':
        return 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-blue-500';
      case 'danger':
        return 'bg-red-600 text-white border border-transparent hover:bg-red-700 focus:ring-red-500';
      case 'ghost':
        return 'bg-transparent text-gray-700 border border-transparent hover:bg-gray-100 focus:ring-blue-500';
      default:
        return 'bg-blue-600 text-white border border-transparent hover:bg-blue-700 focus:ring-blue-500';
    }
  };

  /**
   * Lấy class cho size
   */
  const getSizeClasses = () => {
    switch (size) {
      case 'sm':
        return 'px-3 py-1.5 text-xs';
      case 'lg':
        return 'px-6 py-3 text-base';
      default:
        return 'px-4 py-2 text-sm';
    }
  };

  const isDisabled = disabled || loading;
  const displayText = loading && loadingText ? loadingText : children;
  const displayIcon = loading ? <Loader2 className="h-4 w-4 animate-spin" /> : icon;

  return (
    <button
      {...props}
      disabled={isDisabled}
      className={`
        inline-flex items-center gap-2 font-medium rounded-md
        focus:outline-none focus:ring-2 focus:ring-offset-2
        disabled:opacity-50 disabled:cursor-not-allowed
        transition-colors duration-200
        ${getVariantClasses()}
        ${getSizeClasses()}
        ${className}
      `}
    >
      {displayIcon}
      {displayText}
    </button>
  );
};