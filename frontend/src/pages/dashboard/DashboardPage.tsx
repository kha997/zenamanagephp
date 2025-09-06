import React from 'react';
import { useAuthStore } from '@/store/auth';
import { useProjectsStore } from '@/store/projects';
import { useTasksStore } from '@/store/tasks';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';
import { 
  BarChart3, 
  Calendar, 
  CheckCircle, 
  Clock, 
  FolderOpen, 
  TrendingUp,
  Users,
  AlertTriangle
} from 'lucide-react';

/**
 * Trang dashboard chính hiển thị tổng quan hệ thống
 * Bao gồm thống kê dự án, task, tiến độ và thông báo
 */
export const DashboardPage: React.FC = () => {
  const { user } = useAuthStore();
  const { projects, isLoading: projectsLoading } = useProjectsStore();
  const { tasks, isLoading: tasksLoading } = useTasksStore();

  // Tính toán thống kê
  const stats = React.useMemo(() => {
    const activeProjects = projects.filter(p => p.status === 'active').length;
    const completedProjects = projects.filter(p => p.status === 'completed').length;
    const pendingTasks = tasks.filter(t => t.status === 'pending').length;
    const completedTasks = tasks.filter(t => t.status === 'completed').length;
    const overdueTasks = tasks.filter(t => 
      t.status !== 'completed' && new Date(t.end_date) < new Date()
    ).length;

    return {
      activeProjects,
      completedProjects,
      pendingTasks,
      completedTasks,
      overdueTasks,
      totalProjects: projects.length,
      totalTasks: tasks.length
    };
  }, [projects, tasks]);

  if (projectsLoading || tasksLoading) {
    return <Loading.Skeleton />;
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">
            Chào mừng trở lại, {user?.name}!
          </h1>
          <p className="text-gray-600 mt-1">
            Tổng quan hoạt động hệ thống Z.E.N.A
          </p>
        </div>
        <div className="flex gap-3">
          <Button variant="outline" size="sm">
            <Calendar className="w-4 h-4 mr-2" />
            Lịch làm việc
          </Button>
          <Button size="sm">
            <FolderOpen className="w-4 h-4 mr-2" />
            Dự án mới
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Dự án đang thực hiện</p>
                <p className="text-2xl font-bold text-gray-900">{stats.activeProjects}</p>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <FolderOpen className="w-6 h-6 text-blue-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <TrendingUp className="w-4 h-4 text-green-500 mr-1" />
              <span className="text-green-600">+12%</span>
              <span className="text-gray-600 ml-1">so với tháng trước</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Nhiệm vụ chờ xử lý</p>
                <p className="text-2xl font-bold text-gray-900">{stats.pendingTasks}</p>
              </div>
              <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <Clock className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-gray-600">Từ {stats.totalTasks} tổng nhiệm vụ</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Nhiệm vụ hoàn thành</p>
                <p className="text-2xl font-bold text-gray-900">{stats.completedTasks}</p>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <CheckCircle className="w-6 h-6 text-green-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-green-600">
                {Math.round((stats.completedTasks / stats.totalTasks) * 100)}%
              </span>
              <span className="text-gray-600 ml-1">tỷ lệ hoàn thành</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Nhiệm vụ quá hạn</p>
                <p className="text-2xl font-bold text-gray-900">{stats.overdueTasks}</p>
              </div>
              <div className="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-red-600">Cần xử lý ngay</span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Charts and Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Project Progress Chart */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <BarChart3 className="w-5 h-5 mr-2" />
              Tiến độ dự án
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {projects.slice(0, 5).map((project) => (
                <div key={project.id} className="flex items-center justify-between">
                  <div className="flex-1">
                    <p className="font-medium text-gray-900">{project.name}</p>
                    <div className="w-full bg-gray-200 rounded-full h-2 mt-1">
                      <div 
                        className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                        style={{ width: `${project.progress}%` }}
                      />
                    </div>
                  </div>
                  <span className="ml-4 text-sm font-medium text-gray-600">
                    {project.progress}%
                  </span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Recent Tasks */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <CheckCircle className="w-5 h-5 mr-2" />
              Nhiệm vụ gần đây
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {tasks.slice(0, 5).map((task) => (
                <div key={task.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div className="flex-1">
                    <p className="font-medium text-gray-900">{task.name}</p>
                    <p className="text-sm text-gray-600">
                      Hạn: {new Date(task.end_date).toLocaleDateString('vi-VN')}
                    </p>
                  </div>
                  <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                    task.status === 'completed' 
                      ? 'bg-green-100 text-green-800'
                      : task.status === 'in_progress'
                      ? 'bg-blue-100 text-blue-800'
                      : 'bg-gray-100 text-gray-800'
                  }`}>
                    {task.status === 'completed' ? 'Hoàn thành' :
                     task.status === 'in_progress' ? 'Đang thực hiện' : 'Chờ xử lý'}
                  </span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};