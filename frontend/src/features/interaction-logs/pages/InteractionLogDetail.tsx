import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, 
  Edit, 
  Trash2, 
  CheckCircle, 
  Clock,
  Phone, 
  Mail, 
  Users, 
  FileText, 
  MessageSquare,
  Calendar,
  Tag,
  User,
  Eye,
  ExternalLink
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { InteractionLogsApi } from '../api/interactionLogsApi';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { Skeleton } from '../components';
import { formatDate, formatRelativeTime } from '../utils/date';
import { InteractionType, VisibilityType } from '../types/interactionLog';

/**
 * InteractionLogDetail page - Trang chi tiết interaction log
 * Hiển thị thông tin đầy đủ của một interaction log
 */
export const InteractionLogDetail: React.FC = () => {
  const { projectId, interactionLogId } = useParams<{ 
    projectId: string; 
    interactionLogId: string; 
  }>();
  const navigate = useNavigate();
  
  // Auth guard
  const { hasPermission } = useAuthGuard();
  const canEdit = hasPermission('interaction_logs.update');
  const canDelete = hasPermission('interaction_logs.delete');
  const canApprove = hasPermission('interaction_logs.approve');

  // Fetch interaction log detail
  const {
    data: interactionLogData,
    isLoading,
    error,
    refetch
  } = useQuery({
    queryKey: ['interaction-log', interactionLogId],
    queryFn: () => InteractionLogsApi.getById(parseInt(interactionLogId!)),
    enabled: !!interactionLogId
  });

  /**
   * Lấy icon cho loại interaction
   */
  const getTypeIcon = (type: InteractionType) => {
    const iconClass = "h-6 w-6";
    
    switch (type) {
      case 'call':
        return <Phone className={iconClass} />;
      case 'email':
        return <Mail className={iconClass} />;
      case 'meeting':
        return <Users className={iconClass} />;
      case 'note':
        return <FileText className={iconClass} />;
      case 'feedback':
        return <MessageSquare className={iconClass} />;
      default:
        return <FileText className={iconClass} />;
    }
  };

  /**
   * Lấy label cho loại interaction
   */
  const getTypeLabel = (type: InteractionType) => {
    switch (type) {
      case 'call':
        return 'Cuộc gọi';
      case 'email':
        return 'Email';
      case 'meeting':
        return 'Cuộc họp';
      case 'note':
        return 'Ghi chú';
      case 'feedback':
        return 'Phản hồi';
      default:
        return type;
    }
  };

  /**
   * Lấy màu cho loại interaction
   */
  const getTypeColor = (type: InteractionType) => {
    switch (type) {
      case 'call':
        return 'bg-green-100 text-green-800';
      case 'email':
        return 'bg-blue-100 text-blue-800';
      case 'meeting':
        return 'bg-purple-100 text-purple-800';
      case 'note':
        return 'bg-gray-100 text-gray-800';
      case 'feedback':
        return 'bg-orange-100 text-orange-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  /**
   * Lấy label cho visibility
   */
  const getVisibilityLabel = (visibility: VisibilityType) => {
    switch (visibility) {
      case 'internal':
        return 'Nội bộ';
      case 'client':
        return 'Khách hàng';
      default:
        return visibility;
    }
  };

  /**
   * Xử lý quay lại danh sách
   */
  const handleGoBack = () => {
    navigate(`/projects/${projectId}/interaction-logs`);
  };

  /**
   * Xử lý chỉnh sửa
   */
  const handleEdit = () => {
    navigate(`/projects/${projectId}/interaction-logs/${interactionLogId}/edit`);
  };

  /**
   * Xử lý xóa
   */
  const handleDelete = async () => {
    if (window.confirm('Bạn có chắc chắn muốn xóa interaction log này?')) {
      try {
        await InteractionLogsApi.delete(parseInt(interactionLogId!));
        navigate(`/projects/${projectId}/interaction-logs`);
      } catch (error) {
        console.error('Delete failed:', error);
      }
    }
  };

  /**
   * Xử lý approve/unapprove
   */
  const handleToggleApproval = async () => {
    try {
      await InteractionLogsApi.approve(parseInt(interactionLogId!));
      refetch();
    } catch (error) {
      console.error('Approval toggle failed:', error);
    }
  };

  // Loading state
  if (isLoading) {
    return <Skeleton variant="detail" />;
  }

  // Error state
  if (error || !interactionLogData?.data) {
    return (
      <div className="text-center py-12">
        <div className="text-red-500 mb-4">
          <MessageSquare className="h-12 w-12 mx-auto" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          Không thể tải interaction log
        </h3>
        <p className="text-gray-500 mb-6">
          Interaction log không tồn tại hoặc bạn không có quyền truy cập.
        </p>
        <button
          onClick={handleGoBack}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Quay lại danh sách
        </button>
      </div>
    );
  }

  const interactionLog = interactionLogData.data;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={handleGoBack}
            className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {getTypeLabel(interactionLog.type)}
            </h1>
            <p className="text-gray-600 mt-1">
              Chi tiết interaction log #{interactionLog.id}
            </p>
          </div>
        </div>
        
        <div className="flex items-center gap-3">
          {/* Approval button */}
          {canApprove && interactionLog.visibility === 'client' && (
            <button
              onClick={handleToggleApproval}
              className={`flex items-center gap-2 px-4 py-2 rounded-md transition-colors ${
                interactionLog.client_approved
                  ? 'bg-orange-100 text-orange-700 hover:bg-orange-200'
                  : 'bg-green-100 text-green-700 hover:bg-green-200'
              }`}
            >
              {interactionLog.client_approved ? (
                <>
                  <Clock className="h-4 w-4" />
                  Hủy duyệt
                </>
              ) : (
                <>
                  <CheckCircle className="h-4 w-4" />
                  Duyệt
                </>
              )}
            </button>
          )}
          
          {/* Edit button */}
          {canEdit && (
            <button
              onClick={handleEdit}
              className="flex items-center gap-2 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
            >
              <Edit className="h-4 w-4" />
              Chỉnh sửa
            </button>
          )}
          
          {/* Delete button */}
          {canDelete && (
            <button
              onClick={handleDelete}
              className="flex items-center gap-2 px-4 py-2 text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 transition-colors"
            >
              <Trash2 className="h-4 w-4" />
              Xóa
            </button>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        {/* Header section */}
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-start gap-4">
            <div className={`p-3 rounded-lg ${getTypeColor(interactionLog.type)}`}>
              {getTypeIcon(interactionLog.type)}
            </div>
            
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-2">
                <h2 className="text-xl font-semibold text-gray-900">
                  {getTypeLabel(interactionLog.type)}
                </h2>
                
                {/* Status badges */}
                <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${
                  interactionLog.visibility === 'client' 
                    ? 'bg-blue-100 text-blue-800' 
                    : 'bg-gray-100 text-gray-800'
                }`}>
                  <Eye className="h-3 w-3" />
                  {getVisibilityLabel(interactionLog.visibility)}
                </span>
                
                {interactionLog.visibility === 'client' && (
                  <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    interactionLog.client_approved
                      ? 'bg-green-100 text-green-800'
                      : 'bg-orange-100 text-orange-800'
                  }`}>
                    {interactionLog.client_approved ? (
                      <>
                        <CheckCircle className="h-3 w-3" />
                        Đã duyệt
                      </>
                    ) : (
                      <>
                        <Clock className="h-3 w-3" />
                        Chờ duyệt
                      </>
                    )}
                  </span>
                )}
              </div>
              
              <div className="flex items-center gap-4 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <Calendar className="h-4 w-4" />
                  <span>{formatDate(interactionLog.created_at)}</span>
                </div>
                <span>•</span>
                <span>{formatRelativeTime(interactionLog.created_at)}</span>
                <span>•</span>
                <div className="flex items-center gap-1">
                  <User className="h-4 w-4" />
                  <span>Tạo bởi: ID {interactionLog.created_by}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        {/* Content section */}
        <div className="p-6">
          <div className="space-y-6">
            {/* Description */}
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-3">Mô tả</h3>
              <div className="prose max-w-none">
                <p className="text-gray-700 leading-relaxed whitespace-pre-wrap">
                  {interactionLog.description}
                </p>
              </div>
            </div>
            
            {/* Metadata */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Tag path */}
              {interactionLog.tag_path && (
                <div>
                  <h4 className="text-sm font-medium text-gray-900 mb-2">Tag Path</h4>
                  <div className="flex items-center gap-2">
                    <Tag className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-700">{interactionLog.tag_path}</span>
                  </div>
                </div>
              )}
              
              {/* Linked task */}
              {interactionLog.linked_task_id && (
                <div>
                  <h4 className="text-sm font-medium text-gray-900 mb-2">Liên kết Task</h4>
                  <div className="flex items-center gap-2">
                    <ExternalLink className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-700">Task #{interactionLog.linked_task_id}</span>
                  </div>
                </div>
              )}
              
              {/* Project */}
              <div>
                <h4 className="text-sm font-medium text-gray-900 mb-2">Dự án</h4>
                <div className="flex items-center gap-2">
                  <ExternalLink className="h-4 w-4 text-gray-400" />
                  <span className="text-gray-700">Project #{interactionLog.project_id}</span>
                </div>
              </div>
              
              {/* Created info */}
              <div>
                <h4 className="text-sm font-medium text-gray-900 mb-2">Thông tin tạo</h4>
                <div className="space-y-1 text-sm text-gray-700">
                  <div>Người tạo: ID {interactionLog.created_by}</div>
                  <div>Thời gian: {formatDate(interactionLog.created_at)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};