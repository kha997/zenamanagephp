import React, { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useProjectStore } from '@/store/projects';
import { useTaskStore } from '@/store/tasks';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/Tabs';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { ErrorMessage } from '@/components/ui/ErrorMessage';
import { ResponsiveContainer } from '@/components/ui/ResponsiveContainer';
import { 
  ArrowLeft, 
  Edit, 
  Share2, 
  Download, 
  MoreVertical,
  Calendar,
  Users,
  TrendingUp,
  DollarSign,
  Clock,
  CheckCircle,
  AlertCircle,
  FileText,
  MessageSquare
} from 'lucide-react';
import { cn } from '@/lib/utils';

export const ProjectDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { currentProject, isLoading, error, fetchProject } = useProjectStore();
  const { tasks, fetchTasksByProject } = useTaskStore();
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    if (id) {
      fetchProject(parseInt(id));
      fetchTasksByProject(parseInt(id));
    }
  }, [id, fetchProject, fetchTasksByProject]);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active': return 'bg-green-100 text-green-800 border-green-200';
      case 'completed': return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'on_hold': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'cancelled': return 'bg-red-100 text-red-800 border-red-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'active': return 'Đang thực hiện';
      case 'completed': return 'Hoàn thành';
      case 'on_hold': return 'Tạm dừng';
      case 'cancelled': return 'Đã hủy';
      default: return status;
    }
  };

  const getProgressColor = (progress: number) => {
    if (progress >= 80) return 'bg-green-500';
    if (progress >= 50) return 'bg-blue-500';
    if (progress >= 25) return 'bg-yellow-500';
    return 'bg-red-500';
  };

  // Calculate project statistics
  const projectStats = React.useMemo(() => {
    if (!tasks.length) return null;
    
    const totalTasks = tasks.length;
    const completedTasks = tasks.filter(task => task.status === 'completed').length;
    const inProgressTasks = tasks.filter(task => task.status === 'in_progress').length;
    const overdueTasks = tasks.filter(task => {
      const endDate = new Date(task.end_date);
      return endDate < new Date() && task.status !== 'completed';
    }).length;

    return {
      totalTasks,
      completedTasks,
      inProgressTasks,
      overdueTasks,
      completionRate: totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0
    };
  }, [tasks]);

  if (isLoading) {
    return (
      <ResponsiveContainer>
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="lg" />
        </div>
      </ResponsiveContainer>
    );
  }

  if (error) {
    return (
      <ResponsiveContainer>
        <ErrorMessage 
          message={error}
          onRetry={() => id && fetchProject(parseInt(id))}
        />
      </ResponsiveContainer>
    );
  }

  if (!currentProject) {
    return (
      <ResponsiveContainer>
        <div className="text-center py-12">
          <AlertCircle className="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">Không tìm thấy dự án</h3>
          <p className="text-gray-600 mb-4">Dự án bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</p>
          <Button onClick={() => navigate('/projects')}>Quay lại danh sách dự án</Button>
        </div>
      </ResponsiveContainer>
    );
  }

  return (
    <ResponsiveContainer>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
          <div className="flex items-start gap-4 flex-1">
            <Button variant="outline" size="sm" onClick={() => navigate('/projects')}>
              <ArrowLeft className="w-4 h-4 mr-2" />
              Quay lại
            </Button>
            
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-2">
                <h1 className="text-2xl font-bold text-gray-900">{currentProject.name}</h1>
                <Badge className={cn('text-sm', getStatusColor(currentProject.status))}>
                  {getStatusText(currentProject.status)}
                </Badge>
              </div>
              
              <p className="text-gray-600 mb-3">{currentProject.description}</p>
              
              <div className="flex items-center gap-4 text-sm text-gray-500">
                <span className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  {new Date(currentProject.start_date).toLocaleDateString('vi-VN')} - {new Date(currentProject.end_date).toLocaleDateString('vi-VN')}
                </span>
                <span className="flex items-center gap-1">
                  <TrendingUp className="w-4 h-4" />
                  Tiến độ: {currentProject.progress}%
                </span>
              </div>
            </div>
          </div>
          
          <div className="flex gap-2">
            <Button variant="outline" size="sm">
              <Share2 className="w-4 h-4 mr-2" />
              Chia sẻ
            </Button>
            <Button variant="outline" size="sm">
              <Download className="w-4 h-4 mr-2" />
              Xuất báo cáo
            </Button>
            <Button size="sm">
              <Edit className="w-4 h-4 mr-2" />
              Chỉnh sửa
            </Button>
            <Button variant="outline" size="sm">
              <MoreVertical className="w-4 h-4" />
            </Button>
          </div>
        </div>

        {/* Progress Overview */}
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-900">Tổng quan tiến độ</h3>
            <span className="text-2xl font-bold text-gray-900">{currentProject.progress}%</span>
          </div>
          
          <div className="w-full bg-gray-200 rounded-full h-4 mb-4">
            <div 
              className={cn(
                'h-4 rounded-full transition-all duration-500',
                getProgressColor(currentProject.progress)
              )}
              style={{ width: `${Math.min(currentProject.progress, 100)}%` }}
            />
          </div>
          
          {projectStats && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
              <div>
                <div className="text-2xl font-bold text-blue-600">{projectStats.totalTasks}</div>
                <div className="text-sm text-gray-600">Tổng nhiệm vụ</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-green-600">{projectStats.completedTasks}</div>
                <div className="text-sm text-gray-600">Đã hoàn thành</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-yellow-600">{projectStats.inProgressTasks}</div>
                <div className="text-sm text-gray-600">Đang thực hiện</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-red-600">{projectStats.overdueTasks}</div>
                <div className="text-sm text-gray-600">Quá hạn</div>
              </div>
            </div>
          )}
        </Card>

        {/* Main Content Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList className="grid w-full grid-cols-5">
            <TabsTrigger value="overview">Tổng quan</TabsTrigger>
            <TabsTrigger value="tasks">Nhiệm vụ</TabsTrigger>
            <TabsTrigger value="timeline">Tiến độ</TabsTrigger>
            <TabsTrigger value="team">Nhóm</TabsTrigger>
            <TabsTrigger value="documents">Tài liệu</TabsTrigger>
          </TabsList>

          <TabsContent value="overview" className="space-y-6">
            {/* Project Statistics */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <Card className="p-6">
                <div className="flex items-center">
                  <div className="p-3 bg-blue-100 rounded-lg">
                    <DollarSign className="w-6 h-6 text-blue-600" />
                  </div>
                  <div className="ml-4">
                    <p className="text-sm font-medium text-gray-600">Chi phí thực tế</p>
                    <p className="text-2xl font-bold text-gray-900">
                      {currentProject.actual_cost ? 
                        new Intl.NumberFormat('vi-VN', {
                          style: 'currency',
                          currency: 'VND'
                        }).format(currentProject.actual_cost) : 
                        'Chưa cập nhật'
                      }
                    </p>
                  </div>
                </div>
              </Card>

              <Card className="p-6">
                <div className="flex items-center">
                  <div className="p-3 bg-green-100 rounded-lg">
                    <Clock className="w-6 h-6 text-green-600" />
                  </div>
                  <div className="ml-4">
                    <p className="text-sm font-medium text-gray-600">Thời gian còn lại</p>
                    <p className="text-2xl font-bold text-gray-900">
                      {Math.max(0, Math.ceil((new Date(currentProject.end_date).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)))} ngày
                    </p>
                  </div>
                </div>
              </Card>

              <Card className="p-6">
                <div className="flex items-center">
                  <div className="p-3 bg-purple-100 rounded-lg">
                    <Users className="w-6 h-6 text-purple-600" />
                  </div>
                  <div className="ml-4">
                    <p className="text-sm font-medium text-gray-600">Thành viên</p>
                    <p className="text-2xl font-bold text-gray-900">8</p>
                  </div>
                </div>
              </Card>
            </div>

            {/* Recent Activities */}
            <Card className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Hoạt động gần đây</h3>
              <div className="space-y-4">
                <div className="flex items-start gap-3">
                  <div className="p-2 bg-blue-100 rounded-full">
                    <CheckCircle className="w-4 h-4 text-blue-600" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm text-gray-900">Nhiệm vụ "Thiết kế giao diện" đã được hoàn thành</p>
                    <p className="text-xs text-gray-500">2 giờ trước</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="p-2 bg-green-100 rounded-full">
                    <FileText className="w-4 h-4 text-green-600" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm text-gray-900">Tài liệu "Yêu cầu kỹ thuật" đã được cập nhật</p>
                    <p className="text-xs text-gray-500">1 ngày trước</p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <div className="p-2 bg-yellow-100 rounded-full">
                    <MessageSquare className="w-4 h-4 text-yellow-600" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm text-gray-900">Nhận xét mới từ khách hàng về thiết kế</p>
                    <p className="text-xs text-gray-500">2 ngày trước</p>
                  </div>
                </div>
              </div>
            </Card>
          </TabsContent>

          <TabsContent value="tasks">
            <Card className="p-6">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-gray-900">Danh sách nhiệm vụ</h3>
                <Button size="sm" asChild>
                  <Link to={`/projects/${currentProject.id}/tasks`}>
                    Xem tất cả
                  </Link>
                </Button>
              </div>
              
              {tasks.length > 0 ? (
                <div className="space-y-3">
                  {tasks.slice(0, 5).map((task) => (
                    <div key={task.id} className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
                      <div className="flex items-center gap-3">
                        <div className={cn(
                          'w-3 h-3 rounded-full',
                          task.status === 'completed' ? 'bg-green-500' :
                          task.status === 'in_progress' ? 'bg-blue-500' :
                          task.status === 'pending' ? 'bg-gray-400' : 'bg-red-500'
                        )} />
                        <div>
                          <p className="font-medium text-gray-900">{task.name}</p>
                          <p className="text-sm text-gray-500">
                            {new Date(task.start_date).toLocaleDateString('vi-VN')} - {new Date(task.end_date).toLocaleDateString('vi-VN')}
                          </p>
                        </div>
                      </div>
                      <Badge className={cn(
                        'text-xs',
                        task.status === 'completed' ? 'bg-green-100 text-green-800' :
                        task.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                        task.status === 'pending' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800'
                      )}>
                        {task.status === 'completed' ? 'Hoàn thành' :
                         task.status === 'in_progress' ? 'Đang thực hiện' :
                         task.status === 'pending' ? 'Chờ thực hiện' : 'Quá hạn'}
                      </Badge>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-600 text-center py-8">Chưa có nhiệm vụ nào được tạo.</p>
              )}
            </Card>
          </TabsContent>

          <TabsContent value="timeline">
            <Card className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Biểu đồ tiến độ</h3>
              <p className="text-gray-600">Biểu đồ Gantt sẽ được hiển thị ở đây.</p>
            </Card>
          </TabsContent>

          <TabsContent value="team">
            <Card className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Thành viên nhóm</h3>
              <p className="text-gray-600">Danh sách thành viên tham gia dự án sẽ được hiển thị ở đây.</p>
            </Card>
          </TabsContent>

          <TabsContent value="documents">
            <Card className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Tài liệu dự án</h3>
              <p className="text-gray-600">Danh sách tài liệu liên quan đến dự án sẽ được hiển thị ở đây.</p>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </ResponsiveContainer>
  );
};