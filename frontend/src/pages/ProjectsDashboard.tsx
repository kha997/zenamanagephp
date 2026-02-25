import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { apiClient } from '@/lib/api-client';
import { LoadingState } from '@/components/LoadingStates';

interface Project {
  id: string;
  name: string;
  description: string;
  status: string;
  progress: number;
  start_date: string;
  end_date: string;
  created_at: string;
  updated_at: string;
}

interface ProjectsResponse {
  status: string;
  data: {
    projects: Project[];
    pagination: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
}

export default function ProjectsDashboard() {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

  // Fetch projects data
  const { data, isLoading, error, refetch } = useQuery<ProjectsResponse>({
    queryKey: ['projects', searchTerm, statusFilter],
    queryFn: async () => {
      const response = await apiClient.get('/projects', {
        params: {
          search: searchTerm,
          status: statusFilter !== 'all' ? statusFilter : undefined,
        }
      });
      return response.data;
    },
  });

  const projects = data?.data?.projects || [];
  const pagination = data?.data?.pagination;

  const getStatusConfig = (status: string) => {
    switch (status) {
      case 'planning': 
        return { 
          text: 'Đang lập kế hoạch', 
          color: 'zena-badge-info',
          icon: 'fas fa-clock',
          dotColor: 'bg-blue-500'
        };
      case 'in_progress': 
        return { 
          text: 'Đang thực hiện', 
          color: 'zena-badge-success',
          icon: 'fas fa-play',
          dotColor: 'bg-green-500'
        };
      case 'on_hold': 
        return { 
          text: 'Tạm dừng', 
          color: 'zena-badge-warning',
          icon: 'fas fa-pause',
          dotColor: 'bg-yellow-500'
        };
      case 'completed': 
        return { 
          text: 'Hoàn thành', 
          color: 'zena-badge-neutral',
          icon: 'fas fa-check-circle',
          dotColor: 'bg-gray-500'
        };
      case 'cancelled': 
        return { 
          text: 'Đã hủy', 
          color: 'zena-badge-danger',
          icon: 'fas fa-times-circle',
          dotColor: 'bg-red-500'
        };
      default: 
        return { 
          text: status, 
          color: 'zena-badge-neutral',
          icon: 'fas fa-clock',
          dotColor: 'bg-gray-500'
        };
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };

  const getProgressColor = (progress: number) => {
    if (progress >= 80) return 'zena-progress-bar-success';
    if (progress >= 50) return 'zena-progress-bar-warning';
    if (progress >= 20) return 'zena-progress-bar-info';
    return 'zena-progress-bar-danger';
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="dashboard-card p-6">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-center space-x-4">
                <div className="p-3 bg-blue-100 rounded-lg">
                  <i className="fas fa-project-diagram text-blue-600 text-2xl"></i>
                </div>
                <div>
                  <h1 className="text-3xl font-bold text-gray-900">
                    Quản lý Dự án
                  </h1>
                  <p className="mt-1 text-gray-600 text-lg">
                    Dashboard quản lý và theo dõi các dự án của bạn
                  </p>
                </div>
              </div>
              <div className="mt-6 lg:mt-0 flex flex-col sm:flex-row gap-3">
                <button className="zena-btn zena-btn-outline zena-btn-lg">
                  <i className="fas fa-download"></i>
                  Xuất báo cáo
                </button>
                <button className="zena-btn zena-btn-primary zena-btn-lg">
                  <i className="fas fa-plus"></i>
                  Tạo dự án mới
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Stats Cards */}
        <LoadingState 
          loading={isLoading} 
          error={error} 
          onRetry={refetch}
          loadingText="Đang tải thống kê dự án..."
        >
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div className="dashboard-card metric-card blue p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-white/80 text-sm">Tổng dự án</p>
                  <p className="text-3xl font-bold text-white" x-text="stats?.totalUsers || 24">
                    {pagination?.total || 0}
                  </p>
                  <p className="text-white/80 text-sm">
                    <span>{projects.length}</span> dự án hiện tại
                  </p>
                </div>
                <i className="fas fa-project-diagram text-4xl text-white/60"></i>
              </div>
            </div>

            <div className="dashboard-card metric-card green p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-white/80 text-sm">Đang thực hiện</p>
                  <p className="text-3xl font-bold text-white">
                    {projects.filter(p => p.status === 'in_progress').length}
                  </p>
                  <p className="text-white/80 text-sm">
                    <span>{projects.filter(p => p.status === 'in_progress').length}</span> đang hoạt động
                  </p>
                </div>
                <i className="fas fa-play text-4xl text-white/60"></i>
              </div>
            </div>

            <div className="dashboard-card metric-card orange p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-white/80 text-sm">Tạm dừng</p>
                  <p className="text-3xl font-bold text-white">
                    {projects.filter(p => p.status === 'on_hold').length}
                  </p>
                  <div className="flex space-x-2 mt-1">
                    <p className="text-white/80 text-sm">
                      <span>{projects.filter(p => p.status === 'on_hold').length}</span> cần xem xét
                    </p>
                  </div>
                </div>
                <i className="fas fa-pause text-4xl text-white/60"></i>
              </div>
            </div>

            <div className="dashboard-card metric-card purple p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-white/80 text-sm">Hoàn thành</p>
                  <p className="text-3xl font-bold text-white">
                    {projects.filter(p => p.status === 'completed').length}
                  </p>
                  <p className="text-white/80 text-sm">
                    <span>{projects.filter(p => p.status === 'completed').length}</span> đã hoàn thành
                  </p>
                </div>
                <i className="fas fa-check-circle text-4xl text-white/60"></i>
              </div>
            </div>
          </div>
        </LoadingState>

        {/* Filters and Controls */}
        <div className="mb-8">
          <div className="dashboard-card p-6">
            <div className="flex flex-col lg:flex-row gap-6">
              {/* Search Input */}
              <div className="flex-1">
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i className="fas fa-search text-gray-400"></i>
                  </div>
                  <input
                    type="text"
                    placeholder="Tìm kiếm dự án theo tên hoặc mô tả..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="zena-input pl-12"
                  />
                </div>
              </div>

              {/* Status Filter */}
              <div className="lg:w-64">
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i className="fas fa-filter text-gray-400"></i>
                  </div>
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="zena-select pl-12"
                  >
                    <option value="all">Tất cả trạng thái</option>
                    <option value="planning">Đang lập kế hoạch</option>
                    <option value="in_progress">Đang thực hiện</option>
                    <option value="on_hold">Tạm dừng</option>
                    <option value="completed">Hoàn thành</option>
                    <option value="cancelled">Đã hủy</option>
                  </select>
                </div>
              </div>

              {/* View Mode Toggle */}
              <div className="flex gap-2">
                <div className="bg-gray-100 rounded-lg p-1 flex">
                  <button
                    onClick={() => setViewMode('grid')}
                    className={`px-4 py-2 rounded-md transition-all duration-200 ${
                      viewMode === 'grid' 
                        ? 'bg-white shadow-sm text-blue-600' 
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200'
                    }`}
                  >
                    <i className="fas fa-th mr-2"></i>
                    Grid
                  </button>
                  <button
                    onClick={() => setViewMode('list')}
                    className={`px-4 py-2 rounded-md transition-all duration-200 ${
                      viewMode === 'list' 
                        ? 'bg-white shadow-sm text-blue-600' 
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200'
                    }`}
                  >
                    <i className="fas fa-list mr-2"></i>
                    List
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Projects Content */}
        <div>
          <div className="dashboard-card overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
              <h2 className="text-xl font-bold text-gray-900">Danh sách dự án</h2>
              <p className="text-gray-600 mt-1">Quản lý và theo dõi tiến độ các dự án</p>
            </div>
            
            <div className="p-6">
              {projects.length === 0 ? (
                <div className="text-center py-16">
                  <div className="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i className="fas fa-project-diagram text-blue-500 text-3xl"></i>
                  </div>
                  <h3 className="text-2xl font-bold text-gray-900 mb-3">
                    Chưa có dự án nào
                  </h3>
                  <p className="text-gray-600 mb-8 max-w-md mx-auto">
                    Hãy tạo dự án đầu tiên để bắt đầu quản lý và theo dõi tiến độ công việc của bạn
                  </p>
                  <button className="zena-btn zena-btn-primary zena-btn-lg">
                    <i className="fas fa-plus mr-2"></i>
                    Tạo dự án mới
                  </button>
                </div>
              ) : viewMode === 'grid' ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {projects.map((project) => {
                    const statusConfig = getStatusConfig(project.status);
                    
                    return (
                      <div
                        key={project.id}
                        className="dashboard-card zena-card-interactive p-6"
                      >
                        {/* Header */}
                        <div className="flex items-start justify-between mb-4">
                          <div className="flex-1">
                            <h3 className="font-bold text-gray-900 mb-2 text-lg">
                              {project.name}
                            </h3>
                            <p className="text-gray-600 text-sm line-clamp-2">
                              {project.description}
                            </p>
                          </div>
                          <div className="ml-4">
                            <span className={`zena-badge ${statusConfig.color}`}>
                              <i className={`${statusConfig.icon} mr-1`}></i>
                              {statusConfig.text}
                            </span>
                          </div>
                        </div>

                        {/* Progress */}
                        <div className="mb-6">
                          <div className="flex justify-between items-center mb-2">
                            <span className="text-sm font-medium text-gray-700">Tiến độ</span>
                            <span className="text-sm font-bold text-gray-900">{project.progress || 0}%</span>
                          </div>
                          <div className="zena-progress">
                            <div 
                              className={`zena-progress-bar ${getProgressColor(project.progress || 0)}`}
                              style={{ width: `${project.progress || 0}%` }}
                            />
                          </div>
                        </div>

                        {/* Dates */}
                        <div className="flex items-center justify-between text-sm text-gray-600 mb-6">
                          <div className="flex items-center gap-2">
                            <i className="fas fa-calendar text-blue-500"></i>
                            <span className="font-medium">{formatDate(project.start_date)}</span>
                          </div>
                          <div className="flex items-center gap-2">
                            <i className="fas fa-clock text-green-500"></i>
                            <span className="font-medium">{formatDate(project.end_date)}</span>
                          </div>
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end gap-2 pt-4 border-t border-gray-100">
                          <Link
                            to={`/app/projects/${project.id}/contracts`}
                            className="zena-btn zena-btn-outline zena-btn-sm"
                            aria-label={`View contracts for ${project.name}`}
                          >
                            <i className="fas fa-file-signature mr-1"></i>
                            Contracts
                          </Link>
                          <button className="zena-btn zena-btn-outline zena-btn-sm">
                            <i className="fas fa-eye"></i>
                          </button>
                          <button className="zena-btn zena-btn-outline zena-btn-sm">
                            <i className="fas fa-edit"></i>
                          </button>
                          <button className="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger">
                            <i className="fas fa-trash"></i>
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="zena-table">
                  <table className="min-w-full">
                    <thead>
                      <tr>
                        <th>Dự án</th>
                        <th>Trạng thái</th>
                        <th>Tiến độ</th>
                        <th>Thời gian</th>
                        <th>Thao tác</th>
                      </tr>
                    </thead>
                    <tbody>
                      {projects.map((project) => {
                        const statusConfig = getStatusConfig(project.status);
                        
                        return (
                          <tr key={project.id}>
                            <td>
                              <div>
                                <div className="text-sm font-bold text-gray-900">
                                  {project.name}
                                </div>
                                <div className="text-sm text-gray-600 mt-1">
                                  {project.description}
                                </div>
                              </div>
                            </td>
                            <td>
                              <span className={`zena-badge ${statusConfig.color}`}>
                                <i className={`${statusConfig.icon} mr-1`}></i>
                                {statusConfig.text}
                              </span>
                            </td>
                            <td>
                              <div className="flex items-center">
                                <div className="w-20 zena-progress mr-3">
                                  <div 
                                    className={`zena-progress-bar ${getProgressColor(project.progress || 0)}`}
                                    style={{ width: `${project.progress || 0}%` }}
                                  />
                                </div>
                                <span className="text-sm font-bold text-gray-900">
                                  {project.progress || 0}%
                                </span>
                              </div>
                            </td>
                            <td>
                              <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                  <i className="fas fa-calendar text-blue-500"></i>
                                  <span className="font-medium">{formatDate(project.start_date)}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                  <i className="fas fa-clock text-green-500"></i>
                                  <span className="font-medium">{formatDate(project.end_date)}</span>
                                </div>
                              </div>
                            </td>
                            <td>
                              <div className="flex space-x-2">
                                <Link
                                  to={`/app/projects/${project.id}/contracts`}
                                  className="zena-btn zena-btn-outline zena-btn-sm"
                                  aria-label={`View contracts for ${project.name}`}
                                >
                                  <i className="fas fa-file-signature mr-1"></i>
                                  Contracts
                                </Link>
                                <button className="zena-btn zena-btn-outline zena-btn-sm">
                                  <i className="fas fa-eye"></i>
                                </button>
                                <button className="zena-btn zena-btn-outline zena-btn-sm">
                                  <i className="fas fa-edit"></i>
                                </button>
                                <button className="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger">
                                  <i className="fas fa-trash"></i>
                                </button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Pagination */}
        {pagination && pagination.total > 0 && (
          <div className="mt-8">
            <div className="dashboard-card p-6">
              <div className="flex items-center justify-between">
                <div className="flex-1 flex justify-between sm:hidden">
                  <button className="zena-btn zena-btn-outline">
                    ← Trước
                  </button>
                  <button className="zena-btn zena-btn-outline">
                    Sau →
                  </button>
                </div>
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Hiển thị <span className="font-bold text-blue-600">1</span> đến{' '}
                      <span className="font-bold text-blue-600">{pagination.per_page}</span> trong tổng số{' '}
                      <span className="font-bold text-blue-600">{pagination.total}</span> dự án
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-lg shadow-sm -space-x-px">
                      <button className="zena-btn zena-btn-outline">
                        ← Trước
                      </button>
                      <button className="zena-btn zena-btn-primary">
                        1
                      </button>
                      <button className="zena-btn zena-btn-outline">
                        Sau →
                      </button>
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
