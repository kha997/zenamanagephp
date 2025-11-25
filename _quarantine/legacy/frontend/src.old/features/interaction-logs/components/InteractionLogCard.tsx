import React from 'react';
import { 
  Eye, 
  Phone, 
  Mail, 
  Users, 
  FileText, 
  MessageSquare,
  Calendar,
  Tag,
  User,
  CheckCircle,
  Clock,
  ExternalLink
} from 'lucide-react';
import { InteractionLog, InteractionType, VisibilityType } from '../types/interactionLog';
import { formatDate, formatRelativeTime } from '../utils/date';

interface InteractionLogCardProps {
  interactionLog: InteractionLog;
  isSelected: boolean;
  onSelect: () => void;
  onView: () => void;
  showActions?: boolean;
}

/**
 * InteractionLogCard component để hiển thị thông tin interaction log trong danh sách
 */
export const InteractionLogCard: React.FC<InteractionLogCardProps> = ({
  interactionLog,
  isSelected,
  onSelect,
  onView,
  showActions = false
}) => {
  /**
   * Lấy icon cho loại interaction
   */
  const getTypeIcon = (type: InteractionType) => {
    const iconClass = "h-5 w-5";
    
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
   * Lấy màu cho visibility
   */
  const getVisibilityColor = (visibility: VisibilityType) => {
    switch (visibility) {
      case 'internal':
        return 'bg-gray-100 text-gray-800';
      case 'client':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  /**
   * Truncate description nếu quá dài
   */
  const truncateDescription = (text: string, maxLength: number = 150) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  };

  return (
    <div className={`bg-white rounded-lg shadow-sm border transition-all duration-200 hover:shadow-md ${
      isSelected ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200'
    }`}>
      <div className="p-4">
        {/* Header */}
        <div className="flex items-start justify-between mb-3">
          <div className="flex items-center gap-3">
            <input
              type="checkbox"
              checked={isSelected}
              onChange={onSelect}
              className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            
            <div className={`p-2 rounded-lg ${getTypeColor(interactionLog.type)}`}>
              {getTypeIcon(interactionLog.type)}
            </div>
            
            <div>
              <h3 className="font-medium text-gray-900">
                {getTypeLabel(interactionLog.type)}
              </h3>
              <div className="flex items-center gap-2 text-sm text-gray-500">
                <Calendar className="h-4 w-4" />
                <span>{formatDate(interactionLog.created_at)}</span>
                <span>•</span>
                <span>{formatRelativeTime(interactionLog.created_at)}</span>
              </div>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            {/* Approval status */}
            {interactionLog.visibility === 'client' && (
              <div className="flex items-center gap-1">
                {interactionLog.client_approved ? (
                  <CheckCircle className="h-4 w-4 text-green-500" title="Đã duyệt" />
                ) : (
                  <Clock className="h-4 w-4 text-orange-500" title="Chờ duyệt" />
                )}
              </div>
            )}
            
            {/* View button */}
            <button
              onClick={onView}
              className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors"
              title="Xem chi tiết"
            >
              <ExternalLink className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Description */}
        <div className="mb-3">
          <p className="text-gray-700 leading-relaxed">
            {truncateDescription(interactionLog.description)}
          </p>
        </div>

        {/* Tags and metadata */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 flex-wrap">
            {/* Visibility badge */}
            <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${
              getVisibilityColor(interactionLog.visibility)
            }`}>
              <Eye className="h-3 w-3" />
              {getVisibilityLabel(interactionLog.visibility)}
            </span>
            
            {/* Tag path */}
            {interactionLog.tag_path && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                <Tag className="h-3 w-3" />
                {interactionLog.tag_path}
              </span>
            )}
            
            {/* Linked task */}
            {interactionLog.linked_task_id && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                Task #{interactionLog.linked_task_id}
              </span>
            )}
          </div>
          
          {/* Created by */}
          <div className="flex items-center gap-1 text-sm text-gray-500">
            <User className="h-4 w-4" />
            <span>ID: {interactionLog.created_by}</span>
          </div>
        </div>
      </div>
    </div>
  );
};