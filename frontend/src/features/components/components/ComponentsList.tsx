import React, { useState } from 'react';
import { useComponents, useComponentFilters } from '../hooks/useComponents';
import { ComponentCard } from './index';
import { ComponentsFilterBar } from './index';
import { ComponentFilters } from '../types/component';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, Plus, Grid, List } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface ComponentsListProps {
  projectId: string;
  onCreateComponent?: () => void;
  onSelectComponent?: (componentId: string) => void;
  selectedComponentId?: string;
}

export default function ComponentsList({
  projectId,
  onCreateComponent,
  onSelectComponent,
  selectedComponentId
}: ComponentsListProps) {
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [page, setPage] = useState(1);
  const pageSize = 12;

  const {
    filters,
    updateFilters,
    resetFilters,
    debouncedFilters
  } = useComponentFilters();

  const {
    data: componentsData,
    isLoading,
    error,
    refetch
  } = useComponents({
    projectId,
    filters: {
      ...debouncedFilters,
      page,
      limit: pageSize
    }
  });

  const components = componentsData?.data || [];
  const totalPages = Math.ceil((componentsData?.total || 0) / pageSize);

  const handleFilterChange = (newFilters: Partial<ComponentFilters>) => {
    updateFilters(newFilters);
    setPage(1); // Reset to first page when filters change
  };

  const handleReset = () => {
    resetFilters();
    setPage(1);
  };

  const handlePageChange = (newPage: number) => {
    setPage(newPage);
  };

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertDescription>
          Có lỗi xảy ra khi tải danh sách components: {error.message}
          <Button 
            variant="outline" 
            size="sm" 
            onClick={() => refetch()}
            className="ml-2"
          >
            Thử lại
          </Button>
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Components</h2>
          <p className="text-muted-foreground">
            Quản lý các thành phần của dự án
          </p>
        </div>
        <div className="flex items-center gap-2">
          {/* View Mode Toggle */}
          <div className="flex items-center border rounded-lg p-1">
            <Button
              variant={viewMode === 'grid' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('grid')}
            >
              <Grid className="h-4 w-4" />
            </Button>
            <Button
              variant={viewMode === 'list' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('list')}
            >
              <List className="h-4 w-4" />
            </Button>
          </div>
          
          {onCreateComponent && (
            <Button onClick={onCreateComponent}>
              <Plus className="h-4 w-4 mr-2" />
              Thêm Component
            </Button>
          )}
        </div>
      </div>

      {/* Filter Bar */}
      <ComponentsFilterBar
        filters={filters}
        onFiltersChange={handleFilterChange}
        onReset={handleReset}
      />

      {/* Loading State */}
      {isLoading && (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin" />
          <span className="ml-2">Đang tải components...</span>
        </div>
      )}

      {/* Empty State */}
      {!isLoading && components.length === 0 && (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <div className="text-center">
              <h3 className="text-lg font-semibold mb-2">
                {Object.keys(debouncedFilters).length > 0 
                  ? 'Không tìm thấy component nào'
                  : 'Chưa có component nào'
                }
              </h3>
              <p className="text-muted-foreground mb-4">
                {Object.keys(debouncedFilters).length > 0
                  ? 'Thử điều chỉnh bộ lọc để tìm thấy kết quả phù hợp'
                  : 'Bắt đầu bằng cách tạo component đầu tiên cho dự án'
                }
              </p>
              {Object.keys(debouncedFilters).length > 0 ? (
                <Button variant="outline" onClick={handleReset}>
                  Xóa bộ lọc
                </Button>
              ) : (
                onCreateComponent && (
                  <Button onClick={onCreateComponent}>
                    <Plus className="h-4 w-4 mr-2" />
                    Tạo Component đầu tiên
                  </Button>
                )
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Components Grid/List */}
      {!isLoading && components.length > 0 && (
        <div className={`
          ${viewMode === 'grid' 
            ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' 
            : 'space-y-4'
          }
        `}>
          {components.map((component) => (
            <ComponentCard
              key={component.id}
              component={component}
              onClick={() => onSelectComponent?.(component.id)}
              isSelected={selectedComponentId === component.id}
              viewMode={viewMode}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {!isLoading && totalPages > 1 && (
        <div className="flex items-center justify-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(page - 1)}
            disabled={page <= 1}
          >
            Trước
          </Button>
          
          <div className="flex items-center space-x-1">
            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
              let pageNumber;
              if (totalPages <= 5) {
                pageNumber = i + 1;
              } else if (page <= 3) {
                pageNumber = i + 1;
              } else if (page >= totalPages - 2) {
                pageNumber = totalPages - 4 + i;
              } else {
                pageNumber = page - 2 + i;
              }
              
              return (
                <Button
                  key={pageNumber}
                  variant={page === pageNumber ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => handlePageChange(pageNumber)}
                >
                  {pageNumber}
                </Button>
              );
            })}
          </div>
          
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(page + 1)}
            disabled={page >= totalPages}
          >
            Sau
          </Button>
        </div>
      )}

      {/* Results Summary */}
      {!isLoading && components.length > 0 && (
        <div className="text-sm text-muted-foreground text-center">
          Hiển thị {((page - 1) * pageSize) + 1} - {Math.min(page * pageSize, componentsData?.total || 0)} 
          trong tổng số {componentsData?.total || 0} components
        </div>
      )}
    </div>
  );
}