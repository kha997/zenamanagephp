import React, { useState, useMemo } from 'react';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Progress } from '@/components/ui/Progress';
import { 
  ChevronRight, 
  ChevronDown,
  Building2,
  Plus,
  Search,
  Filter,
  ExpandAll,
  CollapseAll
} from 'lucide-react';
import { ComponentWithChildren } from '../types/component';
import { cn } from '@/lib/utils';
import { formatCurrency } from '@/lib/utils';

interface ComponentTreeViewProps {
  components: ComponentWithChildren[];
  onComponentSelect?: (component: ComponentWithChildren) => void;
  onAddChild?: (parentComponent: ComponentWithChildren) => void;
  selectedComponentId?: string;
  searchTerm?: string;
  onSearchChange?: (term: string) => void;
  className?: string;
  showCosts?: boolean;
  showProgress?: boolean;
  maxDepth?: number;
}

export const ComponentTreeView: React.FC<ComponentTreeViewProps> = ({
  components,
  onComponentSelect,
  onAddChild,
  selectedComponentId,
  searchTerm = '',
  onSearchChange,
  className,
  showCosts = true,
  showProgress = true,
  maxDepth = 5
}) => {
  const [expandedNodes, setExpandedNodes] = useState<Set<string>>(new Set());
  const [localSearchTerm, setLocalSearchTerm] = useState(searchTerm);

  // Xử lý tìm kiếm local nếu không có onSearchChange
  const effectiveSearchTerm = onSearchChange ? searchTerm : localSearchTerm;
  const handleSearchChange = (term: string) => {
    if (onSearchChange) {
      onSearchChange(term);
    } else {
      setLocalSearchTerm(term);
    }
  };

  // Lọc và highlight components theo search term
  const filteredComponents = useMemo(() => {
    if (!effectiveSearchTerm) return components;
    
    const filterComponents = (items: ComponentWithChildren[]): ComponentWithChildren[] => {
      return items.reduce((acc, component) => {
        const matchesSearch = component.name.toLowerCase().includes(effectiveSearchTerm.toLowerCase());
        const filteredChildren = filterComponents(component.children || []);
        
        if (matchesSearch || filteredChildren.length > 0) {
          acc.push({
            ...component,
            children: filteredChildren
          });
        }
        
        return acc;
      }, [] as ComponentWithChildren[]);
    };
    
    return filterComponents(components);
  }, [components, effectiveSearchTerm]);

  // Toggle expand/collapse node
  const toggleNode = (componentId: string) => {
    const newExpanded = new Set(expandedNodes);
    if (newExpanded.has(componentId)) {
      newExpanded.delete(componentId);
    } else {
      newExpanded.add(componentId);
    }
    setExpandedNodes(newExpanded);
  };

  // Expand all nodes
  const expandAll = () => {
    const allIds = new Set<string>();
    const collectIds = (items: ComponentWithChildren[]) => {
      items.forEach(component => {
        if (component.children && component.children.length > 0) {
          allIds.add(component.id);
          collectIds(component.children);
        }
      });
    };
    collectIds(filteredComponents);
    setExpandedNodes(allIds);
  };

  // Collapse all nodes
  const collapseAll = () => {
    setExpandedNodes(new Set());
  };

  // Render tree node
  const renderTreeNode = (component: ComponentWithChildren, level: number = 0) => {
    if (level >= maxDepth) return null;
    
    const hasChildren = component.children && component.children.length > 0;
    const isExpanded = expandedNodes.has(component.id);
    const isSelected = selectedComponentId === component.id;
    
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

    return (
      <div key={component.id} className="select-none">
        {/* Node Content */}
        <div 
          className={cn(
            'flex items-center py-2 px-3 rounded-lg cursor-pointer transition-all duration-200',
            'hover:bg-gray-50 border-l-2',
            isSelected ? 'bg-blue-50 border-l-blue-500' : 'border-l-transparent',
            level > 0 && 'ml-6'
          )}
          style={{ paddingLeft: `${level * 24 + 12}px` }}
          onClick={() => onComponentSelect?.(component)}
        >
          {/* Expand/Collapse Button */}
          <div className="flex items-center mr-2">
            {hasChildren ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={(e) => {
                  e.stopPropagation();
                  toggleNode(component.id);
                }}
                className="p-1 h-6 w-6"
              >
                {isExpanded ? (
                  <ChevronDown className="w-4 h-4" />
                ) : (
                  <ChevronRight className="w-4 h-4" />
                )}
              </Button>
            ) : (
              <div className="w-6 h-6" />
            )}
          </div>

          {/* Icon */}
          <Building2 className="w-4 h-4 text-blue-600 mr-2 flex-shrink-0" />

          {/* Component Info */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center space-x-2">
              <span className={cn(
                'font-medium truncate',
                isSelected ? 'text-blue-900' : 'text-gray-900'
              )}>
                {component.name}
              </span>
              
              {/* Cost Variance Badge */}
              {showCosts && variance > 0 && (
                <Badge 
                  variant={isOverBudget ? 'destructive' : 'success'}
                  className="text-xs"
                >
                  {isOverBudget ? '+' : '-'}{variance.toFixed(1)}%
                </Badge>
              )}
            </div>
            
            {/* Progress Bar */}
            {showProgress && (
              <div className="mt-1">
                <Progress 
                  value={component.progress_percent} 
                  className="h-1.5"
                  color={getProgressColor(component.progress_percent)}
                />
              </div>
            )}
            
            {/* Cost Info */}
            {showCosts && (
              <div className="flex items-center space-x-4 mt-1 text-xs text-gray-500">
                <span>KH: {formatCurrency(component.planned_cost)}</span>
                <span className={isOverBudget ? 'text-red-600' : 'text-green-600'}>
                  TT: {formatCurrency(component.actual_cost)}
                </span>
                <span>{component.progress_percent}%</span>
              </div>
            )}
          </div>

          {/* Add Child Button */}
          {onAddChild && (
            <Button
              variant="ghost"
              size="sm"
              onClick={(e) => {
                e.stopPropagation();
                onAddChild(component);
              }}
              className="p-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
              title="Thêm thành phần con"
            >
              <Plus className="w-3 h-3" />
            </Button>
          )}
        </div>

        {/* Children */}
        {hasChildren && isExpanded && (
          <div className="ml-2">
            {component.children!.map(child => 
              renderTreeNode(child, level + 1)
            )}
          </div>
        )}
      </div>
    );
  };

  return (
    <Card className={cn('p-4', className)}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900">
          Cấu trúc thành phần
        </h3>
        
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={expandAll}
            className="flex items-center space-x-1"
          >
            <ExpandAll className="w-4 h-4" />
            <span>Mở rộng</span>
          </Button>
          
          <Button
            variant="outline"
            size="sm"
            onClick={collapseAll}
            className="flex items-center space-x-1"
          >
            <CollapseAll className="w-4 h-4" />
            <span>Thu gọn</span>
          </Button>
        </div>
      </div>

      {/* Search */}
      <div className="mb-4">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
          <Input
            placeholder="Tìm kiếm thành phần..."
            value={effectiveSearchTerm}
            onChange={(e) => handleSearchChange(e.target.value)}
            className="pl-10"
          />
        </div>
      </div>

      {/* Tree Content */}
      <div className="space-y-1 max-h-96 overflow-y-auto">
        {filteredComponents.length > 0 ? (
          filteredComponents.map(component => renderTreeNode(component))
        ) : (
          <div className="text-center py-8 text-gray-500">
            {effectiveSearchTerm ? (
              <div>
                <Filter className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>Không tìm thấy thành phần nào phù hợp</p>
                <p className="text-sm">Thử thay đổi từ khóa tìm kiếm</p>
              </div>
            ) : (
              <div>
                <Building2 className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p>Chưa có thành phần nào</p>
                <p className="text-sm">Thêm thành phần đầu tiên cho dự án</p>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Summary */}
      {filteredComponents.length > 0 && (
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="grid grid-cols-3 gap-4 text-sm">
            <div className="text-center">
              <p className="text-gray-500">Tổng số</p>
              <p className="font-semibold">{filteredComponents.length}</p>
            </div>
            {showCosts && (
              <>
                <div className="text-center">
                  <p className="text-gray-500">Chi phí KH</p>
                  <p className="font-semibold">
                    {formatCurrency(
                      filteredComponents.reduce((sum, c) => sum + c.planned_cost, 0)
                    )}
                  </p>
                </div>
                <div className="text-center">
                  <p className="text-gray-500">Chi phí TT</p>
                  <p className="font-semibold">
                    {formatCurrency(
                      filteredComponents.reduce((sum, c) => sum + c.actual_cost, 0)
                    )}
                  </p>
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </Card>
  );
};