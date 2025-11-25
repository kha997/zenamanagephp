import React, { useState } from 'react';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Progress } from '@/components/ui/Progress';
import { Tabs } from '@/components/ui/Tabs';
import { Card } from '@/components/ui/Card';
import { 
  Building2,
  Calendar,
  DollarSign,
  TrendingUp,
  Users,
  FileText,
  Activity,
  Edit,
  Trash2,
  Plus,
  ExternalLink
} from 'lucide-react';
import { Component } from '../types/component';
import { formatCurrency, formatDate } from '@/lib/utils';
import { cn } from '@/lib/utils';

interface ComponentDetailModalProps {
  component: Component | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit?: (component: Component) => void;
  onDelete?: (component: Component) => void;
  onAddChild?: (parentComponent: Component) => void;
  onViewTasks?: (component: Component) => void;
  className?: string;
}

export const ComponentDetailModal: React.FC<ComponentDetailModalProps> = ({
  component,
  isOpen,
  onClose,
  onEdit,
  onDelete,
  onAddChild,
  onViewTasks,
  className
}) => {
  const [activeTab, setActiveTab] = useState('overview');

  if (!component) return null;

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

  const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: <Building2 className="w-4 h-4" /> },
    { key: 'progress', label: 'Tiến độ', icon: <TrendingUp className="w-4 h-4" /> },
    { key: 'costs', label: 'Chi phí', icon: <DollarSign className="w-4 h-4" /> },
    { key: 'tasks', label: 'Nhiệm vụ', icon: <FileText className="w-4 h-4" /> },
    { key: 'history', label: 'Lịch sử', icon: <Activity className="w-4 h-4" /> }
  ];

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={component.name}
      size="xl"
      className={className}
    >
      <div className="space-y-6">
        {/* Header Actions */}
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <Building2 className="w-6 h-6 text-blue-600" />
            <div>
              <h2 className="text-xl font-semibold text-gray-900">
                {component.name}
              </h2>
              {component.parent_component && (
                <p className="text-sm text-gray-500">
                  Thuộc: {component.parent_component.name}
                </p>
              )}
            </div>
          </div>
          
          <div className="flex items-center space-x-2">
            {onAddChild && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => onAddChild(component)}
                className="flex items-center space-x-1"
              >
                <Plus className="w-4 h-4" />
                <span>Thêm con</span>
              </Button>
            )}
            
            {onEdit && (
              <Button
                variant="outline"
                size="sm"
                onClick={() => onEdit(component)}
                className="flex items-center space-x-1"
              >
                <Edit className="w-4 h-4" />
                <span>Chỉnh sửa</span>
              </Button>
            )}
            
            {onDelete && (
              <Button
                variant="destructive"
                size="sm"
                onClick={() => onDelete(component)}
                className="flex items-center space-x-1"
              >
                <Trash2 className="w-4 h-4" />
                <span>Xóa</span>
              </Button>
            )}
          </div>
        </div>

        {/* Quick Stats */}
        <div className="grid grid-cols-4 gap-4">
          <Card className="p-4 text-center">
            <TrendingUp className="w-6 h-6 mx-auto mb-2 text-blue-600" />
            <p className="text-sm text-gray-500">Tiến độ</p>
            <p className="text-lg font-semibold">{component.progress_percent}%</p>
          </Card>
          
          <Card className="p-4 text-center">
            <DollarSign className="w-6 h-6 mx-auto mb-2 text-green-600" />
            <p className="text-sm text-gray-500">Chi phí KH</p>
            <p className="text-lg font-semibold">
              {formatCurrency(component.planned_cost)}
            </p>
          </Card>
          
          <Card className="p-4 text-center">
            <DollarSign className={cn(
              'w-6 h-6 mx-auto mb-2',
              isOverBudget ? 'text-red-600' : 'text-green-600'
            )} />
            <p className="text-sm text-gray-500">Chi phí TT</p>
            <p className={cn(
              'text-lg font-semibold',
              isOverBudget ? 'text-red-600' : 'text-green-600'
            )}>
              {formatCurrency(component.actual_cost)}
            </p>
          </Card>
          
          <Card className="p-4 text-center">
            <FileText className="w-6 h-6 mx-auto mb-2 text-purple-600" />
            <p className="text-sm text-gray-500">Nhiệm vụ</p>
            <p className="text-lg font-semibold">
              {component.tasks?.length || 0}
            </p>
          </Card>
        </div>

        {/* Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <Tabs.List className="grid grid-cols-5">
            {tabs.map(tab => (
              <Tabs.Trigger key={tab.key} value={tab.key} className="flex items-center space-x-2">
                {tab.icon}
                <span>{tab.label}</span>
              </Tabs.Trigger>
            ))}
          </Tabs.List>

          {/* Overview Tab */}
          <Tabs.Content value="overview" className="space-y-4">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Thông tin cơ bản</h3>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-gray-500">ID</p>
                  <p className="font-medium">{component.id}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Dự án</p>
                  <p className="font-medium">{component.project?.name || 'N/A'}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Ngày tạo</p>
                  <p className="font-medium">{formatDate(component.created_at)}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-500">Cập nhật cuối</p>
                  <p className="font-medium">{formatDate(component.updated_at)}</p>
                </div>
              </div>
            </Card>

            {/* Children Components */}
            {component.children && component.children.length > 0 && (
              <Card className="p-4">
                <h3 className="font-semibold mb-3">Thành phần con ({component.children.length})</h3>
                <div className="space-y-2">
                  {component.children.map(child => (
                    <div key={child.id} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                      <div className="flex items-center space-x-2">
                        <Building2 className="w-4 h-4 text-blue-600" />
                        <span className="font-medium">{child.name}</span>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Badge variant="outline">{child.progress_percent}%</Badge>
                        <Button variant="ghost" size="sm">
                          <ExternalLink className="w-3 h-3" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </Card>
            )}
          </Tabs.Content>

          {/* Progress Tab */}
          <Tabs.Content value="progress" className="space-y-4">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Tiến độ thực hiện</h3>
              <div className="space-y-4">
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-medium">Tiến độ hiện tại</span>
                    <span className="text-lg font-semibold">{component.progress_percent}%</span>
                  </div>
                  <Progress 
                    value={component.progress_percent} 
                    className="h-3"
                    color={getProgressColor(component.progress_percent)}
                  />
                </div>
                
                <div className="grid grid-cols-3 gap-4 text-center">
                  <div>
                    <p className="text-sm text-gray-500">Chưa bắt đầu</p>
                    <p className="text-lg font-semibold text-gray-600">
                      {100 - component.progress_percent}%
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Đang thực hiện</p>
                    <p className="text-lg font-semibold text-blue-600">
                      {component.progress_percent}%
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-500">Hoàn thành</p>
                    <p className="text-lg font-semibold text-green-600">
                      {component.progress_percent === 100 ? '100%' : '0%'}
                    </p>
                  </div>
                </div>
              </div>
            </Card>
          </Tabs.Content>

          {/* Costs Tab */}
          <Tabs.Content value="costs" className="space-y-4">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Phân tích chi phí</h3>
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="p-3 bg-blue-50 rounded">
                    <p className="text-sm text-blue-600 font-medium">Chi phí kế hoạch</p>
                    <p className="text-xl font-bold text-blue-900">
                      {formatCurrency(component.planned_cost)}
                    </p>
                  </div>
                  <div className={cn(
                    'p-3 rounded',
                    isOverBudget ? 'bg-red-50' : 'bg-green-50'
                  )}>
                    <p className={cn(
                      'text-sm font-medium',
                      isOverBudget ? 'text-red-600' : 'text-green-600'
                    )}>
                      Chi phí thực tế
                    </p>
                    <p className={cn(
                      'text-xl font-bold',
                      isOverBudget ? 'text-red-900' : 'text-green-900'
                    )}>
                      {formatCurrency(component.actual_cost)}
                    </p>
                  </div>
                </div>
                
                {variance > 0 && (
                  <div className={cn(
                    'p-3 rounded border-l-4',
                    isOverBudget ? 'bg-red-50 border-l-red-500' : 'bg-green-50 border-l-green-500'
                  )}>
                    <p className={cn(
                      'font-medium',
                      isOverBudget ? 'text-red-900' : 'text-green-900'
                    )}>
                      {isOverBudget ? 'Vượt ngân sách' : 'Tiết kiệm ngân sách'}: {variance.toFixed(1)}%
                    </p>
                    <p className={cn(
                      'text-sm',
                      isOverBudget ? 'text-red-700' : 'text-green-700'
                    )}>
                      {isOverBudget ? 'Vượt' : 'Tiết kiệm'}: {formatCurrency(Math.abs(component.actual_cost - component.planned_cost))}
                    </p>
                  </div>
                )}
              </div>
            </Card>
          </Tabs.Content>

          {/* Tasks Tab */}
          <Tabs.Content value="tasks" className="space-y-4">
            <Card className="p-4">
              <div className="flex items-center justify-between mb-3">
                <h3 className="font-semibold">Nhiệm vụ liên quan</h3>
                {onViewTasks && (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onViewTasks(component)}
                    className="flex items-center space-x-1"
                  >
                    <ExternalLink className="w-4 h-4" />
                    <span>Xem tất cả</span>
                  </Button>
                )}
              </div>
              
              {component.tasks && component.tasks.length > 0 ? (
                <div className="space-y-2">
                  {component.tasks.slice(0, 5).map(task => (
                    <div key={task.id} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                      <div>
                        <p className="font-medium">{task.name}</p>
                        <p className="text-sm text-gray-500">{task.status}</p>
                      </div>
                      <Badge variant="outline">{task.progress || 0}%</Badge>
                    </div>
                  ))}
                  {component.tasks.length > 5 && (
                    <p className="text-sm text-gray-500 text-center">
                      Và {component.tasks.length - 5} nhiệm vụ khác...
                    </p>
                  )}
                </div>
              ) : (
                <div className="text-center py-8 text-gray-500">
                  <FileText className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                  <p>Chưa có nhiệm vụ nào</p>
                  <p className="text-sm">Thêm nhiệm vụ để theo dõi tiến độ</p>
                </div>
              )}
            </Card>
          </Tabs.Content>

          {/* History Tab */}
          <Tabs.Content value="history" className="space-y-4">
            <Card className="p-4">
              <h3 className="font-semibold mb-3">Lịch sử thay đổi</h3>
              <div className="space-y-3">
                <div className="flex items-start space-x-3 p-3 bg-gray-50 rounded">
                  <Activity className="w-4 h-4 text-blue-600 mt-1" />
                  <div className="flex-1">
                    <p className="font-medium">Tạo thành phần</p>
                    <p className="text-sm text-gray-500">
                      {formatDate(component.created_at)}
                    </p>
                  </div>
                </div>
                
                <div className="flex items-start space-x-3 p-3 bg-gray-50 rounded">
                  <Activity className="w-4 h-4 text-green-600 mt-1" />
                  <div className="flex-1">
                    <p className="font-medium">Cập nhật cuối cùng</p>
                    <p className="text-sm text-gray-500">
                      {formatDate(component.updated_at)}
                    </p>
                  </div>
                </div>
              </div>
            </Card>
          </Tabs.Content>
        </Tabs>
      </div>
    </Modal>
  );
};