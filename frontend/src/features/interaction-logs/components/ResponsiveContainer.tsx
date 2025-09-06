import React from 'react';

interface ResponsiveContainerProps {
  children: React.ReactNode;
  className?: string;
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
  padding?: 'none' | 'sm' | 'md' | 'lg';
}

/**
 * ResponsiveContainer component để wrap content với responsive layout
 * Tự động điều chỉnh padding và max-width theo screen size
 */
export const ResponsiveContainer: React.FC<ResponsiveContainerProps> = ({
  children,
  className = '',
  maxWidth = 'full',
  padding = 'md'
}) => {
  /**
   * Lấy class cho max-width
   */
  const getMaxWidthClass = () => {
    switch (maxWidth) {
      case 'sm':
        return 'max-w-sm';
      case 'md':
        return 'max-w-md';
      case 'lg':
        return 'max-w-lg';
      case 'xl':
        return 'max-w-xl';
      case '2xl':
        return 'max-w-2xl';
      default:
        return 'max-w-full';
    }
  };

  /**
   * Lấy class cho padding
   */
  const getPaddingClass = () => {
    switch (padding) {
      case 'none':
        return '';
      case 'sm':
        return 'px-2 sm:px-4';
      case 'lg':
        return 'px-6 sm:px-8 lg:px-12';
      default:
        return 'px-4 sm:px-6 lg:px-8';
    }
  };

  return (
    <div className={`w-full mx-auto ${getMaxWidthClass()} ${getPaddingClass()} ${className}`}>
      {children}
    </div>
  );
};