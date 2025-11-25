import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  FileText, 
  Download, 
  Edit, 
  Upload, 
  History, 
  User, 
  Calendar, 
  Link, 
  Eye, 
  MoreVertical,
  CheckCircle,
  Clock,
  AlertCircle,
  FileIcon,
  ArrowLeft,
  Share,
  Trash2
} from 'lucide-react';
import { Document, DocumentVersion } from '../types/document';
import { formatDistanceToNow } from 'date-fns';
import { vi } from 'date-fns/locale';
import { cn } from '@/lib/utils';

interface DocumentDetailProps {
  document: Document;
  versions?: DocumentVersion[];
  onEdit?: () => void;
  onUploadVersion?: () => void;
  onDownload?: (version?: DocumentVersion) => void;
  onRevertVersion?: (version: DocumentVersion) => void;
  onDelete?: () => void;
  onBack?: () => void;
  loading?: boolean;
  className?: string;
}

/**
 * DocumentDetail - Component hiển thị chi tiết tài liệu và lịch sử phiên bản
 * 
 * Features:
 * - Hiển thị thông tin chi tiết tài liệu
 * - Lịch sử các phiên bản
 * - Actions: edit, upload version, download, delete
 * - Version management (revert, download specific version)
 * - Responsive design
 * - Status indicators
 */
export const DocumentDetail: React.FC<DocumentDetailProps> = ({
  document,
  versions = [],
  onEdit,
  onUploadVersion,
  onDownload,
  onRevertVersion,
  onDelete,
  onBack,
  loading = false,
  className
}) => {
  const [activeTab, setActiveTab] = useState<'info' | 'versions'>('info');

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
        return <CheckCircle className="w-4 h-4" />;
      case 'pending':
        return <Clock className="w-4 h-4" />;
      case 'rejected':
        return <AlertCircle className="w-4 h-4" />;
      default:
        return <FileIcon className="w-4 h-4" />;
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

  // Format file size
  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <TooltipProvider>
      <div className={cn('space-y-6', className)}>
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            {onBack && (
              <Button variant="ghost" size="sm" onClick={onBack}>
                <ArrowLeft className="h-4 w-4 mr-2" />
                Quay lại
              </Button>
            )}
            <div>
              <h1 className="text-2xl font-bold text-gray-900">{document.title}</h1>
              <p className="text-gray-600">Chi tiết tài liệu</p>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            {onEdit && (
              <Button variant="outline" onClick={onEdit}>
                <Edit className="h-4 w-4 mr-2" />
                Chỉnh sửa
              </Button>
            )}
            {onUploadVersion && (
              <Button variant="outline" onClick={onUploadVersion}>
                <Upload className="h-4 w-4 mr-2" />
                Upload phiên bản
              </Button>
            )}
            {onDownload && (
              <Button onClick={() => onDownload()}>
                <Download className="h-4 w-4 mr-2" />
                Tải xuống
              </Button>
            )}
          </div>
        </div>

        {/* Tabs */}
        <div className="border-b">
          <nav className="flex space-x-8">
            <button
              onClick={() => setActiveTab('info')}
              className={cn(
                'py-2 px-1 border-b-2 font-medium text-sm',
                activeTab === 'info'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              )}
            >
              <FileText className="h-4 w-4 mr-2 inline" />
              Thông tin
            </button>
            <button
              onClick={() => setActiveTab('versions')}
              className={cn(
                'py-2 px-1 border-b-2 font-medium text-sm',
                activeTab === 'versions'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              )}
            >
              <History className="h-4 w-4 mr-2 inline" />
              Lịch sử phiên bản ({versions.length})
            </button>
          </nav>
        </div>

        {/* Tab Content */}
        {activeTab === 'info' && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Main Info */}
            <div className="lg:col-span-2 space-y-6">
              {/* Document Overview */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <div className="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center text-lg">
                      {getFileIcon(document.current_version?.file_name || '')}
                    </div>
                    <div>
                      <h3 className="text-lg font-semibold">{document.title}</h3>
                      <p className="text-sm text-gray-500">
                        {document.current_version?.file_name}
                      </p>
                    </div>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* Description */}
                  {document.description && (
                    <div>
                      <h4 className="font-medium text-gray-900 mb-2">Mô tả</h4>
                      <p className="text-gray-700 whitespace-pre-wrap">
                        {document.description}
                      </p>
                    </div>
                  )}

                  {/* Linked Entity */}
                  {document.linked_entity_type && document.linked_entity_id && (
                    <div>
                      <h4 className="font-medium text-gray-900 mb-2">Liên kết</h4>
                      <div className="flex items-center space-x-2 text-sm text-gray-600 bg-gray-50 rounded-md p-3">
                        <Link className="w-4 h-4" />
                        <span>
                          {document.linked_entity_type === 'task' ? 'Công việc' : 
                           document.linked_entity_type === 'diary' ? 'Nhật ký' : 'Yêu cầu thay đổi'} 
                          #{document.linked_entity_id}
                        </span>
                      </div>
                    </div>
                  )}

                  {/* Current Version Info */}
                  {document.current_version && (
                    <div>
                      <h4 className="font-medium text-gray-900 mb-2">Phiên bản hiện tại</h4>
                      <div className="bg-blue-50 rounded-lg p-4 space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="font-medium text-blue-900">
                            Phiên bản {document.current_version.version_number}
                          </span>
                          <Badge variant="outline" className="bg-blue-100 text-blue-800">
                            Hiện tại
                          </Badge>
                        </div>
                        {document.current_version.comment && (
                          <p className="text-sm text-blue-800">
                            {document.current_version.comment}
                          </p>
                        )}
                        <div className="flex items-center space-x-4 text-xs text-blue-700">
                          <span>Kích thước: {formatFileSize(document.current_version.file_size || 0)}</span>
                          <span>
                            Cập nhật: {formatDistanceToNow(new Date(document.current_version.created_at), {
                              addSuffix: true,
                              locale: vi
                            })}
                          </span>
                        </div>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Sidebar */}
            <div className="space-y-6">
              {/* Status & Actions */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Trạng thái & Thao tác</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* Status */}
                  <div>
                    <Label className="text-sm font-medium text-gray-700">Trạng thái</Label>
                    <Badge 
                      variant="outline" 
                      className={cn(
                        'flex items-center space-x-1 w-fit mt-1',
                        getStatusColor(document.status)
                      )}
                    >
                      {getStatusIcon(document.status)}
                      <span>{getStatusLabel(document.status)}</span>
                    </Badge>
                  </div>

                  <Separator />

                  {/* Quick Actions */}
                  <div className="space-y-2">
                    {onDownload && (
                      <Button 
                        variant="outline" 
                        size="sm" 
                        onClick={() => onDownload()}
                        className="w-full justify-start"
                      >
                        <Download className="h-4 w-4 mr-2" />
                        Tải xuống
                      </Button>
                    )}
                    {onEdit && (
                      <Button 
                        variant="outline" 
                        size="sm" 
                        onClick={onEdit}
                        className="w-full justify-start"
                      >
                        <Edit className="h-4 w-4 mr-2" />
                        Chỉnh sửa
                      </Button>
                    )}
                    {onUploadVersion && (
                      <Button 
                        variant="outline" 
                        size="sm" 
                        onClick={onUploadVersion}
                        className="w-full justify-start"
                      >
                        <Upload className="h-4 w-4 mr-2" />
                        Upload phiên bản mới
                      </Button>
                    )}
                    <Button 
                      variant="outline" 
                      size="sm" 
                      className="w-full justify-start"
                    >
                      <Share className="h-4 w-4 mr-2" />
                      Chia sẻ
                    </Button>
                    {onDelete && (
                      <Button 
                        variant="outline" 
                        size="sm" 
                        onClick={onDelete}
                        className="w-full justify-start text-red-600 hover:text-red-700"
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Xóa tài liệu
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Metadata */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">Thông tin</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {/* Created By */}
                  {document.created_by && (
                    <div className="flex items-center space-x-3">
                      <User className="h-4 w-4 text-gray-400" />
                      <div>
                        <p className="text-sm font-medium text-gray-900">
                          {document.created_by.name}
                        </p>
                        <p className="text-xs text-gray-500">Người tạo</p>
                      </div>
                    </div>
                  )}

                  {/* Created Date */}
                  <div className="flex items-center space-x-3">
                    <Calendar className="h-4 w-4 text-gray-400" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {new Date(document.created_at).toLocaleDateString('vi-VN')}
                      </p>
                      <p className="text-xs text-gray-500">Ngày tạo</p>
                    </div>
                  </div>

                  {/* Last Updated */}
                  <div className="flex items-center space-x-3">
                    <Clock className="h-4 w-4 text-gray-400" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {formatDistanceToNow(new Date(document.updated_at), {
                          addSuffix: true,
                          locale: vi
                        })}
                      </p>
                      <p className="text-xs text-gray-500">Cập nhật lần cuối</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        )}

        {activeTab === 'versions' && (
          <Card>
            <CardHeader>
              <CardTitle>Lịch sử phiên bản</CardTitle>
            </CardHeader>
            <CardContent>
              {versions.length === 0 ? (
                <div className="text-center py-8">
                  <History className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">
                    Chưa có lịch sử phiên bản
                  </h3>
                  <p className="text-gray-500">
                    Các phiên bản của tài liệu sẽ được hiển thị ở đây
                  </p>
                </div>
              ) : (
                <div className="space-y-4">
                  {versions.map((version, index) => (
                    <div 
                      key={version.id}
                      className={cn(
                        'flex items-center justify-between p-4 border rounded-lg',
                        version.id === document.current_version_id 
                          ? 'bg-blue-50 border-blue-200' 
                          : 'bg-white border-gray-200'
                      )}
                    >
                      <div className="flex items-center space-x-4">
                        <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                          <span className="text-sm font-medium text-gray-600">
                            v{version.version_number}
                          </span>
                        </div>
                        <div>
                          <div className="flex items-center space-x-2">
                            <p className="font-medium text-gray-900">
                              Phiên bản {version.version_number}
                            </p>
                            {version.id === document.current_version_id && (
                              <Badge variant="outline" className="bg-blue-100 text-blue-800">
                                Hiện tại
                              </Badge>
                            )}
                            {version.reverted_from_version_number && (
                              <Badge variant="outline" className="bg-orange-100 text-orange-800">
                                Khôi phục từ v{version.reverted_from_version_number}
                              </Badge>
                            )}
                          </div>
                          {version.comment && (
                            <p className="text-sm text-gray-600 mt-1">
                              {version.comment}
                            </p>
                          )}
                          <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                            <span>
                              {formatDistanceToNow(new Date(version.created_at), {
                                addSuffix: true,
                                locale: vi
                              })}
                            </span>
                            {version.created_by && (
                              <span>bởi {version.created_by.name}</span>
                            )}
                            <span>{formatFileSize(version.file_size || 0)}</span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center space-x-2">
                        {onDownload && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onDownload(version)}
                              >
                                <Download className="h-4 w-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>Tải xuống phiên bản này</p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                        
                        {onRevertVersion && version.id !== document.current_version_id && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => onRevertVersion(version)}
                              >
                                <History className="h-4 w-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>Khôi phục phiên bản này</p>
                            </TooltipContent>
                          </Tooltip>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </TooltipProvider>
  );
};

export default DocumentDetail;