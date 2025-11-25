import React from 'react';

interface ResponsiveGridProps {
  children: React.ReactNode;
  cols?: {
    default?: number;
    sm?: number;
    md?: number;
    lg?: number;
    xl?: number;
  };
  gap?: 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
}

/**
 * ResponsiveGrid component để tạo grid layout responsive
 * Tự động điều chỉnh số cột theo screen size
 */
export const ResponsiveGrid: React.FC<ResponsiveGridProps> = ({
  children,
  cols = { default: 1, md: 2, lg: 3 },
  gap = 'md',
  className = ''
}) => {
  /**
   * Tạo grid columns classes
   */
  const getGridColsClass = () => {
    const classes = [];
    
    if (cols.default) classes.push(`grid-cols-${cols.default}`);
    if (cols.sm) classes.push(`sm:grid-cols-${cols.sm}`);
    if (cols.md) classes.push(`md:grid-cols-${cols.md}`);
    if (cols.lg) classes.push(`lg:grid-cols-${cols.lg}`);
    if (cols.xl) classes.push(`xl:grid-cols-${cols.xl}`);
    
    return classes.join(' ');
  };

  /**
   * Lấy gap class
   */
  const getGapClass = () => {
    switch (gap) {
      case 'sm':
        return 'gap-2 sm:gap-3';
      case 'lg':
        return 'gap-6 sm:gap-8';
      case 'xl':
        return 'gap-8 sm:gap-10';
      default:
        return 'gap-4 sm:gap-6';
    }
  };

  return (
    <div className={`grid ${getGridColsClass()} ${getGapClass()} ${className}`}>
      {children}
    </div>
  );
};