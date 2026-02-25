import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  Search, 
  Filter, 
  SortAsc, 
  SortDesc, 
  Grid, 
  List, 
  Plus,
  FileText,
  AlertCircle,
  RefreshCw
} from 'lucide-react';
import { Document, DocumentFilters } from '../types/document';
import { DocumentCard } from './DocumentCard';
import { cn } from '@/lib/utils';

interface DocumentListProps {
  documents: Document[];
  loading?: boolean;
  error?: string | null;
  onDocumentView?: (document: Document) => void;
  onDocumentEdit?: (document: Document) => void;
  onDocumentDelete?: (document: Document) => void;
  onDocumentDownload?: (document: Document) => void;
  onCreateDocument?: () => void;
  onRefresh?: () => void;
  filters?: DocumentFilters;
  onFiltersChange?: (filters: DocumentFilters) => void;
  className?: string;
  showCreateButton?: boolean;
  showFilters?: boolean;
  emptyStateMessage?: string;
}

type ViewMode = 'grid' | 'list';
type SortField = 'title' | 'created_at' | 'updated_at' | 'status';
type SortOrder = 'asc' | 'desc';

/**
 * DocumentList - Component hiển thị danh sách tài liệu với tính năng tìm kiếm, lọc và sắp xếp
 * 
 * Features:
 * - Tìm kiếm theo tên tài liệu
 * - Lọc theo trạng thái, loại liên kết
 * - Sắp xếp theo nhiều tiêu chí
 * - Chuyển đổi chế độ hiển thị (grid/list)
 * - Responsive design
 * - Loading và error states
 * - Empty state
 */
export const DocumentList: React.FC<DocumentListProps> = ({
  documents,
  loading = false,
  error = null,
  onDocumentView,
  onDocumentEdit,
  onDocumentDelete,
  onDocumentDownload,
  onCreateDocument,
  onRefresh,
  filters,
  onFiltersChange,
  className,
  showCreateButton = true,
  showFilters = true,
  emptyStateMessage = 'Không có tài liệu nào được tìm thấy'
}) => {
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [sortField, setSortField] = useState<SortField>('created_at');
  const [sortOrder, setSortOrder] = useState<SortOrder>('desc');
  const [searchQuery, setSearchQuery] = useState('');
  const [localFilters, setLocalFilters] = useState<DocumentFilters>({
    status: '',
    linked_entity_type: '',
    ...filters
  });

  // Xử lý thay đổi filters
  const handleFiltersChange = (newFilters: Partial<DocumentFilters>) => {
    const updatedFilters = { ...localFilters, ...newFilters };
    setLocalFilters(updatedFilters);
    onFiltersChange?.(updatedFilters);
  };

  // Lọc và sắp xếp documents
  const filteredAndSortedDocuments = useMemo(() => {
    let filtered = documents;

    // Tìm kiếm theo tên
    if (searchQuery) {
      filtered = filtered.filter(doc => 
        doc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
        doc.description?.toLowerCase().includes(searchQuery.toLowerCase())
      );
    }

    // Lọc theo status
    if (localFilters.status) {
      filtered = filtered.filter(doc => doc.status === localFilters.status);
    }

    // Lọc theo linked entity type
    if (localFilters.linked_entity_type) {
      filtered = filtered.filter(doc => doc.linked_entity_type === localFilters.linked_entity_type);
    }

    // Sắp xếp
    filtered.sort((a, b) => {
      let aValue: any;
      let bValue: any;

      switch (sortField) {
        case 'title':
          aValue = a.title.toLowerCase();
          bValue = b.title.toLowerCase();
          break;
        case 'created_at':
          aValue = new Date(a.created_at);
          bValue = new Date(b.created_at);
          break;
        case 'updated_at':
          aValue = new Date(a.updated_at);
          bValue = new Date(b.updated_at);
          break;
        case 'status':
          aValue = a.status;
          bValue = b.status;
          break;
        default:
          return 0;
      }

      if (aValue < bValue) return sortOrder === 'asc' ? -1 : 1;
      if (aValue > bValue) return sortOrder === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }, [documents, searchQuery, localFilters, sortField, sortOrder]);

  // Toggle sort order
  const toggleSortOrder = () => {
    setSortOrder(prev => prev === 'asc' ? 'desc' : 'asc');
  };

  // Render loading skeleton
  const renderLoadingSkeleton = () => (
    <div className={cn(
      'grid gap-4',
      viewMode === 'grid' ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1'
    )}>
      {Array.from({ length: 6 }).map((_, index) => (
        <Card key={index}>
          <CardHeader>
            <div className="flex items-start space-x-3">
              <Skeleton className="w-10 h-10 rounded-lg" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-4 w-3/4" />
                <Skeleton className="h-3 w-1/2" />
                <div className="flex space-x-2">
                  <Skeleton className="h-3 w-16" />
                  <Skeleton className="h-3 w-12" />
                </div>
              </div>
              <Skeleton className="h-6 w-16" />
            </div>
          </CardHeader>
        </Card>
      ))}
    </div>
  );

  // Render empty state
  const renderEmptyState = () => (
    <Card className="text-center py-12">
      <CardContent>
        <FileText className="mx-auto h-12 w-12 text-gray-400 mb-4" />
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          {emptyStateMessage}
        </h3>
        <p className="text-gray-500 mb-6">
          {searchQuery || Object.values(localFilters).some(Boolean) 
            ? 'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm'
            : 'Bắt đầu bằng cách tạo tài liệu đầu tiên'
          }
        </p>
        {showCreateButton && onCreateDocument && (
          <Button onClick={onCreateDocument}>
            <Plus className="mr-2 h-4 w-4" />
            Tạo tài liệu mới
          </Button>
        )}
      </CardContent>
    </Card>
  );

  return (
    <div className={cn('space-y-6', className)}>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">Tài liệu</h2>
          <p className="text-gray-600">
            Quản lý tài liệu dự án ({filteredAndSortedDocuments.length} tài liệu)
          </p>
        </div>
        
        <div className="flex items-center space-x-2">
          {onRefresh && (
            <Button
              variant="outline"
              size="sm"
              onClick={onRefresh}
              disabled={loading}
            >
              <RefreshCw className={cn('h-4 w-4', loading && 'animate-spin')} />
            </Button>
          )}
          
          {showCreateButton && onCreateDocument && (
            <Button onClick={onCreateDocument}>
              <Plus className="mr-2 h-4 w-4" />
              Tạo mới
            </Button>
          )}
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Filters & Controls */}
      {showFilters && (
        <Card>
          <CardContent className="pt-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
              {/* Search */}
              <div className="relative col-span-1 md:col-span-2">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                <Input
                  placeholder="Tìm kiếm tài liệu..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10"
                />
              </div>

              {/* Status Filter */}
              <Select
                value={localFilters.status || ''}
                onValueChange={(value) => handleFiltersChange({ status: value || undefined })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Tất cả trạng thái</SelectItem>
                  <SelectItem value="draft">Bản nháp</SelectItem>
                  <SelectItem value="pending">Chờ duyệt</SelectItem>
                  <SelectItem value="approved">Đã duyệt</SelectItem>
                  <SelectItem value="rejected">Từ chối</SelectItem>
                </SelectContent>
              </Select>

              {/* Linked Entity Type Filter */}
              <Select
                value={localFilters.linked_entity_type || ''}
                onValueChange={(value) => handleFiltersChange({ linked_entity_type: value || undefined })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Loại liên kết" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">Tất cả loại</SelectItem>
                  <SelectItem value="task">Công việc</SelectItem>
                  <SelectItem value="diary">Nhật ký</SelectItem>
                  <SelectItem value="cr">Yêu cầu thay đổi</SelectItem>
                </SelectContent>
              </Select>

              {/* Sort */}
              <Select
                value={sortField}
                onValueChange={(value) => setSortField(value as SortField)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Sắp xếp theo" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="created_at">Ngày tạo</SelectItem>
                  <SelectItem value="updated_at">Ngày cập nhật</SelectItem>
                  <SelectItem value="title">Tên tài liệu</SelectItem>
                  <SelectItem value="status">Trạng thái</SelectItem>
                </SelectContent>
              </Select>

              {/* View Mode & Sort Order */}
              <div className="flex items-center space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={toggleSortOrder}
                  className="flex-1"
                >
                  {sortOrder === 'asc' ? (
                    <SortAsc className="h-4 w-4" />
                  ) : (
                    <SortDesc className="h-4 w-4" />
                  )}
                </Button>
                
                <div className="flex border rounded-md">
                  <Button
                    variant={viewMode === 'grid' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => setViewMode('grid')}
                    className="rounded-r-none"
                  >
                    <Grid className="h-4 w-4" />
                  </Button>
                  <Button
                    variant={viewMode === 'list' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => setViewMode('list')}
                    className="rounded-l-none"
                  >
                    <List className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            {/* Active Filters */}
            {(searchQuery || Object.values(localFilters).some(Boolean)) && (
              <div className="flex items-center space-x-2 mt-4 pt-4 border-t">
                <span className="text-sm text-gray-500">Bộ lọc đang áp dụng:</span>
                {searchQuery && (
                  <Badge variant="secondary" className="text-xs">
                    Tìm kiếm: "{searchQuery}"
                  </Badge>
                )}
                {localFilters.status && (
                  <Badge variant="secondary" className="text-xs">
                    Trạng thái: {localFilters.status}
                  </Badge>
                )}
                {localFilters.linked_entity_type && (
                  <Badge variant="secondary" className="text-xs">
                    Loại: {localFilters.linked_entity_type}
                  </Badge>
                )}
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setSearchQuery('');
                    setLocalFilters({ status: '', linked_entity_type: '' });
                    onFiltersChange?.({ status: '', linked_entity_type: '' });
                  }}
                  className="text-xs h-6 px-2"
                >
                  Xóa bộ lọc
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Content */}
      {loading ? (
        renderLoadingSkeleton()
      ) : error ? (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : filteredAndSortedDocuments.length === 0 ? (
        renderEmptyState()
      ) : (
        <div className={cn(
          'grid gap-4',
          viewMode === 'grid' 
            ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3' 
            : 'grid-cols-1'
        )}>
          {filteredAndSortedDocuments.map((document) => (
            <DocumentCard
              key={document.id}
              document={document}
              onView={onDocumentView}
              onEdit={onDocumentEdit}
              onDelete={onDocumentDelete}
              onDownload={onDocumentDownload}
              compact={viewMode === 'list'}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default DocumentList;