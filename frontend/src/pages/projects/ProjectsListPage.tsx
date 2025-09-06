import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useProjectsStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Table } from '@/components/ui/Table';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { 
  Plus, 
  Search, 
  Filter, 
  MoreHorizontal,
  Eye,
  Edit,
  Trash2,
  Calendar,
  DollarSign
} from 'lucide-react';
import type { Project } from '@/lib/types';

/**
 * Trang danh sách dự án với tính năng tìm kiếm, lọc và phân trang
 * Hỗ trợ CRUD operations và quản lý quyền hạn
 */
export const ProjectsListPage: React.FC = () => {
  const { 
    projects, 
    isLoading, 
    pagination,
    fetchProjects,
    deleteProject 
  } = useProjectsStore();
  
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    sortBy: 'created_at',
    sortOrder: 'desc' as 'asc' | 'desc'
  });

  // Load dữ liệu khi component mount
  useEffect(() => {
    fetchProjects(filters);
  }, [filters, fetchProjects]);

  // Xử lý tìm kiếm
  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
  };

  // Xử lý lọc theo trạng thái
  const handleStatusFilter = (status: string) => {
    setFilters(prev => ({ ...prev, status }));
  };

  // Xử lý sắp xếp
  const handleSort = (field: string) => {
    setFilters(prev => ({
      ...prev,
      sortBy: field,
      sortOrder: prev.sortBy === field && prev.sortOrder === 'asc' ? 'desc' : 'asc'
    }));
  };

  // Xử lý xóa dự án
  const handleDelete = async (projectId: string) => {
    if (window.confirm('Bạn có chắc chắn muốn xóa dự án này?')) {
      await deleteProject(projectId);
    }
  };

  // Định nghĩa cột cho bảng
  const columns = [
    {
      key: 'name',
      title: 'Tên dự án',
      sortable: true,
      render: (project: Project) => (
        <div>
          <Link 
            to={`/projects/${project.id}`}
            className="font-medium text-blue-600 hover:text-blue-500"
          >
            {project.name}
          </Link>
          <p className="text-sm text-gray-600 mt-1">{project.description}</p>
        </div>
      )
    },
    {
      key: 'status',
      title: 'Trạng thái',
      sortable: true,
      render: (project: Project) => (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
          project.status === 'completed' 
            ? 'bg-green-100 text-green-800'
            : project.status === 'active'
            ? 'bg-blue-100 text-blue-800'
            : project.status === 'on_hold'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-gray-100 text-gray-800'
        }`}>
          {project.status === 'completed' ? 'Hoàn thành' :
           project.status === 'active' ? 'Đang thực hiện' :
           project.status === 'on_hold' ? 'Tạm dừng' : 'Nháp'}
        </span>
      )
    },
    {
      key: 'progress',
      title: 'Tiến độ',
      sortable: true,
      render: (project: Project) => (
        <div className="w-24">
          <div className="flex items-center justify-between mb-1">
            <span className="text-sm font-medium">{project.progress}%</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div 
              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${project.progress}%` }}
            />
          </div>
        </div>
      )
    },
    {
      key: 'start_date',
      title: 'Thời gian',
      sortable: true,
      render: (project: Project) => (
        <div className="text-sm">
          <div className="flex items-center text-gray-600">
            <Calendar className="w-4 h-4 mr-1" />
            {new Date(project.start_date).toLocaleDateString('vi-VN')}
          </div>
          <div className="text-gray-500 mt-1">
            đến {new Date(project.end_date).toLocaleDateString('vi-VN')}
          </div>
        </div>
      )
    },
    {
      key: 'actual_cost',
      title: 'Chi phí',
      sortable: true,
      render: (project: Project) => (
        <div className="text-sm">
          <div className="flex items-center font-medium">
            <DollarSign className="w-4 h-4 mr-1" />
            {project.actual_cost?.toLocaleString('vi-VN')} VNĐ
          </div>
        </div>
      )
    },
    {
      key: 'actions',
      title: '',
      render: (project: Project) => (
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/projects/${project.id}`}
          >
            <Eye className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/projects/${project.id}/edit`}
          >
            <Edit className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => handleDelete(project.id)}
            className="text-red-600 hover:text-red-700"
          >
            <Trash2 className="w-4 h-4" />
          </Button>
        </div>
      )
    }
  ];

  if (isLoading) {
    return <Loading.Skeleton />;
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Quản lý dự án</h1>
          <p className="text-gray-600 mt-1">
            Tổng cộng {pagination.total} dự án
          </p>
        </div>
        <Button as={Link} to="/projects/create">
          <Plus className="w-4 h-4 mr-2" />
          Tạo dự án mới
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <Input
                placeholder="Tìm kiếm dự án..."
                value={filters.search}
                onChange={(e) => handleSearch(e.target.value)}
                leftIcon={<Search className="w-4 h-4" />}
              />
            </div>
            <div className="w-48">
              <Select
                placeholder="Trạng thái"
                value={filters.status}
                onChange={handleStatusFilter}
                options={[
                  { value: '', label: 'Tất cả trạng thái' },
                  { value: 'draft', label: 'Nháp' },
                  { value: 'active', label: 'Đang thực hiện' },
                  { value: 'on_hold', label: 'Tạm dừng' },
                  { value: 'completed', label: 'Hoàn thành' }
                ]}
              />
            </div>
            <Button variant="outline">
              <Filter className="w-4 h-4 mr-2" />
              Bộ lọc
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Projects Table */}
      <Card>
        <CardContent className="p-0">
          <Table
            columns={columns}
            data={projects}
            pagination={pagination}
            onSort={handleSort}
            sortBy={filters.sortBy}
            sortOrder={filters.sortOrder}
          />
        </CardContent>
      </Card>
    </div>
  );
};