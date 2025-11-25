import React, { useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Plus, Download, RefreshCw } from 'lucide-react';
import { useInteractionLogs } from '../hooks/useInteractionLogs';
import { usePusherInteractionLogs } from '../hooks/usePusherInteractionLogs';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { useInteractionLogsStore } from '../store/useInteractionLogsStore';
import { FilterBar, Pagination, EmptyState, Skeleton } from '../components';
import { InteractionLogCard } from '../components/InteractionLogCard';
import { formatDate } from '../utils/date';
import { InteractionLog } from '../types/interactionLog';

/**
 * InteractionLogsList page - Trang danh sách interaction logs
 * Hiển thị danh sách interaction logs với filter, pagination và real-time updates
 */
export const InteractionLogsList: React.FC = () => {
  const { projectId } = useParams<{ projectId: string }>();
  const navigate = useNavigate();
  
  // Store state
  const { 
    filters, 
    pagination, 
    selectedIds,
    isLoading,
    setPagination,
    clearSelection,
    toggleSelection,
    selectAll,
    setIsLoading
  } = useInteractionLogsStore();

  // Auth guard
  const { hasPermission } = useAuthGuard();
  const canCreate = hasPermission('interaction_logs.create');
  const canExport = hasPermission('interaction_logs.export');
  const canApprove = hasPermission('interaction_logs.approve');

  // Data fetching
  const {
    data: interactionLogsData,
    isLoading: isQueryLoading,
    error,
    refetch
  } = useInteractionLogs({
    projectId: projectId ? parseInt(projectId) : undefined,
    filters,
    pagination
  });

  // Real-time updates
  usePusherInteractionLogs(projectId ? parseInt(projectId) : undefined);

  /**
   * Sync loading state với store
   */
  useEffect(() => {
    setIsLoading(isQueryLoading);
  }, [isQueryLoading, setIsLoading]);

  /**
   * Cập nhật pagination khi có dữ liệu mới
   */
  useEffect(() => {
    if (interactionLogsData?.pagination) {
      setPagination({
        currentPage: interactionLogsData.pagination.current_page,
        totalPages: interactionLogsData.pagination.last_page,
        pageSize: interactionLogsData.pagination.per_page,
        totalItems: interactionLogsData.pagination.total
      });
    }
  }, [interactionLogsData?.pagination, setPagination]);

  /**
   * Xử lý thay đổi trang
   */
  const handlePageChange = (page: number) => {
    setPagination({ currentPage: page });
  };

  /**
   * Xử lý thay đổi page size
   */
  const handlePageSizeChange = (pageSize: number) => {
    setPagination({ pageSize, currentPage: 1 });
  };

  /**
   * Xử lý tạo interaction log mới
   */
  const handleCreateNew = () => {
    navigate(`/projects/${projectId}/interaction-logs/create`);
  };

  /**
   * Xử lý xem chi tiết interaction log
   */
  const handleViewDetail = (interactionLog: InteractionLog) => {
    navigate(`/projects/${projectId}/interaction-logs/${interactionLog.id}`);
  };

  /**
   * Xử lý refresh dữ liệu
   */
  const handleRefresh = () => {
    refetch();
    clearSelection();
  };

  /**
   * Xử lý export dữ liệu
   */
  const handleExport = async () => {
    try {
      // TODO: Implement export functionality
      console.log('Exporting interaction logs...', { filters, selectedIds });
    } catch (error) {
      console.error('Export failed:', error);
    }
  };

  /**
   * Xử lý bulk approve
   */
  const handleBulkApprove = async () => {
    try {
      // TODO: Implement bulk approve functionality
      console.log('Bulk approving interaction logs...', selectedIds);
    } catch (error) {
      console.error('Bulk approve failed:', error);
    }
  };

  /**
   * Xử lý select all
   */
  const handleSelectAll = () => {
    if (interactionLogsData?.data) {
      const allIds = interactionLogsData.data.map(log => log.id);
      selectAll(allIds);
    }
  };

  /**
   * Kiểm tra có item nào được select không
   */
  const hasSelectedItems = selectedIds.length > 0;
  const isAllSelected = interactionLogsData?.data && 
    selectedIds.length === interactionLogsData.data.length;

  // Loading state
  if (isLoading && !interactionLogsData) {
    return (
      <div className="space-y-6">
        <Skeleton variant="filter" />
        <Skeleton variant="list" count={5} />
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="space-y-6">
        <FilterBar />
        <EmptyState 
          type="error" 
          title="Không thể tải dữ liệu"
          description="Có lỗi xảy ra khi tải danh sách interaction logs. Vui lòng thử lại."
          actionLabel="Thử lại"
          onAction={handleRefresh}
        />
      </div>
    );
  }

  const interactionLogs = interactionLogsData?.data || [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Interaction Logs</h1>
          <p className="text-gray-600 mt-1">
            Quản lý các tương tác và ghi chú trong dự án
          </p>
        </div>
        
        <div className="flex items-center gap-3">
          {/* Bulk actions */}
          {hasSelectedItems && (
            <div className="flex items-center gap-2 mr-4">
              <span className="text-sm text-gray-600">
                Đã chọn {selectedIds.length} mục
              </span>
              
              {canApprove && (
                <button
                  onClick={handleBulkApprove}
                  className="px-3 py-1.5 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                >
                  Duyệt hàng loạt
                </button>
              )}
            </div>
          )}
          
          {/* Action buttons */}
          <button
            onClick={handleRefresh}
            className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
            title="Làm mới"
          >
            <RefreshCw className="h-5 w-5" />
          </button>
          
          {canExport && (
            <button
              onClick={handleExport}
              className="flex items-center gap-2 px-3 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
            >
              <Download className="h-4 w-4" />
              Xuất dữ liệu
            </button>
          )}
          
          {canCreate && (
            <button
              onClick={handleCreateNew}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <Plus className="h-4 w-4" />
              Tạo mới
            </button>
          )}
        </div>
      </div>

      {/* Filter Bar */}
      <FilterBar />

      {/* Content */}
      {interactionLogs.length === 0 ? (
        <EmptyState 
          type={Object.values(filters).some(v => v !== undefined && v !== '' && v !== null) ? 'no-results' : 'no-data'}
          onAction={Object.values(filters).some(v => v !== undefined && v !== '' && v !== null) ? undefined : handleCreateNew}
        />
      ) : (
        <>
          {/* Selection controls */}
          <div className="flex items-center justify-between bg-gray-50 px-4 py-2 rounded-lg">
            <div className="flex items-center gap-3">
              <input
                type="checkbox"
                checked={isAllSelected}
                onChange={handleSelectAll}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <span className="text-sm text-gray-700">
                {isAllSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả'}
              </span>
            </div>
            
            {hasSelectedItems && (
              <button
                onClick={clearSelection}
                className="text-sm text-gray-500 hover:text-gray-700 underline"
              >
                Xóa lựa chọn
              </button>
            )}
          </div>

          {/* List */}
          <div className="space-y-4">
            {interactionLogs.map((interactionLog) => (
              <InteractionLogCard
                key={interactionLog.id}
                interactionLog={interactionLog}
                isSelected={selectedIds.includes(interactionLog.id)}
                onSelect={() => toggleSelection(interactionLog.id)}
                onView={() => handleViewDetail(interactionLog)}
                showActions={canApprove}
              />
            ))}
          </div>

          {/* Pagination */}
          <Pagination
            pagination={pagination}
            onPageChange={handlePageChange}
            onPageSizeChange={handlePageSizeChange}
          />
        </>
      )}
    </div>
  );
};