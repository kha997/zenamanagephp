import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/Select';
import { Badge } from '@/components/ui/Badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/Popover';
import { Calendar } from '@/components/ui/Calendar';
import { Checkbox } from '@/components/ui/Checkbox';
import { Label } from '@/components/ui/Label';
import { Separator } from '@/components/ui/Separator';
import {
  Search,
  Filter,
  X,
  Calendar as CalendarIcon,
  ChevronDown,
  RotateCcw,
  Save,
  Settings,
  Users,
  Flag,
  Clock,
  FolderOpen
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';
import { vi } from 'date-fns/locale';
import { debounce } from 'lodash';

interface TasksFilterBarProps {
  onFiltersChange: (filters: TaskFilters) => void;
  projects?: Array<{ id: string; name: string }>;
  users?: Array<{ id: string; name: string; avatar?: string }>;
  components?: Array<{ id: string; name: string; project_id: string }>;
  initialFilters?: Partial<TaskFilters>;
  className?: string;
  showSavedFilters?: boolean;
}

export interface TaskFilters {
  search: string;
  status: string[];
  priority: string[];
  project_id: string[];
  component_id: string[];
  assigned_to: string[];
  start_date_from?: Date;
  start_date_to?: Date;
  end_date_from?: Date;
  end_date_to?: Date;
  is_overdue?: boolean;
  has_dependencies?: boolean;
  sortBy: string;
  sortOrder: 'asc' | 'desc';
}

const DEFAULT_FILTERS: TaskFilters = {
  search: '',
  status: [],
  priority: [],
  project_id: [],
  component_id: [],
  assigned_to: [],
  sortBy: 'created_at',
  sortOrder: 'desc'
};

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Chờ thực hiện', color: 'bg-gray-100 text-gray-800' },
  { value: 'in_progress', label: 'Đang thực hiện', color: 'bg-blue-100 text-blue-800' },
  { value: 'review', label: 'Đang kiểm tra', color: 'bg-yellow-100 text-yellow-800' },
  { value: 'completed', label: 'Hoàn thành', color: 'bg-green-100 text-green-800' },
  { value: 'cancelled', label: 'Đã hủy', color: 'bg-red-100 text-red-800' }
];

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Thấp', color: 'text-green-600' },
  { value: 'medium', label: 'Trung bình', color: 'text-yellow-600' },
  { value: 'high', label: 'Cao', color: 'text-orange-600' },
  { value: 'critical', label: 'Khẩn cấp', color: 'text-red-600' }
];

const SORT_OPTIONS = [
  { value: 'created_at', label: 'Ngày tạo' },
  { value: 'start_date', label: 'Ngày bắt đầu' },
  { value: 'end_date', label: 'Ngày kết thúc' },
  { value: 'priority', label: 'Độ ưu tiên' },
  { value: 'name', label: 'Tên nhiệm vụ' },
  { value: 'progress', label: 'Tiến độ' }
];

/**
 * TasksFilterBar component với tính năng lọc nâng cao và tìm kiếm thông minh
 * Hỗ trợ lưu bộ lọc, quick filters và responsive design
 */
export const TasksFilterBar: React.FC<TasksFilterBarProps> = ({
  onFiltersChange,
  projects = [],
  users = [],
  components = [],
  initialFilters = {},
  className,
  showSavedFilters = true
}) => {
  const [filters, setFilters] = useState<TaskFilters>({
    ...DEFAULT_FILTERS,
    ...initialFilters
  });
  
  const [isExpanded, setIsExpanded] = useState(false);
  const [searchValue, setSearchValue] = useState(filters.search);
  const [savedFilters, setSavedFilters] = useState<Array<{ id: string; name: string; filters: TaskFilters }>>([]);
  const [showSaveDialog, setShowSaveDialog] = useState(false);
  const [filterName, setFilterName] = useState('');

  // Debounced search để tránh gọi API quá nhiều
  const debouncedSearch = useCallback(
    debounce((value: string) => {
      updateFilters({ search: value });
    }, 300),
    []
  );

  // Xử lý thay đổi search
  useEffect(() => {
    debouncedSearch(searchValue);
    return () => {
      debouncedSearch.cancel();
    };
  }, [searchValue, debouncedSearch]);

  // Gọi callback khi filters thay đổi
  useEffect(() => {
    onFiltersChange(filters);
  }, [filters, onFiltersChange]);

  // Load saved filters từ localStorage
  useEffect(() => {
    if (showSavedFilters) {
      const saved = localStorage.getItem('tasks_saved_filters');
      if (saved) {
        try {
          setSavedFilters(JSON.parse(saved));
        } catch (error) {
          console.error('Error loading saved filters:', error);
        }
      }
    }
  }, [showSavedFilters]);

  const updateFilters = (newFilters: Partial<TaskFilters>) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
  };

  const handleArrayFilter = (key: keyof TaskFilters, value: string) => {
    const currentArray = filters[key] as string[];
    const newArray = currentArray.includes(value)
      ? currentArray.filter(item => item !== value)
      : [...currentArray, value];
    updateFilters({ [key]: newArray });
  };

  const clearFilters = () => {
    setFilters(DEFAULT_FILTERS);
    setSearchValue('');
  };

  const saveCurrentFilters = () => {
    if (!filterName.trim()) return;
    
    const newSavedFilter = {
      id: Date.now().toString(),
      name: filterName.trim(),
      filters: { ...filters }
    };
    
    const updatedSaved = [...savedFilters, newSavedFilter];
    setSavedFilters(updatedSaved);
    localStorage.setItem('tasks_saved_filters', JSON.stringify(updatedSaved));
    
    setFilterName('');
    setShowSaveDialog(false);
  };

  const loadSavedFilter = (savedFilter: { filters: TaskFilters }) => {
    setFilters(savedFilter.filters);
    setSearchValue(savedFilter.filters.search);
  };

  const deleteSavedFilter = (filterId: string) => {
    const updatedSaved = savedFilters.filter(f => f.id !== filterId);
    setSavedFilters(updatedSaved);
    localStorage.setItem('tasks_saved_filters', JSON.stringify(updatedSaved));
  };

  // Tính toán số lượng filters đang active
  const activeFiltersCount = (
    filters.status.length +
    filters.priority.length +
    filters.project_id.length +
    filters.component_id.length +
    filters.assigned_to.length +
    (filters.start_date_from ? 1 : 0) +
    (filters.end_date_from ? 1 : 0) +
    (filters.is_overdue ? 1 : 0) +
    (filters.has_dependencies ? 1 : 0)
  );

  // Quick filter buttons
  const quickFilters = [
    {
      label: 'Của tôi',
      action: () => {
        // Assuming current user ID is available
        updateFilters({ assigned_to: ['current_user_id'] });
      }
    },
    {
      label: 'Quá hạn',
      action: () => updateFilters({ is_overdue: true })
    },
    {
      label: 'Đang thực hiện',
      action: () => updateFilters({ status: ['in_progress'] })
    },
    {
      label: 'Ưu tiên cao',
      action: () => updateFilters({ priority: ['high', 'critical'] })
    }
  ];

  return (
    <Card className={cn('border-0 shadow-sm', className)}>
      <CardContent className="p-4 space-y-4">
        {/* Search và Quick Actions */}
        <div className="flex flex-col sm:flex-row gap-3">
          {/* Search Input */}
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              placeholder="Tìm kiếm nhiệm vụ..."
              value={searchValue}
              onChange={(e) => setSearchValue(e.target.value)}
              className="pl-10 pr-4"
            />
            {searchValue && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setSearchValue('')}
                className="absolute right-2 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0"
              >
                <X className="h-4 w-4" />
              </Button>
            )}
          </div>

          {/* Filter Toggle */}
          <Button
            variant="outline"
            onClick={() => setIsExpanded(!isExpanded)}
            className="shrink-0"
          >
            <Filter className="h-4 w-4 mr-2" />
            Bộ lọc
            {activeFiltersCount > 0 && (
              <Badge className="ml-2 h-5 w-5 p-0 text-xs">
                {activeFiltersCount}
              </Badge>
            )}
            <ChevronDown className={cn(
              'h-4 w-4 ml-2 transition-transform',
              isExpanded && 'rotate-180'
            )} />
          </Button>

          {/* Clear Filters */}
          {activeFiltersCount > 0 && (
            <Button
              variant="ghost"
              onClick={clearFilters}
              className="shrink-0"
            >
              <RotateCcw className="h-4 w-4 mr-2" />
              Xóa bộ lọc
            </Button>
          )}
        </div>

        {/* Quick Filters */}
        <div className="flex flex-wrap gap-2">
          {quickFilters.map((quickFilter, index) => (
            <Button
              key={index}
              variant="outline"
              size="sm"
              onClick={quickFilter.action}
              className="text-xs"
            >
              {quickFilter.label}
            </Button>
          ))}
        </div>

        {/* Advanced Filters */}
        {isExpanded && (
          <div className="space-y-4 pt-4 border-t border-gray-100">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {/* Status Filter */}
              <div className="space-y-2">
                <Label className="text-sm font-medium flex items-center gap-2">
                  <Clock className="h-4 w-4" />
                  Trạng thái
                </Label>
                <div className="space-y-2">
                  {STATUS_OPTIONS.map((status) => (
                    <div key={status.value} className="flex items-center space-x-2">
                      <Checkbox
                        id={`status-${status.value}`}
                        checked={filters.status.includes(status.value)}
                        onCheckedChange={() => handleArrayFilter('status', status.value)}
                      />
                      <Label
                        htmlFor={`status-${status.value}`}
                        className="text-sm cursor-pointer"
                      >
                        <Badge className={cn('text-xs', status.color)}>
                          {status.label}
                        </Badge>
                      </Label>
                    </div>
                  ))}
                </div>
              </div>

              {/* Priority Filter */}
              <div className="space-y-2">
                <Label className="text-sm font-medium flex items-center gap-2">
                  <Flag className="h-4 w-4" />
                  Độ ưu tiên
                </Label>
                <div className="space-y-2">
                  {PRIORITY_OPTIONS.map((priority) => (
                    <div key={priority.value} className="flex items-center space-x-2">
                      <Checkbox
                        id={`priority-${priority.value}`}
                        checked={filters.priority.includes(priority.value)}
                        onCheckedChange={() => handleArrayFilter('priority', priority.value)}
                      />
                      <Label
                        htmlFor={`priority-${priority.value}`}
                        className={cn('text-sm cursor-pointer', priority.color)}
                      >
                        {priority.label}
                      </Label>
                    </div>
                  ))}
                </div>
              </div>

              {/* Project Filter */}
              <div className="space-y-2">
                <Label className="text-sm font-medium flex items-center gap-2">
                  <FolderOpen className="h-4 w-4" />
                  Dự án
                </Label>
                <Select
                  value={filters.project_id[0] || ''}
                  onValueChange={(value) => updateFilters({ project_id: value ? [value] : [] })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Chọn dự án" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Tất cả dự án</SelectItem>
                    {projects.map((project) => (
                      <SelectItem key={project.id} value={project.id}>
                        {project.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Assignee Filter */}
              <div className="space-y-2">
                <Label className="text-sm font-medium flex items-center gap-2">
                  <Users className="h-4 w-4" />
                  Người thực hiện
                </Label>
                <Select
                  value={filters.assigned_to[0] || ''}
                  onValueChange={(value) => updateFilters({ assigned_to: value ? [value] : [] })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Chọn người thực hiện" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
                    {users.map((user) => (
                      <SelectItem key={user.id} value={user.id}>
                        {user.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Date Range Filters */}
              <div className="space-y-2">
                <Label className="text-sm font-medium flex items-center gap-2">
                  <CalendarIcon className="h-4 w-4" />
                  Ngày bắt đầu
                </Label>
                <div className="flex gap-2">
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button variant="outline" className="w-full justify-start text-left font-normal">
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {filters.start_date_from ? format(filters.start_date_from, 'dd/MM/yyyy', { locale: vi }) : 'Từ ngày'}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={filters.start_date_from}
                        onSelect={(date) => updateFilters({ start_date_from: date })}
                        initialFocus
                      />
                    </PopoverContent>
                  </Popover>
                  <Popover>
                    <PopoverTrigger asChild>
                      <Button variant="outline" className="w-full justify-start text-left font-normal">
                        <CalendarIcon className="mr-2 h-4 w-4" />
                        {filters.start_date_to ? format(filters.start_date_to, 'dd/MM/yyyy', { locale: vi }) : 'Đến ngày'}
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="start">
                      <Calendar
                        mode="single"
                        selected={filters.start_date_to}
                        onSelect={(date) => updateFilters({ start_date_to: date })}
                        initialFocus
                      />
                    </PopoverContent>
                  </Popover>
                </div>
              </div>

              {/* Sort Options */}
              <div className="space-y-2">
                <Label className="text-sm font-medium">Sắp xếp theo</Label>
                <div className="flex gap-2">
                  <Select
                    value={filters.sortBy}
                    onValueChange={(value) => updateFilters({ sortBy: value })}
                  >
                    <SelectTrigger className="flex-1">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {SORT_OPTIONS.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Button
                    variant="outline"
                    onClick={() => updateFilters({ 
                      sortOrder: filters.sortOrder === 'asc' ? 'desc' : 'asc' 
                    })}
                    className="px-3"
                  >
                    {filters.sortOrder === 'asc' ? '↑' : '↓'}
                  </Button>
                </div>
              </div>
            </div>

            {/* Additional Options */}
            <div className="flex flex-wrap gap-4 pt-2">
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="is_overdue"
                  checked={filters.is_overdue || false}
                  onCheckedChange={(checked) => updateFilters({ is_overdue: checked as boolean })}
                />
                <Label htmlFor="is_overdue" className="text-sm cursor-pointer">
                  Chỉ hiển thị nhiệm vụ quá hạn
                </Label>
              </div>
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="has_dependencies"
                  checked={filters.has_dependencies || false}
                  onCheckedChange={(checked) => updateFilters({ has_dependencies: checked as boolean })}
                />
                <Label htmlFor="has_dependencies" className="text-sm cursor-pointer">
                  Có phụ thuộc
                </Label>
              </div>
            </div>

            {/* Saved Filters */}
            {showSavedFilters && (
              <>
                <Separator />
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <Label className="text-sm font-medium flex items-center gap-2">
                      <Settings className="h-4 w-4" />
                      Bộ lọc đã lưu
                    </Label>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setShowSaveDialog(true)}
                    >
                      <Save className="h-4 w-4 mr-2" />
                      Lưu bộ lọc hiện tại
                    </Button>
                  </div>
                  
                  {savedFilters.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {savedFilters.map((savedFilter) => (
                        <div key={savedFilter.id} className="flex items-center gap-1">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => loadSavedFilter(savedFilter)}
                            className="text-xs"
                          >
                            {savedFilter.name}
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => deleteSavedFilter(savedFilter.id)}
                            className="h-6 w-6 p-0 text-red-500 hover:text-red-700"
                          >
                            <X className="h-3 w-3" />
                          </Button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Save Filter Dialog */}
                {showSaveDialog && (
                  <div className="flex gap-2 p-3 bg-gray-50 rounded-lg">
                    <Input
                      placeholder="Tên bộ lọc..."
                      value={filterName}
                      onChange={(e) => setFilterName(e.target.value)}
                      className="flex-1"
                    />
                    <Button
                      onClick={saveCurrentFilters}
                      disabled={!filterName.trim()}
                      size="sm"
                    >
                      Lưu
                    </Button>
                    <Button
                      variant="ghost"
                      onClick={() => {
                        setShowSaveDialog(false);
                        setFilterName('');
                      }}
                      size="sm"
                    >
                      Hủy
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
};