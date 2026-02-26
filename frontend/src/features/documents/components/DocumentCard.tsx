import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/Avatar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { 
  FileText, 
  Download, 
  Eye, 
  Edit, 
  Trash2, 
  MoreVertical,
  Calendar,
  User,
  FileIcon,
  Clock,
  CheckCircle,
  AlertCircle
} from 'lucide-react';
import { Document } from '../types/document';
import { formatDistanceToNow } from 'date-fns';
import { vi } from 'date-fns/locale';
import { cn } from '@/lib/utils';

interface DocumentCardProps {
  document: Document;
  onView?: (document: Document) => void;
  onEdit?: (document: Document) => void;
  onDelete?: (document: Document) => void;
  onDownload?: (document: Document) => void;
  className?: string;
  showActions?: boolean;
  compact?: boolean;
}

/**
 * DocumentCard - Component hiển thị thông tin tài liệu dưới dạng card
 * 
 * Features:
 * - Hiển thị thông tin cơ bản của tài liệu
 * - Actions menu với các thao tác (xem, chỉnh sửa, xóa, tải xuống)
 * - Responsive design
 * - Accessibility support
 * - Status indicators
 * - Version information
 */
export const DocumentCard: React.FC<DocumentCardProps> = ({
  document,
  onView,
  onEdit,
  onDelete,
  onDownload,
  className,
  showActions = true,
  compact = false
}) => {
  // Xác định icon dựa trên loại file
  const getFileIcon = (fileName: string) => {
    const extension = fileName.split('.').pop()?.toLowerCase();
    switch (extension) {
      case 'pdf':
        return '📄';
      case 'doc':
      case 'docx':
        return '📝';
      case 'xls':
      case 'xlsx':
        return '📊';
      case 'ppt':
      case 'pptx':
        return '📋';
      case 'jpg':
      case 'jpeg':
      case 'png':
      case 'gif':
        return '🖼️';
      default:
        return '📄';
    }
  };

  // Xác định màu sắc status
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return 'bg-green-100 text-green-800 border-green-200';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'rejected':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'draft':
        return 'bg-gray-100 text-gray-800 border-gray-200';
      default:
        return 'bg-blue-100 text-blue-800 border-blue-200';
    }
  };

  // Xác định icon status
  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'approved':
        return <CheckCircle className="w-3 h-3" />;
      case 'pending':
        return <Clock className="w-3 h-3" />;
      case 'rejected':
        return <AlertCircle className="w-3 h-3" />;
      default:
        return <FileIcon className="w-3 h-3" />;
    }
  };

  // Format tên status hiển thị
  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'approved':
        return 'Đã duyệt';
      case 'pending':
        return 'Chờ duyệt';
      case 'rejected':
        return 'Từ chối';
      case 'draft':
        return 'Bản nháp';
      default:
        return 'Không xác định';
    }
  };

  return (
    <TooltipProvider>
      <Card 
        className={cn(
          'group hover:shadow-md transition-all duration-200 border-l-4',
          document.status === 'approved' && 'border-l-green-500',
          document.status === 'pending' && 'border-l-yellow-500',
          document.status === 'rejected' && 'border-l-red-500',
          document.status === 'draft' && 'border-l-gray-500',
          compact && 'p-3',
          className
        )}
        role="article"
        aria-label={`Tài liệu ${document.title}`}
      >
        <CardHeader className={cn('pb-3', compact && 'pb-2')}>
          <div className="flex items-start justify-between">
            <div className="flex items-start space-x-3 flex-1 min-w-0">
              {/* File Icon */}
              <div className="flex-shrink-0">
                <div className="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-lg">
                  {getFileIcon(document.current_version?.file_name || '')}
                </div>
              </div>

              {/* Document Info */}
              <div className="flex-1 min-w-0">
                <CardTitle className={cn(
                  'text-sm font-medium text-gray-900 truncate',
                  compact && 'text-xs'
                )}>
                  {document.title}
                </CardTitle>
                
                {!compact && (
                  <p className="text-xs text-gray-500 mt-1 line-clamp-2">
                    {document.description || 'Không có mô tả'}
                  </p>
                )}

                {/* Metadata */}
                <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <div className="flex items-center space-x-1">
                        <Calendar className="w-3 h-3" />
                        <span>
                          {formatDistanceToNow(new Date(document.created_at), {
                            addSuffix: true,
                            locale: vi
                          })}
                        </span>
                      </div>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>Ngày tạo: {new Date(document.created_at).toLocaleDateString('vi-VN')}</p>
                    </TooltipContent>
                  </Tooltip>

                  {document.current_version && (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <div className="flex items-center space-x-1">
                          <FileText className="w-3 h-3" />
                          <span>v{document.current_version.version_number}</span>
                        </div>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>Phiên bản hiện tại</p>
                      </TooltipContent>
                    </Tooltip>
                  )}

                  {document.created_by && (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <div className="flex items-center space-x-1">
                          <User className="w-3 h-3" />
                          <span className="truncate max-w-20">
                            {document.created_by.name}
                          </span>
                        </div>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>Người tạo: {document.created_by.name}</p>
                      </TooltipContent>
                    </Tooltip>
                  )}
                </div>
              </div>
            </div>

            {/* Status & Actions */}
            <div className="flex items-start space-x-2 flex-shrink-0">
              {/* Status Badge */}
              <Badge 
                variant="outline" 
                className={cn(
                  'text-xs flex items-center space-x-1',
                  getStatusColor(document.status)
                )}
              >
                {getStatusIcon(document.status)}
                <span>{getStatusLabel(document.status)}</span>
              </Badge>

              {/* Actions Menu */}
              {showActions && (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                      aria-label="Thao tác với tài liệu"
                    >
                      <MoreVertical className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-48">
                    {onView && (
                      <DropdownMenuItem onClick={() => onView(document)}>
                        <Eye className="mr-2 h-4 w-4" />
                        Xem chi tiết
                      </DropdownMenuItem>
                    )}
                    {onDownload && (
                      <DropdownMenuItem onClick={() => onDownload(document)}>
                        <Download className="mr-2 h-4 w-4" />
                        Tải xuống
                      </DropdownMenuItem>
                    )}
                    {onEdit && (
                      <DropdownMenuItem onClick={() => onEdit(document)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Chỉnh sửa
                      </DropdownMenuItem>
                    )}
                    {onDelete && (
                      <DropdownMenuItem 
                        onClick={() => onDelete(document)}
                        className="text-red-600 focus:text-red-600"
                      >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Xóa
                      </DropdownMenuItem>
                    )}
                  </DropdownMenuContent>
                </DropdownMenu>
              )}
            </div>
          </div>
        </CardHeader>

        {!compact && (
          <CardContent className="pt-0">
            {/* Linked Entity Info */}
            {document.linked_entity_type && document.linked_entity_id && (
              <div className="flex items-center space-x-2 text-xs text-gray-500 bg-gray-50 rounded-md p-2">
                <FileText className="w-3 h-3" />
                <span>
                  Liên kết với {document.linked_entity_type === 'task' ? 'Công việc' : 
                    document.linked_entity_type === 'diary' ? 'Nhật ký' : 'Yêu cầu thay đổi'} 
                  #{document.linked_entity_id}
                </span>
              </div>
            )}

            {/* Quick Actions */}
            <div className="flex items-center space-x-2 mt-3">
              {onView && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => onView(document)}
                  className="flex-1"
                >
                  <Eye className="mr-2 h-3 w-3" />
                  Xem
                </Button>
              )}
              {onDownload && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => onDownload(document)}
                  className="flex-1"
                >
                  <Download className="mr-2 h-3 w-3" />
                  Tải
                </Button>
              )}
            </div>
          </CardContent>
        )}
      </Card>
    </TooltipProvider>
  );
};

export default DocumentCard;