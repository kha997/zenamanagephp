import React from 'react';
import { Link } from 'react-router-dom';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Calendar, Users, TrendingUp, MoreVertical } from 'lucide-react';
import { Project } from '../types';
import { cn } from '@/lib/utils';

interface ProjectCardProps {
  project: Project;
  onEdit?: (project: Project) => void;
  onDelete?: (project: Project) => void;
  className?: string;
}

export const ProjectCard: React.FC<ProjectCardProps> = ({
  project,
  onEdit,
  onDelete,
  className
}) => {
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

  return (
    <Card className={cn(
      'group hover:shadow-lg transition-all duration-200 border-l-4',
      project.status === 'active' ? 'border-l-green-500' : 
      project.status === 'completed' ? 'border-l-blue-500' :
      project.status === 'on_hold' ? 'border-l-yellow-500' : 'border-l-red-500',
      className
    )}>
      <div className="p-6">
        {/* Header với actions */}
        <div className="flex justify-between items-start mb-4">
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2">
              <h3 className="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                {project.name}
              </h3>
              <Badge className={cn('text-xs', getStatusColor(project.status))}>
                {getStatusText(project.status)}
              </Badge>
            </div>
            <p className="text-gray-600 text-sm line-clamp-2 mb-3">
              {project.description || 'Không có mô tả'}
            </p>
          </div>
          
          {(onEdit || onDelete) && (
            <div className="relative group/menu">
              <Button
                variant="ghost"
                size="sm"
                className="opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <MoreVertical className="w-4 h-4" />
              </Button>
              <div className="absolute right-0 top-8 bg-white border rounded-md shadow-lg py-1 z-10 opacity-0 group-hover/menu:opacity-100 transition-opacity">
                {onEdit && (
                  <button
                    onClick={() => onEdit(project)}
                    className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  >
                    Chỉnh sửa
                  </button>
                )}
                {onDelete && (
                  <button
                    onClick={() => onDelete(project)}
                    className="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                  >
                    Xóa
                  </button>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Progress Bar */}
        <div className="mb-4">
          <div className="flex justify-between items-center mb-2">
            <span className="text-sm font-medium text-gray-700 flex items-center gap-1">
              <TrendingUp className="w-4 h-4" />
              Tiến độ
            </span>
            <span className="text-sm font-semibold text-gray-900">
              {project.progress}%
            </span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2.5">
            <div 
              className={cn(
                'h-2.5 rounded-full transition-all duration-500',
                getProgressColor(project.progress)
              )}
              style={{ width: `${Math.min(project.progress, 100)}%` }}
            />
          </div>
        </div>

        {/* Thông tin chi tiết */}
        <div className="grid grid-cols-2 gap-4 mb-4 text-sm">
          <div className="flex items-center gap-2 text-gray-600">
            <Calendar className="w-4 h-4" />
            <div>
              <p className="font-medium">Bắt đầu</p>
              <p>{new Date(project.start_date).toLocaleDateString('vi-VN')}</p>
            </div>
          </div>
          <div className="flex items-center gap-2 text-gray-600">
            <Calendar className="w-4 h-4" />
            <div>
              <p className="font-medium">Kết thúc</p>
              <p>{new Date(project.end_date).toLocaleDateString('vi-VN')}</p>
            </div>
          </div>
        </div>

        {/* Cost information */}
        {project.actual_cost && (
          <div className="mb-4 p-3 bg-gray-50 rounded-lg">
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-600">Chi phí thực tế:</span>
              <span className="font-semibold text-gray-900">
                {new Intl.NumberFormat('vi-VN', {
                  style: 'currency',
                  currency: 'VND'
                }).format(project.actual_cost)}
              </span>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex gap-2">
          <Button asChild variant="outline" className="flex-1">
            <Link to={`/projects/${project.id}`}>
              Xem chi tiết
            </Link>
          </Button>
          <Button asChild size="sm" className="px-3">
            <Link to={`/projects/${project.id}/tasks`}>
              <Users className="w-4 h-4" />
            </Link>
          </Button>
        </div>
      </div>
    </Card>
  );
};