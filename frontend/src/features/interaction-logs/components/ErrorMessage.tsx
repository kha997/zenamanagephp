import React from 'react';
import { AlertCircle, RefreshCw, X } from 'lucide-react';

interface ErrorMessageProps {
  title?: string;
  message: string;
  variant?: 'error' | 'warning' | 'info';
  showRetry?: boolean;
  showDismiss?: boolean;
  onRetry?: () => void;
  onDismiss?: () => void;
  className?: string;
}

/**
 * ErrorMessage component để hiển thị thông báo lỗi
 * Hỗ trợ các variant khác nhau và actions
 */
export const ErrorMessage: React.FC<ErrorMessageProps> = ({
  title,
  message,
  variant = 'error',
  showRetry = false,
  showDismiss = false,
  onRetry,
  onDismiss,
  className = ''
}) => {
  /**
   * Lấy class cho variant
   */
  const getVariantClasses = () => {
    switch (variant) {
      case 'warning':
        return {
          container: 'bg-yellow-50 border-yellow-200',
          icon: 'text-yellow-600',
          title: 'text-yellow-800',
          message: 'text-yellow-700'
        };
      case 'info':
        return {
          container: 'bg-blue-50 border-blue-200',
          icon: 'text-blue-600',
          title: 'text-blue-800',
          message: 'text-blue-700'
        };
      default:
        return {
          container: 'bg-red-50 border-red-200',
          icon: 'text-red-600',
          title: 'text-red-800',
          message: 'text-red-700'
        };
    }
  };

  const classes = getVariantClasses();

  return (
    <div className={`border rounded-md p-4 ${classes.container} ${className}`}>
      <div className="flex items-start">
        <div className="flex-shrink-0">
          <AlertCircle className={`h-5 w-5 ${classes.icon}`} />
        </div>
        
        <div className="ml-3 flex-1">
          {title && (
            <h3 className={`text-sm font-medium ${classes.title} mb-1`}>
              {title}
            </h3>
          )}
          
          <p className={`text-sm ${classes.message}`}>
            {message}
          </p>
          
          {(showRetry || showDismiss) && (
            <div className="mt-3 flex gap-2">
              {showRetry && onRetry && (
                <button
                  onClick={onRetry}
                  className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                  <RefreshCw className="h-3 w-3" />
                  Thử lại
                </button>
              )}
            </div>
          )}
        </div>
        
        {showDismiss && onDismiss && (
          <div className="ml-auto pl-3">
            <button
              onClick={onDismiss}
              className={`inline-flex rounded-md p-1.5 hover:bg-opacity-20 focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors ${
                variant === 'error' ? 'text-red-500 hover:bg-red-600 focus:ring-red-500' :
                variant === 'warning' ? 'text-yellow-500 hover:bg-yellow-600 focus:ring-yellow-500' :
                'text-blue-500 hover:bg-blue-600 focus:ring-blue-500'
              }`}
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        )}
      </div>
    </div>
  );
};