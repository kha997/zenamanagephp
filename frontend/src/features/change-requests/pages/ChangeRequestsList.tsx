import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Filter } from 'lucide-react';
import { useChangeRequestsStore } from '../../../store/changeRequests';
import { ChangeRequestCard } from '../components/ChangeRequestCard';
import { StatusBadge } from '../components/StatusBadge';
import type { ChangeRequestStatus } from '../../../lib/types';

interface FilterState {
  status: ChangeRequestStatus | 'all';
  search: string;
}

export const ChangeRequestsList: React.FC = () => {
  const { changeRequests, loading, error, fetchChangeRequests } = useChangeRequestsStore();
  const [filters, setFilters] = useState<FilterState>({
    status: 'all',
    search: ''
  });

  useEffect(() => {
    fetchChangeRequests();
  }, [fetchChangeRequests]);

  // Lọc danh sách Change Requests theo filters
  const filteredChangeRequests = changeRequests.filter(cr => {
    const matchesStatus = filters.status === 'all' || cr.status === filters.status;
    const matchesSearch = filters.search === '' || 
      cr.title.toLowerCase().includes(filters.search.toLowerCase()) ||
      cr.description.toLowerCase().includes(filters.search.toLowerCase());
    
    return matchesStatus && matchesSearch;
  });

  const handleFilterChange = (key: keyof FilterState, value: string) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  };

  if (loading.isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <p className="text-red-800">Lỗi: {error}</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Change Requests</h1>
          <p className="text-gray-600 mt-1">
            Quản lý các yêu cầu thay đổi dự án
          </p>
        </div>
        <Link
          to="/change-requests/create"
          className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          <Plus className="w-4 h-4 mr-2" />
          Tạo Change Request
        </Link>
      </div>

      {/* Filters */}
      <div className="bg-white p-4 rounded-lg shadow-sm border">
        <div className="flex flex-col sm:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
              <input
                type="text"
                placeholder="Tìm kiếm theo tiêu đề hoặc mô tả..."
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="pl-10 pr-4 py-2 w-full border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          {/* Status Filter */}
          <div className="sm:w-48">
            <select
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="all">Tất cả trạng thái</option>
              <option value="draft">Bản nháp</option>
              <option value="awaiting_approval">Chờ duyệt</option>
              <option value="approved">Đã duyệt</option>
              <option value="rejected">Từ chối</option>
            </select>
          </div>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {[
          { status: 'draft', label: 'Bản nháp', color: 'bg-gray-100 text-gray-800' },
          { status: 'awaiting_approval', label: 'Chờ duyệt', color: 'bg-yellow-100 text-yellow-800' },
          { status: 'approved', label: 'Đã duyệt', color: 'bg-green-100 text-green-800' },
          { status: 'rejected', label: 'Từ chối', color: 'bg-red-100 text-red-800' }
        ].map(({ status, label, color }) => {
          const count = changeRequests.filter(cr => cr.status === status).length;
          return (
            <div key={status} className="bg-white p-4 rounded-lg shadow-sm border">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-gray-600">{label}</p>
                  <p className="text-2xl font-bold text-gray-900">{count}</p>
                </div>
                <div className={`px-2 py-1 rounded-full text-xs font-medium ${color}`}>
                  <StatusBadge status={status as ChangeRequestStatus} />
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Change Requests List */}
      <div className="space-y-4">
        {filteredChangeRequests.length === 0 ? (
          <div className="text-center py-12">
            <Filter className="mx-auto h-12 w-12 text-gray-400" />
            <h3 className="mt-2 text-sm font-medium text-gray-900">
              Không tìm thấy Change Request nào
            </h3>
            <p className="mt-1 text-sm text-gray-500">
              {filters.status !== 'all' || filters.search ? 
                'Thử thay đổi bộ lọc để xem thêm kết quả.' :
                'Bắt đầu bằng cách tạo Change Request đầu tiên.'
              }
            </p>
            {filters.status === 'all' && !filters.search && (
              <div className="mt-6">
                <Link
                  to="/change-requests/create"
                  className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                >
                  <Plus className="w-4 h-4 mr-2" />
                  Tạo Change Request
                </Link>
              </div>
            )}
          </div>
        ) : (
          <div className="grid gap-4">
            {filteredChangeRequests.map((changeRequest) => (
              <ChangeRequestCard
                key={changeRequest.id}
                changeRequest={changeRequest}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
