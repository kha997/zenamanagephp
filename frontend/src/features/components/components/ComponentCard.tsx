import React from 'react';
import { Link } from 'react-router-dom';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Progress } from '@/components/ui/Progress';
import { Dropdown } from '@/components/ui/Dropdown';
import { 
  Building2, 
  TrendingUp, 
  DollarSign, 
  MoreVertical,
  Edit,
  Trash2,
  Eye,
  ChevronRight
} from 'lucide-react';
import { Component } from '../types/component';
import { cn } from '@/lib/utils';
import { formatCurrency } from '@/lib/utils';

interface ComponentCardProps {
  component: Component;
  onEdit?: (component: Component) => void;
  onDelete?: (component: Component) => void;
  onView?: (component: Component) => void;
  className?: string;
  showProject?: boolean;
  level?: number;
  isExpanded?: boolean;
  onToggleExpand?: (componentId: string) => void;
  hasChildren?: boolean;
}

export const ComponentCard: React.FC<ComponentCardProps> = ({
  component,
  onEdit,
  onDelete,
  onView,
  className,
  showProject = false,
  level = 0,
  isExpanded = false,
  onToggleExpand,
  hasChildren = false
}) => {
  const getProgressColor = (progress: number) => {
    if (progress >= 80) return 'success';
    if (progress >= 50) return 'primary';
    if (progress >= 25) return 'warning';
    return 'danger';
  };

  const getCostVariance = () => {
    if (component.planned_cost === 0) return { variance: 0, isOverBudget: false };
    const variance = ((component.actual_cost - component.planned_cost) / component.planned_cost) * 100;
    return {
      variance: Math.abs(variance),
      isOverBudget: variance > 0
    };
  };

  const { variance, isOverBudget } = getCostVariance();

  const dropdownItems = [
    {
      key: 'view',
      label: 'Xem chi tiết',
      icon: <Eye className="w-4 h-4" />,
      onClick: () => onView?.(component)
    },
    {
      key: 'edit',
      label: 'Chỉnh sửa',
      icon: <Edit className="w-4 h-4" />,
      onClick: () => onEdit?.(component)
    },
    {
      key: 'delete',
      label: 'Xóa',
      icon: <Trash2 className="w-4 h-4" />,
      onClick: () => onDelete?.(component),
      danger: true
    }
  ];

  return (
    <Card 
      className={cn(
        'hover:shadow-md transition-all duration-200 border-l-4',
        level > 0 && 'ml-6 border-l-gray-300',
        level === 0 && 'border-l-blue-500',
        className
      )}
    >
      <div className="p-4">
        {/* Header */}
        <div className="flex items-start justify-between mb-3">
          <div className="flex items-center space-x-2 flex-1">
            {hasChildren && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => onToggleExpand?.(component.id)}
                className="p-1 h-6 w-6"
              >
                <ChevronRight 
                  className={cn(
                    'w-4 h-4 transition-transform',
                    isExpanded && 'rotate-90'
                  )} 
                />
              </Button>
            )}
            
            <Building2 className="w-5 h-5 text-blue-600 flex-shrink-0" />
            
            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-gray-900 truncate">
                {component.name}
              </h3>
              {showProject && component.project && (
                <p className="text-sm text-gray-500 truncate">
                  Dự án: {component.project.name}
                </p>
              )}
              {component.parent_component && (
                <p className="text-xs text-gray-400 truncate">
                  Thuộc: {component.parent_component.name}
                </p>
              )}
            </div>
          </div>

          <Dropdown items={dropdownItems} placement="bottom-end">
            <Button variant="ghost" size="sm" className="p-1 h-8 w-8">
              <MoreVertical className="w-4 h-4" />
            </Button>
          </Dropdown>
        </div>

        {/* Progress */}
        <div className="mb-3">
          <div className="flex items-center justify-between mb-1">
            <span className="text-sm font-medium text-gray-700">
              Tiến độ
            </span>
            <span className="text-sm font-semibold text-gray-900">
              {component.progress_percent}%
            </span>
          </div>
          <Progress 
            value={component.progress_percent} 
            className="h-2"
            color={getProgressColor(component.progress_percent)}
          />
        </div>

        {/* Cost Information */}
        <div className="grid grid-cols-2 gap-4 mb-3">
          <div className="space-y-1">
            <p className="text-xs text-gray-500">Chi phí kế hoạch</p>
            <p className="text-sm font-semibold text-gray-900">
              {formatCurrency(component.planned_cost)}
            </p>
          </div>
          <div className="space-y-1">
            <p className="text-xs text-gray-500">Chi phí thực tế</p>
            <p className={cn(
              'text-sm font-semibold',
              isOverBudget ? 'text-red-600' : 'text-green-600'
            )}>
              {formatCurrency(component.actual_cost)}
            </p>
          </div>
        </div>

        {/* Cost Variance Badge */}
        {variance > 0 && (
          <div className="flex items-center justify-between">
            <Badge 
              variant={isOverBudget ? 'destructive' : 'success'}
              className="text-xs"
            >
              {isOverBudget ? 'Vượt' : 'Tiết kiệm'} {variance.toFixed(1)}%
            </Badge>
            
            <div className="flex items-center space-x-1 text-xs text-gray-500">
              <TrendingUp className="w-3 h-3" />
              <span>ID: {component.id.slice(-6)}</span>
            </div>
          </div>
        )}

        {/* Children Count */}
        {hasChildren && (
          <div className="mt-2 pt-2 border-t border-gray-100">
            <p className="text-xs text-gray-500">
              {component.children?.length || 0} thành phần con
            </p>
          </div>
        )}
      </div>
    </Card>
  );
};