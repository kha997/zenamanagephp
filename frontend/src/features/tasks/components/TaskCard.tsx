import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Progress } from '@/components/ui/Progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/Avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
  Calendar,
  Clock,
  User,
  MoreHorizontal,
  Eye,
  Edit,
  Trash2,
  AlertTriangle,
  CheckCircle,
  PlayCircle,
  PauseCircle,
  Flag,
  Link as LinkIcon
} from 'lucide-react';
import { Task } from '@/lib/types';
import { cn } from '@/lib/utils';
import { format, isAfter, isBefore, differenceInDays } from 'date-fns';
import { vi } from 'date-fns/locale';

interface TaskCardProps {
  task: Task;
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: string) => void;
  onStatusChange?: (taskId: string, status: string) => void;
  onAssign?: (taskId: string) => void;
  className?: string;
  showProject?: boolean;
  compact?: boolean;
}

/**
 * TaskCard component với thiết kế hiện đại và tính năng tương tác phong phú
 * Hỗ trợ drag & drop, hover effects, và quick actions
 */
export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  onEdit,
  onDelete,
  onStatusChange,
  onAssign,
  className,
  showProject = false,
  compact = false
}) => {
  const [isHovered, setIsHovered] = useState(false);

  // Tính toán trạng thái và màu sắc
  const getStatusConfig = (status: string) => {
    const configs = {
      pending: {
        color: 'bg-gray-100 text-gray-800 border-gray-200',
        icon: Clock,
        label: 'Chờ thực hiện'
      },
      in_progress: {
        color: 'bg-blue-100 text-blue-800 border-blue-200',
        icon: PlayCircle,
        label: 'Đang thực hiện'
      },
      review: {
        color: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        icon: PauseCircle,
        label: 'Đang kiểm tra'
      },
      completed: {
        color: 'bg-green-100 text-green-800 border-green-200',
        icon: CheckCircle,
        label: 'Hoàn thành'
      },
      cancelled: {
        color: 'bg-red-100 text-red-800 border-red-200',
        icon: AlertTriangle,
        label: 'Đã hủy'
      }
    };
    return configs[status as keyof typeof configs] || configs.pending;
  };

  const getPriorityConfig = (priority: string) => {
    const configs = {
      low: { color: 'text-green-600', label: 'Thấp' },
      medium: { color: 'text-yellow-600', label: 'Trung bình' },
      high: { color: 'text-orange-600', label: 'Cao' },
      critical: { color: 'text-red-600', label: 'Khẩn cấp' }
    };
    return configs[priority as keyof typeof configs] || configs.medium;
  };

  const statusConfig = getStatusConfig(task.status);
  const priorityConfig = getPriorityConfig(task.priority);
  const StatusIcon = statusConfig.icon;

  // Tính toán thời gian
  const now = new Date();
  const startDate = new Date(task.start_date);
  const endDate = new Date(task.end_date);
  const isOverdue = task.status !== 'completed' && isAfter(now, endDate);
  const isUpcoming = isBefore(now, startDate);
  const daysRemaining = differenceInDays(endDate, now);

  // Tính toán progress color
  const getProgressColor = () => {
    if (task.progress >= 80) return 'bg-green-500';
    if (task.progress >= 60) return 'bg-blue-500';
    if (task.progress >= 40) return 'bg-yellow-500';
    return 'bg-gray-400';
  };

  // Quick status change
  const handleQuickStatusChange = (newStatus: string) => {
    onStatusChange?.(task.id, newStatus);
  };

  return (
    <TooltipProvider>
      <Card
        className={cn(
          'group relative transition-all duration-200 hover:shadow-lg hover:-translate-y-1',
          'border border-gray-200 hover:border-gray-300',
          isOverdue && 'border-red-200 bg-red-50/30',
          compact ? 'p-3' : 'p-4',
          className
        )}
        onMouseEnter={() => setIsHovered(true)}
        onMouseLeave={() => setIsHovered(false)}
      >
        <CardContent className={cn('space-y-3', compact && 'space-y-2')}>
          {/* Header với title và actions */}
          <div className="flex items-start justify-between gap-3">
            <div className="flex-1 min-w-0">
              <Link
                to={`/tasks/${task.id}`}
                className="block group-hover:text-blue-600 transition-colors"
              >
                <h3 className={cn(
                  'font-semibold text-gray-900 truncate',
                  compact ? 'text-sm' : 'text-base'
                )}>
                  {task.name}
                </h3>
              </Link>
              
              {!compact && task.description && (
                <p className="text-sm text-gray-600 mt-1 line-clamp-2">
                  {task.description}
                </p>
              )}
            </div>

            {/* Quick Actions */}
            <div className={cn(
              'flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity',
              isHovered && 'opacity-100'
            )}>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onEdit?.(task)}
                    className="h-8 w-8 p-0"
                  >
                    <Edit className="h-4 w-4" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>Chỉnh sửa</TooltipContent>
              </Tooltip>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => onEdit?.(task)}>
                    <Edit className="h-4 w-4 mr-2" />
                    Chỉnh sửa
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => onAssign?.(task.id)}>
                    <User className="h-4 w-4 mr-2" />
                    Phân công
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <LinkIcon className="h-4 w-4 mr-2" />
                    Sao chép liên kết
                  </DropdownMenuItem>
                  <DropdownMenuItem 
                    onClick={() => onDelete?.(task.id)}
                    className="text-red-600"
                  >
                    <Trash2 className="h-4 w-4 mr-2" />
                    Xóa
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          {/* Status và Priority */}
          <div className="flex items-center gap-2 flex-wrap">
            <Badge className={cn('border', statusConfig.color)}>
              <StatusIcon className="h-3 w-3 mr-1" />
              {statusConfig.label}
            </Badge>
            
            <Badge variant="outline" className="border-gray-200">
              <Flag className={cn('h-3 w-3 mr-1', priorityConfig.color)} />
              {priorityConfig.label}
            </Badge>

            {isOverdue && (
              <Badge className="bg-red-100 text-red-800 border-red-200">
                <AlertTriangle className="h-3 w-3 mr-1" />
                Quá hạn
              </Badge>
            )}

            {showProject && task.project && (
              <Badge variant="outline" className="text-xs">
                {task.project.name}
              </Badge>
            )}
          </div>

          {/* Progress Bar */}
          {!compact && (
            <div className="space-y-1">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Tiến độ</span>
                <span className="font-medium">{task.progress}%</span>
              </div>
              <Progress 
                value={task.progress} 
                className="h-2"
                indicatorClassName={getProgressColor()}
              />
            </div>
          )}

          {/* Assignees */}
          {task.assignees && task.assignees.length > 0 && (
            <div className="flex items-center gap-2">
              <User className="h-4 w-4 text-gray-400" />
              <div className="flex -space-x-2">
                {task.assignees.slice(0, 3).map((assignment) => (
                  <Tooltip key={assignment.id}>
                    <TooltipTrigger asChild>
                      <Avatar className="h-6 w-6 border-2 border-white">
                        <AvatarImage src={assignment.user.avatar} />
                        <AvatarFallback className="text-xs">
                          {assignment.user.name.charAt(0)}
                        </AvatarFallback>
                      </Avatar>
                    </TooltipTrigger>
                    <TooltipContent>
                      {assignment.user.name} ({assignment.split_percentage}%)
                    </TooltipContent>
                  </Tooltip>
                ))}
                {task.assignees.length > 3 && (
                  <div className="h-6 w-6 rounded-full bg-gray-100 border-2 border-white flex items-center justify-center">
                    <span className="text-xs text-gray-600">+{task.assignees.length - 3}</span>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Dates */}
          <div className="flex items-center justify-between text-sm text-gray-500">
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-1">
                <Calendar className="h-4 w-4" />
                <span>{format(startDate, 'dd/MM', { locale: vi })}</span>
              </div>
              <div className="flex items-center gap-1">
                <Clock className="h-4 w-4" />
                <span>{format(endDate, 'dd/MM', { locale: vi })}</span>
              </div>
            </div>
            
            {!isOverdue && daysRemaining >= 0 && (
              <span className={cn(
                'text-xs px-2 py-1 rounded-full',
                daysRemaining <= 3 ? 'bg-red-100 text-red-700' :
                daysRemaining <= 7 ? 'bg-yellow-100 text-yellow-700' :
                'bg-green-100 text-green-700'
              )}>
                {daysRemaining === 0 ? 'Hôm nay' : `${daysRemaining} ngày`}
              </span>
            )}
          </div>

          {/* Quick Status Actions */}
          {!compact && isHovered && (
            <div className="flex gap-1 pt-2 border-t border-gray-100">
              {task.status === 'pending' && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => handleQuickStatusChange('in_progress')}
                  className="flex-1 text-xs"
                >
                  <PlayCircle className="h-3 w-3 mr-1" />
                  Bắt đầu
                </Button>
              )}
              {task.status === 'in_progress' && (
                <>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleQuickStatusChange('review')}
                    className="flex-1 text-xs"
                  >
                    <PauseCircle className="h-3 w-3 mr-1" />
                    Kiểm tra
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleQuickStatusChange('completed')}
                    className="flex-1 text-xs"
                  >
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Hoàn thành
                  </Button>
                </>
              )}
              {task.status === 'review' && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => handleQuickStatusChange('completed')}
                  className="flex-1 text-xs"
                >
                  <CheckCircle className="h-3 w-3 mr-1" />
                  Hoàn thành
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </TooltipProvider>
  );
};