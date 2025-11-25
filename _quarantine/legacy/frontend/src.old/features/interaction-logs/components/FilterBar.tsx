import React, { useState, useMemo } from 'react';
import { 
  Search, 
  Filter, 
  Calendar, 
  Tag, 
  Eye, 
  User, 
  ChevronDown, 
  ChevronUp,
  X,
  Clock,
  Phone,
  Mail,
  Users,
  FileText,
  MessageSquare
} from 'lucide-react';
import { InteractionLogFilters, InteractionType, VisibilityType } from '../types/interactionLog';
import { useInteractionLogsStore } from '../store/useInteractionLogsStore';

/**
 * Enhanced FilterBar component với UI cải thiện
 * Bao gồm: collapsible filters, quick filters, responsive design
 */
export const FilterBar: React.FC = () => {
  const { filters, setFilters, clearFilters } = useInteractionLogsStore();
  const [isExpanded, setIsExpanded] = useState(false);
  const [searchValue, setSearchValue] = useState(filters.search || '');

  /**
   * Cập nhật một trường filter cụ thể
   */
  const updateFilter = <K extends keyof InteractionLogFilters>(
    key: K,
    value: InteractionLogFilters[K]
  ) => {
    setFilters({ [key]: value });
  };

  /**
   * Xử lý thay đổi input tìm kiếm với debounce
   */
  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchValue(value);
    
    // Debounce search
    const timeoutId = setTimeout(() => {
      updateFilter('search', value || undefined);
    }, 300);
    
    return () => clearTimeout(timeoutId);
  };

  /**
   * Quick filter presets
   */
  const quickFilters = [
    {
      label: 'Hôm nay',
      icon: Clock,
      action: () => {
        const today = new Date().toISOString().split('T')[0];
        updateFilter('startDate', today);
        updateFilter('endDate', today);
      }
    },
    {
      label: '7 ngày qua',
      icon: Calendar,
      action: () => {
        const today = new Date();
        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
        updateFilter('startDate', weekAgo.toISOString().split('T')[0]);
        updateFilter('endDate', today.toISOString().split('T')[0]);
      }
    },
    {
      label: 'Chờ duyệt',
      icon: Clock,
      action: () => {
        updateFilter('visibility', 'client');
        updateFilter('clientApproved', false);
      }
    },
    {
      label: 'Cuộc gọi',
      icon: Phone,
      action: () => updateFilter('type', 'call')
    },
    {
      label: 'Email',
      icon: Mail,
      action: () => updateFilter('type', 'email')
    },
    {
      label: 'Cuộc họp',
      icon: Users,
      action: () => updateFilter('type', 'meeting')
    }
  ];

  /**
   * Đếm số lượng filter đang active
   */
  const activeFiltersCount = useMemo(() => {
    return Object.values(filters).filter(value => 
      value !== undefined && value !== '' && value !== null
    ).length;
  }, [filters]);

  /**
   * Lấy active filter tags
   */
  const activeFilterTags = useMemo(() => {
    const tags = [];
    
    if (filters.search) {
      tags.push({ key: 'search', label: `Tìm kiếm: "${filters.search}"`, value: filters.search });
    }
    
    if (filters.type) {
      const typeLabels = {
        call: 'Cuộc gọi',
        email: 'Email', 
        meeting: 'Cuộc họp',
        note: 'Ghi chú',
        feedback: 'Phản hồi'
      };
      tags.push({ key: 'type', label: `Loại: ${typeLabels[filters.type]}`, value: filters.type });
    }
    
    if (filters.visibility) {
      const visibilityLabels = {
        internal: 'Nội bộ',
        client: 'Khách hàng'
      };
      tags.push({ key: 'visibility', label: `Hiển thị: ${visibilityLabels[filters.visibility]}`, value: filters.visibility });
    }
    
    if (filters.tagPath) {
      tags.push({ key: 'tagPath', label: `Tag: ${filters.tagPath}`, value: filters.tagPath });
    }
    
    if (filters.startDate) {
      tags.push({ key: 'startDate', label: `Từ: ${filters.startDate}`, value: filters.startDate });
    }
    
    if (filters.endDate) {
      tags.push({ key: 'endDate', label: `Đến: ${filters.endDate}`, value: filters.endDate });
    }
    
    if (filters.createdBy) {
      tags.push({ key: 'createdBy', label: `Người tạo: ${filters.createdBy}`, value: filters.createdBy });
    }
    
    if (filters.clientApproved !== undefined) {
      tags.push({ 
        key: 'clientApproved', 
        label: filters.clientApproved ? 'Đã duyệt' : 'Chưa duyệt', 
        value: filters.clientApproved 
      });
    }
    
    return tags;
  }, [filters]);

  /**
   * Xóa một filter cụ thể
   */
  const removeFilter = (key: keyof InteractionLogFilters) => {
    updateFilter(key, undefined);
    if (key === 'search') {
      setSearchValue('');
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b border-gray-200">
        <div className="flex items-center gap-3">
          <Filter className="h-5 w-5 text-gray-500" />
          <h3 className="text-lg font-medium text-gray-900">Bộ lọc</h3>
          {activeFiltersCount > 0 && (
            <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
              {activeFiltersCount}
            </span>
          )}
        </div>
        
        <div className="flex items-center gap-2">
          {activeFiltersCount > 0 && (
            <button
              onClick={clearFilters}
              className="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-md hover:bg-gray-100 transition-colors"
            >
              Xóa tất cả
            </button>
          )}
          
          <button
            onClick={() => setIsExpanded(!isExpanded)}
            className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800 px-3 py-1.5 rounded-md hover:bg-gray-100 transition-colors"
          >
            {isExpanded ? (
              <>
                <ChevronUp className="h-4 w-4" />
                Thu gọn
              </>
            ) : (
              <>
                <ChevronDown className="h-4 w-4" />
                Mở rộng
              </>
            )}
          </button>
        </div>
      </div>

      {/* Quick Filters */}
      <div className="p-4 border-b border-gray-100">
        <div className="flex items-center gap-2 mb-3">
          <span className="text-sm font-medium text-gray-700">Bộ lọc nhanh:</span>
        </div>
        <div className="flex flex-wrap gap-2">
          {quickFilters.map((filter, index) => {
            const Icon = filter.icon;
            return (
              <button
                key={index}
                onClick={filter.action}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition-colors"
              >
                <Icon className="h-3.5 w-3.5" />
                {filter.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* Active Filter Tags */}
      {activeFilterTags.length > 0 && (
        <div className="p-4 border-b border-gray-100">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-sm font-medium text-gray-700">Bộ lọc đang áp dụng:</span>
          </div>
          <div className="flex flex-wrap gap-2">
            {activeFilterTags.map((tag) => (
              <span
                key={tag.key}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full"
              >
                {tag.label}
                <button
                  onClick={() => removeFilter(tag.key as keyof InteractionLogFilters)}
                  className="hover:bg-blue-200 rounded-full p-0.5 transition-colors"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Main Search */}
      <div className="p-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            type="text"
            placeholder="Tìm kiếm trong mô tả, tag path..."
            value={searchValue}
            onChange={handleSearchChange}
            className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
          />
        </div>
      </div>

      {/* Advanced Filters */}
      {isExpanded && (
        <div className="p-4 border-t border-gray-100 bg-gray-50">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {/* Loại tương tác */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Loại tương tác</label>
              <select
                value={filters.type || ''}
                onChange={(e) => updateFilter('type', (e.target.value as InteractionType) || undefined)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white"
              >
                <option value="">Tất cả loại</option>
                <option value="call">📞 Cuộc gọi</option>
                <option value="email">📧 Email</option>
                <option value="meeting">👥 Cuộc họp</option>
                <option value="note">📝 Ghi chú</option>
                <option value="feedback">💬 Phản hồi</option>
              </select>
            </div>

            {/* Visibility */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Hiển thị</label>
              <div className="relative">
                <Eye className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <select
                  value={filters.visibility || ''}
                  onChange={(e) => updateFilter('visibility', (e.target.value as VisibilityType) || undefined)}
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white"
                >
                  <option value="">Tất cả</option>
                  <option value="internal">🏢 Nội bộ</option>
                  <option value="client">👤 Khách hàng</option>
                </select>
              </div>
            </div>

            {/* Tag Path */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Tag Path</label>
              <div className="relative">
                <Tag className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Ví dụ: Material/Flooring"
                  value={filters.tagPath || ''}
                  onChange={(e) => updateFilter('tagPath', e.target.value || undefined)}
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
              </div>
            </div>

            {/* Ngày bắt đầu */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Từ ngày</label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="date"
                  value={filters.startDate || ''}
                  onChange={(e) => updateFilter('startDate', e.target.value || undefined)}
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
              </div>
            </div>

            {/* Ngày kết thúc */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Đến ngày</label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="date"
                  value={filters.endDate || ''}
                  onChange={(e) => updateFilter('endDate', e.target.value || undefined)}
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
              </div>
            </div>

            {/* Created By */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Người tạo (ID)</label>
              <div className="relative">
                <User className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="number"
                  placeholder="ID người tạo"
                  value={filters.createdBy || ''}
                  onChange={(e) => updateFilter('createdBy', e.target.value ? parseInt(e.target.value) : undefined)}
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                />
              </div>
            </div>

            {/* Client Approved */}
            <div className="space-y-1">
              <label className="block text-xs font-medium text-gray-700">Trạng thái duyệt</label>
              <div className="flex items-center space-x-4 pt-2">
                <label className="flex items-center">
                  <input
                    type="radio"
                    name="clientApproved"
                    checked={filters.clientApproved === undefined}
                    onChange={() => updateFilter('clientApproved', undefined)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700">Tất cả</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="radio"
                    name="clientApproved"
                    checked={filters.clientApproved === true}
                    onChange={() => updateFilter('clientApproved', true)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700">Đã duyệt</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="radio"
                    name="clientApproved"
                    checked={filters.clientApproved === false}
                    onChange={() => updateFilter('clientApproved', false)}
                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                  />
                  <span className="ml-2 text-sm text-gray-700">Chờ duyệt</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};