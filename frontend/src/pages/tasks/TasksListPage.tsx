import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useTasksStore } from '@/store/tasks';
import { useProjectsStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Table } from '@/components/ui/Table';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { 
  Plus, 
  Search, 
  Filter, 
  Calendar,
  User,
  Clock,
  CheckCircle,
  AlertTriangle,
  MoreHorizontal,
  Eye,
  Edit,
  Trash2,
  Kanban
} from 'lucide-react';
import type { Task } from '@/lib/types';

/**
 * Trang danh sách nhiệm vụ với tính năng tìm kiếm, lọc và quản lý
 * Hỗ trợ view theo list và kanban board
 */
export const TasksListPage: React.FC = () => {
  const { 
    tasks, 
    isLoading, 
    pagination,
    fetchTasks,
    deleteTask 
  } = useTasksStore();
  
  const { projects } = useProjectsStore();
  
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    project_id: '',
    assigned_to: '',
    priority: '',
    sortBy: 'created_at',
    sortOrder: 'desc' as 'asc' | 'desc'
  });

  const [viewMode, setViewMode] = useState<'list' | 'board'>('list');

  // Load dữ liệu khi component mount
  useEffect(() => {
    fetchTasks(filters);
  }, [filters, fetchTasks]);

  // Xử lý tìm kiếm
  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
  };

  // Xử lý lọc
  const handleFilter = (field: string, value: string) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  // Xử lý sắp xếp
  const handleSort = (field: string) => {
    setFilters(prev => ({
      ...prev,
      sortBy: field,
      sortOrder: prev.sortBy === field && prev.sortOrder === 'asc' ? 'desc' : 'asc'
    }));
  };

  // Xử lý xóa nhiệm vụ
  const handleDelete = async (taskId: string) => {
    if (window.confirm('Bạn có chắc chắn muốn xóa nhiệm vụ này?')) {
      await deleteTask(taskId);
    }
  };

  // Tính toán thống kê
  const stats = React.useMemo(() => {
    const total = tasks.length;
    const pending = tasks.filter(t => t.status === 'pending').length;
    const inProgress = tasks.filter(t => t.status === 'in_progress').length;
    const completed = tasks.filter(t => t.status === 'completed').length;
    const overdue = tasks.filter(t => 
      t.status !== 'completed' && new Date(t.end_date) < new Date()
    ).length;

    return { total, pending, inProgress, completed, overdue };
  }, [tasks]);

  // Định nghĩa cột cho bảng
  const columns = [
    {
      key: 'name',
      title: 'Tên nhiệm vụ',
      sortable: true,
      render: (task: Task) => (
        <div>
          <Link 
            to={`/tasks/${task.id}`}
            className="font-medium text-blue-600 hover:text-blue-500"
          >
            {task.name}
          </Link>
          <p className="text-sm text-gray-600 mt-1">
            Dự án: {projects.find(p => p.id === task.project_id)?.name}
          </p>
        </div>
      )
    },
    {
      key: 'status',
      title: 'Trạng thái',
      sortable: true,
      render: (task: Task) => (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
          task.status === 'completed' 
            ? 'bg-green-100 text-green-800'
            : task.status === 'in_progress'
            ? 'bg-blue-100 text-blue-800'
            : task.status === 'on_hold'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-gray-100 text-gray-800'
        }`}>
          {task.status === 'completed' ? 'Hoàn thành' :
           task.status === 'in_progress' ? 'Đang thực hiện' :
           task.status === 'on_hold' ? 'Tạm dừng' : 'Chờ xử lý'}
        </span>
      )
    },
    {
      key: 'priority',
      title: 'Độ ưu tiên',
      sortable: true,
      render: (task: Task) => (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
          task.priority === 'high' 
            ? 'bg-red-100 text-red-800'
            : task.priority === 'medium'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-green-100 text-green-800'
        }`}>
          {task.priority === 'high' ? 'Cao' :
           task.priority === 'medium' ? 'Trung bình' : 'Thấp'}
        </span>
      )
    },
    {
      key: 'assigned_to',
      title: 'Người thực hiện',
      render: (task: Task) => (
        <div className="flex items-center">
          <User className="w-4 h-4 mr-2 text-gray-400" />
          <span className="text-sm">{task.assigned_to?.name || 'Chưa phân công'}</span>
        </div>
      )
    },
    {
      key: 'end_date',
      title: 'Hạn hoàn thành',
      sortable: true,
      render: (task: Task) => {
        const isOverdue = new Date(task.end_date) < new Date() && task.status !== 'completed';
        return (
          <div className={`flex items-center text-sm ${
            isOverdue ? 'text-red-600' : 'text-gray-600'
          }`}>
            <Calendar className="w-4 h-4 mr-1" />
            {new Date(task.end_date).toLocaleDateString('vi-VN')}
            {isOverdue && <AlertTriangle className="w-4 h-4 ml-1" />}
          </div>
        );
      }
    },
    {
      key: 'actions',
      title: '',
      render: (task: Task) => (
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/tasks/${task.id}`}
          >
            <Eye className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/tasks/${task.id}/edit`}
          >
            <Edit className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => handleDelete(task.id)}
            className="text-red-600 hover:text-red-700"
          >
            <Trash2 className="w-4 h-4" />
          </Button>
        </div>
      )
    }
  ];

  if (isLoading) {
    return <Loading.Skeleton />;
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Quản lý nhiệm vụ</h1>
          <p className="text-gray-600 mt-1">
            Tổng cộng {stats.total} nhiệm vụ
          </p>
        </div>
        <div className="flex gap-3">
          <div className="flex bg-gray-100 rounded-lg p-1">
            <Button
              variant={viewMode === 'list' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('list')}
            >
              Danh sách
            </Button>
            <Button
              variant={viewMode === 'board' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('board')}
              as={Link}
              to="/tasks/board"
            >
              <Kanban className="w-4 h-4 mr-2" />
              Kanban
            </Button>
          </div>
          <Button as={Link} to="/tasks/create">
            <Plus className="w-4 h-4 mr-2" />
            Tạo nhiệm vụ
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Tổng số</p>
                <p className="text-2xl font-bold">{stats.total}</p>
              </div>
              <Clock className="w-8 h-8 text-gray-400" />
            </div>
          </CardContent>
        </Card>
        
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Chờ xử lý</p>
                <p className="text-2xl font-bold text-gray-600">{stats.pending}</p>
              </div>
              <Clock className="w-8 h-8 text-gray-400" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Đang thực hiện</p>
                <p className="text-2xl font-bold text-blue-600">{stats.inProgress}</p>
              </div>
              <Clock className="w-8 h-8 text-blue-400" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Hoàn thành</p>
                <p className="text-2xl font-bold text-green-600">{stats.completed}</p>
              </div>
              <CheckCircle className="w-8 h-8 text-green-400" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Quá hạn</p>
                <p className="text-2xl font-bold text-red-600">{stats.overdue}</p>
              </div>
              <AlertTriangle className="w-8 h-8 text-red-400" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div className="md:col-span-2">
              <Input
                placeholder="Tìm kiếm nhiệm vụ..."
                value={filters.search}
                onChange={(e) => handleSearch(e.target.value)}
                leftIcon={<Search className="w-4 h-4" />}
              />
            </div>
            <Select
              placeholder="Dự án"
              value={filters.project_id}
              onChange={(value) => handleFilter('project_id', value)}
              options={[
                { value: '', label: 'Tất cả dự án' },
                ...projects.map(p => ({ value: p.id, label: p.name }))
              ]}
            />
            <Select
              placeholder="Trạng thái"
              value={filters.status}
              onChange={(value) => handleFilter('status', value)}
              options={[
                { value: '', label: 'Tất cả trạng thái' },
                { value: 'pending', label: 'Chờ xử lý' },
                { value: 'in_progress', label: 'Đang thực hiện' },
                { value: 'on_hold', label: 'Tạm dừng' },
                { value: 'completed', label: 'Hoàn thành' }
              ]}
            />
            <Select
              placeholder="Độ ưu tiên"
              value={filters.priority}
              onChange={(value) => handleFilter('priority', value)}
              options={[
                { value: '', label: 'Tất cả mức độ' },
                { value: 'high', label: 'Cao' },
                { value: 'medium', label: 'Trung bình' },
                { value: 'low', label: 'Thấp' }
              ]}
            />
          </div>
        </CardContent>
      </Card>

      {/* Tasks Table */}
      <Card>
        <CardContent className="p-0">
          <Table
            columns={columns}
            data={tasks}
            pagination={pagination}
            onSort={handleSort}
            sortBy={filters.sortBy}
            sortOrder={filters.sortOrder}
          />
        </CardContent>
      </Card>
    </div>
  );
};