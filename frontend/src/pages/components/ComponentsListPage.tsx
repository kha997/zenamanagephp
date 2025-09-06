import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useComponentsStore } from '@/store/components';
import { useProjectsStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { 
  Plus, 
  Search, 
  ChevronRight,
  ChevronDown,
  Package,
  DollarSign,
  TrendingUp,
  Eye,
  Edit,
  Trash2
} from 'lucide-react';
import type { Component } from '@/lib/types';

/**
 * Trang danh sách components với cấu trúc phân cấp
 * Hỗ trợ tree view và quản lý chi phí theo component
 */
export const ComponentsListPage: React.FC = () => {
  const { 
    components, 
    isLoading, 
    fetchComponents,
    deleteComponent 
  } = useComponentsStore();
  
  const { projects } = useProjectsStore();
  
  const [filters, setFilters] = useState({
    search: '',
    project_id: ''
  });

  const [expandedItems, setExpandedItems] = useState<Set<string>>(new Set());

  // Load dữ liệu khi component mount
  useEffect(() => {
    fetchComponents(filters);
  }, [filters, fetchComponents]);

  // Xử lý tìm kiếm
  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
  };

  // Xử lý lọc theo dự án
  const handleProjectFilter = (projectId: string) => {
    setFilters(prev => ({ ...prev, project_id: projectId }));
  };

  // Xử lý expand/collapse
  const toggleExpanded = (componentId: string) => {
    const newExpanded = new Set(expandedItems);
    if (newExpanded.has(componentId)) {
      newExpanded.delete(componentId);
    } else {
      newExpanded.add(componentId);
    }
    setExpandedItems(newExpanded);
  };

  // Xử lý xóa component
  const handleDelete = async (componentId: string) => {
    if (window.confirm('Bạn có chắc chắn muốn xóa component này?')) {
      await deleteComponent(componentId);
    }
  };

  // Tạo cấu trúc tree từ danh sách components
  const buildTree = (components: Component[]): Component[] => {
    const tree: Component[] = [];
    const map = new Map<string, Component & { children: Component[] }>();

    // Tạo map với children array
    components.forEach(component => {
      map.set(component.id, { ...component, children: [] });
    });

    // Xây dựng tree
    components.forEach(component => {
      const item = map.get(component.id)!;
      if (component.parent_component_id) {
        const parent = map.get(component.parent_component_id);
        if (parent) {
          parent.children.push(item);
        }
      } else {
        tree.push(item);
      }
    });

    return tree;
  };

  // Component TreeItem để hiển thị cấu trúc phân cấp
  const TreeItem: React.FC<{ 
    component: Component & { children?: Component[] }; 
    level: number 
  }> = ({ component, level }) => {
    const hasChildren = component.children && component.children.length > 0;
    const isExpanded = expandedItems.has(component.id);
    const project = projects.find(p => p.id === component.project_id);

    return (
      <div>
        <div 
          className="flex items-center justify-between p-4 hover:bg-gray-50 border-b"
          style={{ paddingLeft: `${level * 24 + 16}px` }}
        >
          <div className="flex items-center flex-1">
            {hasChildren ? (
              <button
                onClick={() => toggleExpanded(component.id)}
                className="mr-2 p-1 hover:bg-gray-200 rounded"
              >
                {isExpanded ? (
                  <ChevronDown className="w-4 h-4" />
                ) : (
                  <ChevronRight className="w-4 h-4" />
                )}
              </button>
            ) : (
              <div className="w-6 h-6 mr-2" />
            )}
            
            <Package className="w-5 h-5 mr-3 text-gray-400" />
            
            <div className="flex-1">
              <Link 
                to={`/components/${component.id}`}
                className="font-medium text-blue-600 hover:text-blue-500"
              >
                {component.name}
              </Link>
              {project && (
                <p className="text-sm text-gray-600 mt-1">
                  Dự án: {project.name}
                </p>
              )}
            </div>
          </div>

          <div className="flex items-center gap-6">
            {/* Progress */}
            <div className="w-24">
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm font-medium">{component.progress_percent}%</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  style={{ width: `${component.progress_percent}%` }}
                />
              </div>
            </div>

            {/* Planned Cost */}
            <div className="text-right">
              <p className="text-sm text-gray-600">Kế hoạch</p>
              <p className="font-medium">
                {component.planned_cost?.toLocaleString('vi-VN')} VNĐ
              </p>
            </div>

            {/* Actual Cost */}
            <div className="text-right">
              <p className="text-sm text-gray-600">Thực tế</p>
              <p className={`font-medium ${
                (component.actual_cost || 0) > (component.planned_cost || 0)
                  ? 'text-red-600' 
                  : 'text-green-600'
              }`}>
                {component.actual_cost?.toLocaleString('vi-VN')} VNĐ
              </p>
            </div>

            {/* Actions */}
            <div className="flex items-center gap-2">
              <Button
                variant="ghost"
                size="sm"
                as={Link}
                to={`/components/${component.id}`}
              >
                <Eye className="w-4 h-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                as={Link}
                to={`/components/${component.id}/edit`}
              >
                <Edit className="w-4 h-4" />
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleDelete(component.id)}
                className="text-red-600 hover:text-red-700"
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Children */}
        {hasChildren && isExpanded && component.children?.map((child) => (
          <TreeItem key={child.id} component={child} level={level + 1} />
        ))}
      </div>
    );
  };

  const treeData = buildTree(components);

  // Tính toán thống kê
  const stats = React.useMemo(() => {
    const total = components.length;
    const totalPlanned = components.reduce((sum, c) => sum + (c.planned_cost || 0), 0);
    const totalActual = components.reduce((sum, c) => sum + (c.actual_cost || 0), 0);
    const avgProgress = components.length > 0 
      ? components.reduce((sum, c) => sum + c.progress_percent, 0) / components.length 
      : 0;

    return { total, totalPlanned, totalActual, avgProgress };
  }, [components]);

  if (isLoading) {
    return <Loading.Skeleton />;
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Quản lý Components</h1>
          <p className="text-gray-600 mt-1">
            Cấu trúc phân cấp và quản lý chi phí
          </p>
        </div>
        <Button as={Link} to="/components/create">
          <Plus className="w-4 h-4 mr-2" />
          Tạo component
        </Button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Tổng components</p>
                <p className="text-2xl font-bold text-gray-900">{stats.total}</p>
              </div>
              <Package className="w-8 h-8 text-blue-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Chi phí kế hoạch</p>
                <p className="text-2xl font-bold text-gray-900">
                  {stats.totalPlanned.toLocaleString('vi-VN')}
                </p>
              </div>
              <DollarSign className="w-8 h-8 text-green-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Chi phí thực tế</p>
                <p className={`text-2xl font-bold ${
                  stats.totalActual > stats.totalPlanned ? 'text-red-600' : 'text-green-600'
                }`}>
                  {stats.totalActual.toLocaleString('vi-VN')}
                </p>
              </div>
              <DollarSign className="w-8 h-8 text-orange-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Tiến độ trung bình</p>
                <p className="text-2xl font-bold text-gray-900">
                  {Math.round(stats.avgProgress)}%
                </p>
              </div>
              <TrendingUp className="w-8 h-8 text-blue-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="flex gap-4">
            <div className="flex-1">
              <Input
                placeholder="Tìm kiếm component..."
                value={filters.search}
                onChange={(e) => handleSearch(e.target.value)}
                leftIcon={<Search className="w-4 h-4" />}
              />
            </div>
            <div className="w-64">
              <Select
                placeholder="Chọn dự án"
                value={filters.project_id}
                onChange={handleProjectFilter}
                options={[
                  { value: '', label: 'Tất cả dự án' },
                  ...projects.map(p => ({ value: p.id, label: p.name }))
                ]}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Components Tree */}
      <Card>
        <CardHeader>
          <CardTitle>Cấu trúc Components</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {treeData.length > 0 ? (
            <div>
              {treeData.map((component) => (
                <TreeItem key={component.id} component={component} level={0} />
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <Package className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p className="text-gray-600">Chưa có component nào</p>
              <Button as={Link} to="/components/create" className="mt-4">
                Tạo component đầu tiên
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};