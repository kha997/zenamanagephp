import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/Card';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Select } from '@/components/Select';
import { Table } from '@/components/Table';
import { Loading } from '@/components/Loading';
import { usePagination } from '@/hooks/usePagination';
import { useDebounce } from '@/hooks/useDebounce';
import { apiClient } from '@/lib/api-client';
import {
  Plus,
  Search,
  Filter,
  Eye,
  Edit,
  Trash2,
  Calendar,
  DollarSign
} from 'lucide-react';

interface Project {
  id: number;
  name: string;
  description: string;
  status: 'planning' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled';
  progress: number;
  start_date: string;
  end_date: string;
  actual_cost: number;
  created_at: string;
  updated_at: string;
}

interface ProjectsResponse {
  projects: Project[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
  };
}

const ProjectsPage: React.FC = () => {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const debouncedSearch = useDebounce(search, 300);
  
  const {
    currentPage,
    pageSize,
    setCurrentPage,
    setPageSize
  } = usePagination();

  // Fetch projects với pagination và filtering
  const { data, isLoading, error } = useQuery({
    queryKey: ['projects', currentPage, pageSize, debouncedSearch, statusFilter],
    queryFn: async (): Promise<ProjectsResponse> => {
      const params = new URLSearchParams({
        page: currentPage.toString(),
        limit: pageSize.toString(),
        ...(debouncedSearch && { search: debouncedSearch }),
        ...(statusFilter && { status: statusFilter })
      });
      
      const response = await apiClient.get(`/projects?${params}`);
      return response.data;
    }
  });

  const statusOptions = [
    { value: '', label: 'Tất cả trạng thái' },
    { value: 'planning', label: 'Lên kế hoạch' },
    { value: 'in_progress', label: 'Đang thực hiện' },
    { value: 'on_hold', label: 'Tạm dừng' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'cancelled', label: 'Đã hủy' }
  ];

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return 'text-green-600 bg-green-100';
      case 'in_progress':
        return 'text-blue-600 bg-blue-100';
      case 'on_hold':
        return 'text-yellow-600 bg-yellow-100';
      case 'planning':
        return 'text-gray-600 bg-gray-100';
      case 'cancelled':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusText = (status: string) => {
    const option = statusOptions.find(opt => opt.value === status);
    return option?.label || status;
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('vi-VN');
  };

  const columns = [
    {
      key: 'name',
      label: 'Tên dự án',
      render: (project: Project) => (
        <div>
          <Link
            to={`/projects/${project.id}`}
            className="font-medium text-blue-600 hover:text-blue-800 transition-colors"
          >
            {project.name}
          </Link>
          <p className="text-sm text-gray-500 mt-1 line-clamp-2">
            {project.description}
          </p>
        </div>
      )
    },
    {
      key: 'status',
      label: 'Trạng thái',
      render: (project: Project) => (
        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(project.status)}`}>
          {getStatusText(project.status)}
        </span>
      )
    },
    {
      key: 'progress',
      label: 'Tiến độ',
      render: (project: Project) => (
        <div className="w-full">
          <div className="flex justify-between text-sm mb-1">
            <span>{project.progress}%</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-blue-600 h-2 rounded-full transition-all"
              style={{ width: `${project.progress}%` }}
            ></div>
          </div>
        </div>
      )
    },
    {
      key: 'dates',
      label: 'Thời gian',
      render: (project: Project) => (
        <div className="text-sm">
          <div className="flex items-center text-gray-600 mb-1">
            <Calendar className="w-3 h-3 mr-1" />
            {formatDate(project.start_date)}
          </div>
          <div className="text-gray-500">
            đến {formatDate(project.end_date)}
          </div>
        </div>
      )
    },
    {
      key: 'cost',
      label: 'Chi phí',
      render: (project: Project) => (
        <div className="text-sm">
          <div className="flex items-center text-gray-900 font-medium">
            <DollarSign className="w-3 h-3 mr-1" />
            {formatCurrency(project.actual_cost)}
          </div>
        </div>
      )
    },
    {
      key: 'actions',
      label: 'Thao tác',
      render: (project: Project) => (
        <div className="flex items-center gap-2">
          <Button size="sm" variant="outline" asChild>
            <Link to={`/projects/${project.id}`}>
              <Eye className="w-3 h-3" />
            </Link>
          </Button>
          <Button size="sm" variant="outline" asChild>
            <Link to={`/projects/${project.id}/edit`}>
              <Edit className="w-3 h-3" />
            </Link>
          </Button>
          <Button size="sm" variant="outline" className="text-red-600 hover:text-red-700">
            <Trash2 className="w-3 h-3" />
          </Button>
        </div>
      )
    }
  ];

  if (error) {
    return (
      <div className="text-center py-12">
        <p className="text-red-600">Có lỗi xảy ra khi tải dữ liệu</p>
        <Button className="mt-4" onClick={() => window.location.reload()}>
          Thử lại
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Dự án</h1>
          <p className="text-gray-600 mt-1">
            Quản lý tất cả các dự án trong hệ thống
          </p>
        </div>
        <Button asChild>
          <Link to="/projects/create">
            <Plus className="w-4 h-4 mr-2" />
            Tạo dự án mới
          </Link>
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Search */}
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input
                  placeholder="Tìm kiếm dự án..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>
            
            {/* Status Filter */}
            <div className="w-full sm:w-48">
              <Select
                value={statusFilter}
                onValueChange={setStatusFilter}
                options={statusOptions}
                placeholder="Lọc theo trạng thái"
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Projects Table */}
      <Card>
        <CardHeader>
          <CardTitle>
            Danh sách dự án
            {data && (
              <span className="text-sm font-normal text-gray-500 ml-2">
                ({data.pagination.total} dự án)
              </span>
            )}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loading size="lg" />
            </div>
          ) : data && data.projects.length > 0 ? (
            <Table
              data={data.projects}
              columns={columns}
              pagination={{
                currentPage: data.pagination.current_page,
                totalPages: data.pagination.last_page,
                pageSize: data.pagination.per_page,
                total: data.pagination.total,
                onPageChange: setCurrentPage,
                onPageSizeChange: setPageSize
              }}
            />
          ) : (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <Filter className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                Không tìm thấy dự án
              </h3>
              <p className="text-gray-500 mb-4">
                {search || statusFilter
                  ? 'Thử thay đổi bộ lọc để xem thêm kết quả'
                  : 'Chưa có dự án nào được tạo'}
              </p>
              {!search && !statusFilter && (
                <Button asChild>
                  <Link to="/projects/create">
                    <Plus className="w-4 h-4 mr-2" />
                    Tạo dự án đầu tiên
                  </Link>
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default ProjectsPage;