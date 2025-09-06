import React from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/Card';
import { Button } from '@/components/Button';
import { Loading } from '@/components/Loading';
import { apiClient } from '@/lib/api-client';
import {
  ArrowLeft,
  Calendar,
  DollarSign,
  Edit,
  Users,
  Clock,
  CheckCircle,
  AlertCircle,
  BarChart3
} from 'lucide-react';

interface ProjectDetail {
  id: number;
  name: string;
  description: string;
  status: string;
  progress: number;
  start_date: string;
  end_date: string;
  actual_cost: number;
  created_at: string;
  updated_at: string;
}

const ProjectDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();

  const { data: project, isLoading, error } = useQuery({
    queryKey: ['project', id],
    queryFn: async (): Promise<ProjectDetail> => {
      const response = await apiClient.get(`/projects/${id}`);
      return response.data;
    },
    enabled: !!id
  });

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
    switch (status) {
      case 'completed':
        return 'Hoàn thành';
      case 'in_progress':
        return 'Đang thực hiện';
      case 'on_hold':
        return 'Tạm dừng';
      case 'planning':
        return 'Lên kế hoạch';
      case 'cancelled':
        return 'Đã hủy';
      default:
        return status;
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('vi-VN', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center min-h-96">
        <Loading size="lg" />
      </div>
    );
  }

  if (error || !project) {
    return (
      <div className="text-center py-12">
        <AlertCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
        <h2 className="text-2xl font-bold text-gray-900 mb-2">Không tìm thấy dự án</h2>
        <p className="text-gray-600 mb-4">Dự án bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</p>
        <Button asChild>
          <Link to="/projects">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Quay lại danh sách
          </Link>
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="outline" asChild>
            <Link to="/projects">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Quay lại
            </Link>
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-gray-900">{project.name}</h1>
            <div className="flex items-center gap-3 mt-2">
              <span className={`px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(project.status)}`}>
                {getStatusText(project.status)}
              </span>
              <span className="text-gray-500 text-sm">
                Cập nhật: {formatDate(project.updated_at)}
              </span>
            </div>
          </div>
        </div>
        <div className="flex gap-3">
          <Button variant="outline" asChild>
            <Link to={`/projects/${project.id}/edit`}>
              <Edit className="w-4 h-4 mr-2" />
              Chỉnh sửa
            </Link>
          </Button>
          <Button asChild>
            <Link to={`/projects/${project.id}/tasks`}>
              <CheckCircle className="w-4 h-4 mr-2" />
              Xem nhiệm vụ
            </Link>
          </Button>
        </div>
      </div>

      {/* Project Overview */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Thông tin dự án</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {/* Description */}
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-2">Mô tả</h3>
                  <p className="text-gray-600 leading-relaxed">
                    {project.description || 'Chưa có mô tả cho dự án này.'}
                  </p>
                </div>

                {/* Progress */}
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-3">Tiến độ thực hiện</h3>
                  <div className="space-y-3">
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-600">Hoàn thành</span>
                      <span className="font-medium">{project.progress}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-3">
                      <div
                        className="bg-blue-600 h-3 rounded-full transition-all duration-300"
                        style={{ width: `${project.progress}%` }}
                      ></div>
                    </div>
                    <div className="grid grid-cols-3 gap-4 text-sm">
                      <div className="text-center">
                        <div className="text-2xl font-bold text-blue-600">{project.progress}%</div>
                        <div className="text-gray-500">Hoàn thành</div>
                      </div>
                      <div className="text-center">
                        <div className="text-2xl font-bold text-yellow-600">{100 - project.progress}%</div>
                        <div className="text-gray-500">Còn lại</div>
                      </div>
                      <div className="text-center">
                        <div className="text-2xl font-bold text-green-600">0</div>
                        <div className="text-gray-500">Quá hạn</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Project Stats */}
          <Card>
            <CardHeader>
              <CardTitle>Thống kê</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {/* Timeline */}
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Calendar className="w-5 h-5 text-blue-600" />
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Thời gian</p>
                    <p className="font-medium">
                      {formatDate(project.start_date)} - {formatDate(project.end_date)}
                    </p>
                  </div>
                </div>

                {/* Budget */}
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <DollarSign className="w-5 h-5 text-green-600" />
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Chi phí thực tế</p>
                    <p className="font-medium">{formatCurrency(project.actual_cost)}</p>
                  </div>
                </div>

                {/* Team */}
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <Users className="w-5 h-5 text-purple-600" />
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Thành viên</p>
                    <p className="font-medium">5 người</p>
                  </div>
                </div>

                {/* Tasks */}
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <Clock className="w-5 h-5 text-yellow-600" />
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Nhiệm vụ</p>
                    <p className="font-medium">12/20 hoàn thành</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader>
              <CardTitle>Thao tác nhanh</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                <Button className="w-full justify-start" variant="outline" asChild>
                  <Link to={`/projects/${project.id}/tasks`}>
                    <CheckCircle className="w-4 h-4 mr-2" />
                    Quản lý nhiệm vụ
                  </Link>
                </Button>
                <Button className="w-full justify-start" variant="outline" asChild>
                  <Link to={`/projects/${project.id}/team`}>
                    <Users className="w-4 h-4 mr-2" />
                    Quản lý nhóm
                  </Link>
                </Button>
                <Button className="w-full justify-start" variant="outline" asChild>
                  <Link to={`/projects/${project.id}/reports`}>
                    <BarChart3 className="w-4 h-4 mr-2" />
                    Báo cáo
                  </Link>
                </Button>
                <Button className="w-full justify-start" variant="outline" asChild>
                  <Link to={`/projects/${project.id}/documents`}>
                    <Calendar className="w-4 h-4 mr-2" />
                    Tài liệu
                  </Link>
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
};

export default ProjectDetailPage;