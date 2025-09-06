import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Progress } from '@/components/ui/Progress';
import { Badge } from '@/components/ui/Badge';
import { 
  TrendingUp, 
  TrendingDown, 
  DollarSign, 
  Calendar, 
  Users, 
  CheckCircle, 
  Clock, 
  AlertTriangle,
  Target,
  Activity
} from 'lucide-react';
import { Project, Task } from '@/lib/types';
import { cn } from '@/lib/utils';

interface ProjectStatsProps {
  project: Project;
  tasks?: Task[];
  className?: string;
}

interface StatCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  trend?: {
    value: number;
    isPositive: boolean;
  };
  color?: 'blue' | 'green' | 'yellow' | 'red' | 'purple';
  className?: string;
}

const StatCard: React.FC<StatCardProps> = ({ 
  title, 
  value, 
  icon, 
  trend, 
  color = 'blue',
  className 
}) => {
  const colorClasses = {
    blue: 'bg-blue-50 text-blue-600 border-blue-200',
    green: 'bg-green-50 text-green-600 border-green-200',
    yellow: 'bg-yellow-50 text-yellow-600 border-yellow-200',
    red: 'bg-red-50 text-red-600 border-red-200',
    purple: 'bg-purple-50 text-purple-600 border-purple-200'
  };

  return (
    <Card className={cn('hover:shadow-md transition-shadow', className)}>
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <p className="text-sm font-medium text-gray-600 mb-1">{title}</p>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            {trend && (
              <div className="flex items-center mt-2">
                {trend.isPositive ? (
                  <TrendingUp className="w-4 h-4 text-green-500 mr-1" />
                ) : (
                  <TrendingDown className="w-4 h-4 text-red-500 mr-1" />
                )}
                <span className={cn(
                  'text-sm font-medium',
                  trend.isPositive ? 'text-green-600' : 'text-red-600'
                )}>
                  {trend.value > 0 ? '+' : ''}{trend.value}%
                </span>
              </div>
            )}
          </div>
          <div className={cn(
            'w-12 h-12 rounded-lg flex items-center justify-center',
            colorClasses[color]
          )}>
            {icon}
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

const ProjectStats: React.FC<ProjectStatsProps> = ({ project, tasks = [], className }) => {
  // Tính toán thống kê từ tasks
  const taskStats = React.useMemo(() => {
    const total = tasks.length;
    const completed = tasks.filter(task => task.status === 'completed').length;
    const inProgress = tasks.filter(task => task.status === 'in_progress').length;
    const pending = tasks.filter(task => task.status === 'pending').length;
    const overdue = tasks.filter(task => {
      const endDate = new Date(task.end_date);
      return endDate < new Date() && task.status !== 'completed';
    }).length;

    return {
      total,
      completed,
      inProgress,
      pending,
      overdue,
      completionRate: total > 0 ? Math.round((completed / total) * 100) : 0
    };
  }, [tasks]);

  // Tính toán thời gian dự án
  const projectDuration = React.useMemo(() => {
    const startDate = new Date(project.start_date);
    const endDate = project.end_date ? new Date(project.end_date) : new Date();
    const totalDays = Math.ceil((endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24));
    const elapsedDays = Math.ceil((new Date().getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24));
    const remainingDays = Math.max(0, totalDays - elapsedDays);
    
    return {
      totalDays,
      elapsedDays,
      remainingDays,
      timeProgress: totalDays > 0 ? Math.min(100, Math.round((elapsedDays / totalDays) * 100)) : 0
    };
  }, [project.start_date, project.end_date]);

  // Tính toán chi phí
  const costStats = React.useMemo(() => {
    const planned = project.planned_cost || 0;
    const actual = project.actual_cost || 0;
    const variance = planned > 0 ? Math.round(((actual - planned) / planned) * 100) : 0;
    
    return {
      planned,
      actual,
      variance,
      isOverBudget: actual > planned
    };
  }, [project.planned_cost, project.actual_cost]);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(amount);
  };

  return (
    <div className={cn('space-y-6', className)}>
      {/* Thống kê tổng quan */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="Tiến độ dự án"
          value={`${project.progress}%`}
          icon={<Target className="w-6 h-6" />}
          color={project.progress >= 80 ? 'green' : project.progress >= 50 ? 'blue' : 'yellow'}
        />
        
        <StatCard
          title="Nhiệm vụ hoàn thành"
          value={`${taskStats.completed}/${taskStats.total}`}
          icon={<CheckCircle className="w-6 h-6" />}
          color="green"
          trend={{
            value: taskStats.completionRate,
            isPositive: taskStats.completionRate >= 70
          }}
        />
        
        <StatCard
          title="Chi phí thực tế"
          value={formatCurrency(costStats.actual)}
          icon={<DollarSign className="w-6 h-6" />}
          color={costStats.isOverBudget ? 'red' : 'green'}
          trend={costStats.planned > 0 ? {
            value: costStats.variance,
            isPositive: !costStats.isOverBudget
          } : undefined}
        />
        
        <StatCard
          title="Thời gian còn lại"
          value={`${projectDuration.remainingDays} ngày`}
          icon={<Calendar className="w-6 h-6" />}
          color={projectDuration.remainingDays < 7 ? 'red' : projectDuration.remainingDays < 30 ? 'yellow' : 'blue'}
        />
      </div>

      {/* Biểu đồ tiến độ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Tiến độ công việc */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Activity className="w-5 h-5" />
              Tiến độ công việc
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium">Hoàn thành</span>
                <span className="text-sm text-gray-600">{taskStats.completed} nhiệm vụ</span>
              </div>
              <Progress 
                value={taskStats.completionRate} 
                className="h-2"
                indicatorClassName="bg-green-500"
              />
            </div>
            
            <div className="grid grid-cols-3 gap-4 pt-4 border-t">
              <div className="text-center">
                <p className="text-2xl font-bold text-blue-600">{taskStats.inProgress}</p>
                <p className="text-xs text-gray-600">Đang thực hiện</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-yellow-600">{taskStats.pending}</p>
                <p className="text-xs text-gray-600">Chờ thực hiện</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-red-600">{taskStats.overdue}</p>
                <p className="text-xs text-gray-600">Quá hạn</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tiến độ thời gian */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Clock className="w-5 h-5" />
              Tiến độ thời gian
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium">Thời gian đã trôi qua</span>
                <span className="text-sm text-gray-600">{projectDuration.timeProgress}%</span>
              </div>
              <Progress 
                value={projectDuration.timeProgress} 
                className="h-2"
                indicatorClassName={cn(
                  projectDuration.timeProgress > project.progress ? 'bg-red-500' : 'bg-blue-500'
                )}
              />
            </div>
            
            <div className="grid grid-cols-2 gap-4 pt-4 border-t">
              <div className="text-center">
                <p className="text-2xl font-bold text-blue-600">{projectDuration.elapsedDays}</p>
                <p className="text-xs text-gray-600">Ngày đã qua</p>
              </div>
              <div className="text-center">
                <p className="text-2xl font-bold text-green-600">{projectDuration.remainingDays}</p>
                <p className="text-xs text-gray-600">Ngày còn lại</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Cảnh báo */}
      {(taskStats.overdue > 0 || costStats.isOverBudget) && (
        <Card className="border-yellow-200 bg-yellow-50">
          <CardContent className="p-4">
            <div className="flex items-start gap-3">
              <AlertTriangle className="w-5 h-5 text-yellow-600 mt-0.5" />
              <div className="flex-1">
                <h4 className="font-medium text-yellow-800 mb-2">Cảnh báo dự án</h4>
                <ul className="space-y-1 text-sm text-yellow-700">
                  {taskStats.overdue > 0 && (
                    <li>• Có {taskStats.overdue} nhiệm vụ đã quá hạn</li>
                  )}
                  {costStats.isOverBudget && (
                    <li>• Chi phí thực tế vượt quá ngân sách {Math.abs(costStats.variance)}%</li>
                  )}
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
};

export default ProjectStats;