import React from 'react';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { 
  Search, 
  Filter, 
  X, 
  SortAsc, 
  SortDesc,
  RefreshCw
} from 'lucide-react';
import { ComponentFilters } from '../types/component';
import { cn } from '@/lib/utils';

interface ComponentsFilterBarProps {
  filters: ComponentFilters;
  onFiltersChange: (filters: ComponentFilters) => void;
  onReset: () => void;
  isLoading?: boolean;
  className?: string;
}

export const ComponentsFilterBar: React.FC<ComponentsFilterBarProps> = ({
  filters,
  onFiltersChange,
  onReset,
  isLoading = false,
  className
}) => {
  const sortOptions = [
    { value: 'name', label: 'Tên' },
    { value: 'progress_percent', label: 'Tiến độ' },
    { value: 'planned_cost', label: 'Chi phí kế hoạch' },
    { value: 'actual_cost', label: 'Chi phí thực tế' },
    { value: 'created_at', label: 'Ngày tạo' }
  ];

  const getActiveFiltersCount = () => {
    let count = 0;
    if (filters.search) count++;
    if (filters.parent_component_id) count++;
    if (filters.min_cost !== undefined) count++;
    if (filters.max_cost !== undefined) count++;
    if (filters.min_progress !== undefined) count++;
    if (filters.max_progress !== undefined) count++;
    return count;
  };

  const activeFiltersCount = getActiveFiltersCount();

  return (
    <div className={cn('space-y-4', className)}>
      {/* Search and Quick Actions */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="flex-1">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
            <Input
              placeholder="Tìm kiếm thành phần..."
              value={filters.search || ''}
              onChange={(e) => onFiltersChange({ ...filters, search: e.target.value })}
              className="pl-10"
            />
          </div>
        </div>
        
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={onReset}
            disabled={isLoading || activeFiltersCount === 0}
            className="flex items-center space-x-1"
          >
            <RefreshCw className={cn('w-4 h-4', isLoading && 'animate-spin')} />
            <span>Đặt lại</span>
          </Button>
          
          {activeFiltersCount > 0 && (
            <Badge variant="secondary" className="ml-2">
              {activeFiltersCount} bộ lọc
            </Badge>
          )}
        </div>
      </div>

      {/* Advanced Filters */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Sort By */}
        <div className="space-y-1">
          <label className="text-sm font-medium text-gray-700">
            Sắp xếp theo
          </label>
          <div className="flex space-x-1">
            <Select
              value={filters.sort_by || 'name'}
              onValueChange={(value) => 
                onFiltersChange({ 
                  ...filters, 
                  sort_by: value as ComponentFilters['sort_by']
                })
              }
              className="flex-1"
            >
              {sortOptions.map(option => (
                <Select.Option key={option.value} value={option.value}>
                  {option.label}
                </Select.Option>
              ))}
            </Select>
            
            <Button
              variant="outline"
              size="sm"
              onClick={() => 
                onFiltersChange({ 
                  ...filters, 
                  sort_order: filters.sort_order === 'asc' ? 'desc' : 'asc'
                })
              }
              className="px-2"
            >
              {filters.sort_order === 'desc' ? 
                <SortDesc className="w-4 h-4" /> : 
                <SortAsc className="w-4 h-4" />
              }
            </Button>
          </div>
        </div>

        {/* Cost Range */}
        <div className="space-y-1">
          <label className="text-sm font-medium text-gray-700">
            Chi phí tối thiểu
          </label>
          <Input
            type="number"
            placeholder="0"
            value={filters.min_cost || ''}
            onChange={(e) => 
              onFiltersChange({ 
                ...filters, 
                min_cost: e.target.value ? Number(e.target.value) : undefined
              })
            }
          />
        </div>

        <div className="space-y-1">
          <label className="text-sm font-medium text-gray-700">
            Chi phí tối đa
          </label>
          <Input
            type="number"
            placeholder="Không giới hạn"
            value={filters.max_cost || ''}
            onChange={(e) => 
              onFiltersChange({ 
                ...filters, 
                max_cost: e.target.value ? Number(e.target.value) : undefined
              })
            }
          />
        </div>

        {/* Progress Range */}
        <div className="space-y-1">
          <label className="text-sm font-medium text-gray-700">
            Tiến độ tối thiểu (%)
          </label>
          <Input
            type="number"
            min="0"
            max="100"
            placeholder="0"
            value={filters.min_progress || ''}
            onChange={(e) => 
              onFiltersChange({ 
                ...filters, 
                min_progress: e.target.value ? Number(e.target.value) : undefined
              })
            }
          />
        </div>
      </div>

      {/* Active Filters Display */}
      {activeFiltersCount > 0 && (
        <div className="flex flex-wrap gap-2">
          {filters.search && (
            <Badge variant="outline" className="flex items-center space-x-1">
              <span>Tìm kiếm: "{filters.search}"</span>
              <button
                onClick={() => onFiltersChange({ ...filters, search: undefined })}
                className="ml-1 hover:bg-gray-200 rounded-full p-0.5"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}
          
          {filters.min_cost !== undefined && (
            <Badge variant="outline" className="flex items-center space-x-1">
              <span>Chi phí ≥ {filters.min_cost.toLocaleString()}</span>
              <button
                onClick={() => onFiltersChange({ ...filters, min_cost: undefined })}
                className="ml-1 hover:bg-gray-200 rounded-full p-0.5"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}
          
          {filters.max_cost !== undefined && (
            <Badge variant="outline" className="flex items-center space-x-1">
              <span>Chi phí ≤ {filters.max_cost.toLocaleString()}</span>
              <button
                onClick={() => onFiltersChange({ ...filters, max_cost: undefined })}
                className="ml-1 hover:bg-gray-200 rounded-full p-0.5"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}
          
          {filters.min_progress !== undefined && (
            <Badge variant="outline" className="flex items-center space-x-1">
              <span>Tiến độ ≥ {filters.min_progress}%</span>
              <button
                onClick={() => onFiltersChange({ ...filters, min_progress: undefined })}
                className="ml-1 hover:bg-gray-200 rounded-full p-0.5"
              >
                <X className="w-3 h-3" />
              </button>
            </Badge>
          )}
        </div>
      )}
    </div>
  );
};