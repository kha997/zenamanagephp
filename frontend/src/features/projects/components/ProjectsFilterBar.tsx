import React, { useState, useCallback } from 'react';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { 
  Search, 
  Filter, 
  X, 
  Calendar,
  DollarSign,
  TrendingUp,
  ChevronDown
} from 'lucide-react';
import { useDebounce } from '@/hooks/useDebounce';
import { cn } from '@/lib/utils';

export interface ProjectsFilters {
  search: string;
  status: string[];
  dateRange: {
    start?: string;
    end?: string;
  };
  progressRange: {
    min?: number;
    max?: number;
  };
  costRange: {
    min?: number;
    max?: number;
  };
}

interface ProjectsFilterBarProps {
  filters: ProjectsFilters;
  onFiltersChange: (filters: ProjectsFilters) => void;
  totalCount: number;
  filteredCount: number;
  className?: string;
}

const STATUS_OPTIONS = [
  { value: 'active', label: 'Đang thực hiện', color: 'bg-green-100 text-green-800' },
  { value: 'completed', label: 'Hoàn thành', color: 'bg-blue-100 text-blue-800' },
  { value: 'on_hold', label: 'Tạm dừng', color: 'bg-yellow-100 text-yellow-800' },
  { value: 'cancelled', label: 'Đã hủy', color: 'bg-red-100 text-red-800' }
];

export const ProjectsFilterBar: React.FC<ProjectsFilterBarProps> = ({
  filters,
  onFiltersChange,
  totalCount,
  filteredCount,
  className
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [searchValue, setSearchValue] = useState(filters.search);
  
  // Debounce search để tránh gọi API quá nhiều
  const debouncedSearch = useDebounce(searchValue, 300);
  
  React.useEffect(() => {
    if (debouncedSearch !== filters.search) {
      onFiltersChange({ ...filters, search: debouncedSearch });
    }
  }, [debouncedSearch]);

  const handleStatusToggle = useCallback((status: string) => {
    const newStatus = filters.status.includes(status)
      ? filters.status.filter(s => s !== status)
      : [...filters.status, status];
    
    onFiltersChange({ ...filters, status: newStatus });
  }, [filters, onFiltersChange]);

  const handleDateRangeChange = useCallback((field: 'start' | 'end', value: string) => {
    onFiltersChange({
      ...filters,
      dateRange: { ...filters.dateRange, [field]: value }
    });
  }, [filters, onFiltersChange]);

  const handleProgressRangeChange = useCallback((field: 'min' | 'max', value: number) => {
    onFiltersChange({
      ...filters,
      progressRange: { ...filters.progressRange, [field]: value }
    });
  }, [filters, onFiltersChange]);

  const clearAllFilters = useCallback(() => {
    setSearchValue('');
    onFiltersChange({
      search: '',
      status: [],
      dateRange: {},
      progressRange: {},
      costRange: {}
    });
  }, [onFiltersChange]);

  const hasActiveFilters = filters.search || 
    filters.status.length > 0 || 
    Object.keys(filters.dateRange).length > 0 ||
    Object.keys(filters.progressRange).length > 0 ||
    Object.keys(filters.costRange).length > 0;

  return (
    <div className={cn('bg-white border rounded-lg p-4 space-y-4', className)}>
      {/* Search và Quick Actions */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
          <Input
            placeholder="Tìm kiếm dự án theo tên, mô tả..."
            value={searchValue}
            onChange={(e) => setSearchValue(e.target.value)}
            className="pl-10 pr-4"
          />
          {searchValue && (
            <button
              onClick={() => setSearchValue('')}
              className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>
        
        <div className="flex gap-2">
          <Button
            variant={isExpanded ? 'default' : 'outline'}
            onClick={() => setIsExpanded(!isExpanded)}
            className="flex items-center gap-2"
          >
            <Filter className="w-4 h-4" />
            Bộ lọc
            <ChevronDown className={cn(
              'w-4 h-4 transition-transform',
              isExpanded && 'rotate-180'
            )} />
          </Button>
          
          {hasActiveFilters && (
            <Button
              variant="ghost"
              onClick={clearAllFilters}
              className="text-red-600 hover:text-red-700 hover:bg-red-50"
            >
              <X className="w-4 h-4 mr-1" />
              Xóa bộ lọc
            </Button>
          )}
        </div>
      </div>

      {/* Quick Status Filters */}
      <div className="flex flex-wrap gap-2">
        {STATUS_OPTIONS.map((option) => {
          const isSelected = filters.status.includes(option.value);
          return (
            <Badge
              key={option.value}
              className={cn(
                'cursor-pointer transition-all hover:scale-105',
                isSelected ? option.color : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              )}
              onClick={() => handleStatusToggle(option.value)}
            >
              {option.label}
              {isSelected && <X className="w-3 h-3 ml-1" />}
            </Badge>
          );
        })}
      </div>

      {/* Advanced Filters */}
      {isExpanded && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t">
          {/* Date Range */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700 flex items-center gap-1">
              <Calendar className="w-4 h-4" />
              Khoảng thời gian
            </label>
            <div className="grid grid-cols-2 gap-2">
              <Input
                type="date"
                placeholder="Từ ngày"
                value={filters.dateRange.start || ''}
                onChange={(e) => handleDateRangeChange('start', e.target.value)}
                className="text-sm"
              />
              <Input
                type="date"
                placeholder="Đến ngày"
                value={filters.dateRange.end || ''}
                onChange={(e) => handleDateRangeChange('end', e.target.value)}
                className="text-sm"
              />
            </div>
          </div>

          {/* Progress Range */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700 flex items-center gap-1">
              <TrendingUp className="w-4 h-4" />
              Tiến độ (%)
            </label>
            <div className="grid grid-cols-2 gap-2">
              <Input
                type="number"
                placeholder="Từ %"
                min="0"
                max="100"
                value={filters.progressRange.min || ''}
                onChange={(e) => handleProgressRangeChange('min', parseInt(e.target.value) || 0)}
                className="text-sm"
              />
              <Input
                type="number"
                placeholder="Đến %"
                min="0"
                max="100"
                value={filters.progressRange.max || ''}
                onChange={(e) => handleProgressRangeChange('max', parseInt(e.target.value) || 100)}
                className="text-sm"
              />
            </div>
          </div>

          {/* Cost Range */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700 flex items-center gap-1">
              <DollarSign className="w-4 h-4" />
              Chi phí (VND)
            </label>
            <div className="grid grid-cols-2 gap-2">
              <Input
                type="number"
                placeholder="Từ"
                value={filters.costRange.min || ''}
                onChange={(e) => onFiltersChange({
                  ...filters,
                  costRange: { ...filters.costRange, min: parseInt(e.target.value) || undefined }
                })}
                className="text-sm"
              />
              <Input
                type="number"
                placeholder="Đến"
                value={filters.costRange.max || ''}
                onChange={(e) => onFiltersChange({
                  ...filters,
                  costRange: { ...filters.costRange, max: parseInt(e.target.value) || undefined }
                })}
                className="text-sm"
              />
            </div>
          </div>
        </div>
      )}

      {/* Results Summary */}
      <div className="flex justify-between items-center text-sm text-gray-600 pt-2 border-t">
        <span>
          Hiển thị <span className="font-medium text-gray-900">{filteredCount}</span> trong tổng số{' '}
          <span className="font-medium text-gray-900">{totalCount}</span> dự án
        </span>
        
        {hasActiveFilters && (
          <span className="text-blue-600">
            {Object.keys(filters).filter(key => {
              const value = filters[key as keyof ProjectsFilters];
              return Array.isArray(value) ? value.length > 0 : 
                     typeof value === 'object' ? Object.keys(value).length > 0 :
                     Boolean(value);
            }).length} bộ lọc đang áp dụng
          </span>
        )}
      </div>
    </div>
  );
};