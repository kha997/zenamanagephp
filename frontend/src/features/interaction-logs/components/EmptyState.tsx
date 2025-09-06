import React from 'react';
import { MessageSquare, Search, Plus } from 'lucide-react';

interface EmptyStateProps {
  type?: 'no-data' | 'no-results' | 'error';
  title?: string;
  description?: string;
  actionLabel?: string;
  onAction?: () => void;
}

/**
 * EmptyState component để hiển thị khi không có dữ liệu
 * Hỗ trợ các trạng thái khác nhau: no-data, no-results, error
 */
export const EmptyState: React.FC<EmptyStateProps> = ({
  type = 'no-data',
  title,
  description,
  actionLabel,
  onAction
}) => {
  /**
   * Lấy icon phù hợp dựa trên type
   */
  const getIcon = () => {
    switch (type) {
      case 'no-results':
        return <Search className="h-12 w-12 text-gray-400" />;
      case 'error':
        return <MessageSquare className="h-12 w-12 text-red-400" />;
      default:
        return <MessageSquare className="h-12 w-12 text-gray-400" />;
    }
  };

  /**
   * Lấy title mặc định dựa trên type
   */
  const getDefaultTitle = () => {
    switch (type) {
      case 'no-results':
        return 'Không tìm thấy kết quả';
      case 'error':
        return 'Có lỗi xảy ra';
      default:
        return 'Chưa có interaction log nào';
    }
  };

  /**
   * Lấy description mặc định dựa trên type
   */
  const getDefaultDescription = () => {
    switch (type) {
      case 'no-results':
        return 'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm để xem thêm kết quả.';
      case 'error':
        return 'Không thể tải dữ liệu interaction logs. Vui lòng thử lại sau.';
      default:
        return 'Bắt đầu bằng cách tạo interaction log đầu tiên cho dự án này.';
    }
  };

  /**
   * Lấy action label mặc định dựa trên type
   */
  const getDefaultActionLabel = () => {
    switch (type) {
      case 'no-results':
        return 'Xóa bộ lọc';
      case 'error':
        return 'Thử lại';
      default:
        return 'Tạo interaction log';
    }
  };

  return (
    <div className="text-center py-12">
      <div className="flex justify-center mb-4">
        {getIcon()}
      </div>
      
      <h3 className="text-lg font-medium text-gray-900 mb-2">
        {title || getDefaultTitle()}
      </h3>
      
      <p className="text-gray-500 mb-6 max-w-md mx-auto">
        {description || getDefaultDescription()}
      </p>
      
      {(onAction || type === 'no-data') && (
        <button
          onClick={onAction}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          {type === 'no-data' && <Plus className="h-4 w-4" />}
          {actionLabel || getDefaultActionLabel()}
        </button>
      )}
    </div>
  );
};