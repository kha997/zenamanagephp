import React from 'react';
import { LucideIcon, FileX, Search, Plus, AlertCircle } from 'lucide-react';
import { Button } from './Button';
import { cn } from '../../lib/utils';

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: {
    label: string;
    onClick: () => void;
    variant?: 'primary' | 'outline';
  };
  secondaryAction?: {
    label: string;
    onClick: () => void;
  };
  className?: string;
  size?: 'sm' | 'md' | 'lg';
}

/**
 * EmptyState component để hiển thị khi không có dữ liệu
 * Cung cấp hướng dẫn rõ ràng cho người dùng về cách thêm nội dung
 */
export const EmptyState: React.FC<EmptyStateProps> = ({
  icon: Icon = FileX,
  title,
  description,
  action,
  secondaryAction,
  className,
  size = 'md',
}) => {
  const sizeClasses = {
    sm: {
      container: 'py-8',
      icon: 'h-8 w-8',
      title: 'text-lg',
      description: 'text-sm',
    },
    md: {
      container: 'py-12',
      icon: 'h-12 w-12',
      title: 'text-xl',
      description: 'text-base',
    },
    lg: {
      container: 'py-16',
      icon: 'h-16 w-16',
      title: 'text-2xl',
      description: 'text-lg',
    },
  };

  return (
    <div className={cn(
      'flex flex-col items-center justify-center text-center',
      sizeClasses[size].container,
      className
    )}>
      <div className="flex justify-center mb-4">
        <Icon className={cn(
          'text-gray-400',
          sizeClasses[size].icon
        )} />
      </div>
      
      <h3 className={cn(
        'font-semibold text-gray-900 mb-2',
        sizeClasses[size].title
      )}>
        {title}
      </h3>
      
      {description && (
        <p className={cn(
          'text-gray-600 mb-6 max-w-md',
          sizeClasses[size].description
        )}>
          {description}
        </p>
      )}
      
      {(action || secondaryAction) && (
        <div className="flex flex-col sm:flex-row gap-3">
          {action && (
            <Button
              onClick={action.onClick}
              variant={action.variant || 'primary'}
              className="flex items-center gap-2"
            >
              <Plus className="h-4 w-4" />
              {action.label}
            </Button>
          )}
          
          {secondaryAction && (
            <Button
              onClick={secondaryAction.onClick}
              variant="outline"
            >
              {secondaryAction.label}
            </Button>
          )}
        </div>
      )}
    </div>
  );
};

// Predefined empty states cho các trường hợp phổ biến
export const NoDataFound: React.FC<{
  title?: string;
  description?: string;
  onRefresh?: () => void;
  className?: string;
}> = ({ 
  title = 'Không tìm thấy dữ liệu',
  description = 'Không có dữ liệu để hiển thị.',
  onRefresh,
  className 
}) => (
  <EmptyState
    icon={FileX}
    title={title}
    description={description}
    action={onRefresh ? {
      label: 'Tải lại',
      onClick: onRefresh,
      variant: 'outline'
    } : undefined}
    className={className}
  />
);

export const NoSearchResults: React.FC<{
  query: string;
  onClearSearch?: () => void;
  className?: string;
}> = ({ query, onClearSearch, className }) => (
  <EmptyState
    icon={Search}
    title="Không tìm thấy kết quả"
    description={`Không tìm thấy kết quả nào cho "${query}". Hãy thử tìm kiếm với từ khóa khác.`}
    action={onClearSearch ? {
      label: 'Xóa bộ lọc',
      onClick: onClearSearch,
      variant: 'outline'
    } : undefined}
    className={className}
  />
);

export const ErrorState: React.FC<{
  title?: string;
  description?: string;
  onRetry?: () => void;
  className?: string;
}> = ({ 
  title = 'Có lỗi xảy ra',
  description = 'Không thể tải dữ liệu. Vui lòng thử lại.',
  onRetry,
  className 
}) => (
  <EmptyState
    icon={AlertCircle}
    title={title}
    description={description}
    action={onRetry ? {
      label: 'Thử lại',
      onClick: onRetry,
      variant: 'primary'
    } : undefined}
    className={className}
  />
);